<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Http\Controllers\API\NotificationController;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class MessagesController extends Controller
{
  /**
   * 特定のチャットルームのメッセージ一覧を取得する
   */
  public function index(ChatRoom $chatRoom, Request $request)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // ユーザーがこのチャットルームの参加者であることを確認
    if (!$chatRoom->hasParticipant($user->id)) {
      return response()->json(['message' => 'アクセス権がありません。'], 403);
    }

    // グループチャットの場合、グループメンバーかどうかを確認
    if ($chatRoom->isGroupChat()) {
      if ($chatRoom->group_id) {
        $group = $chatRoom->group;
        if ($group) {
          // ユーザーがグループメンバーかチェック - 新アーキテクチャでは、グループチャットの場合はhasParticipantで十分
          if (!$chatRoom->hasParticipant($user->id)) {
            return response()->json([
              'message' => 'グループメンバーではないため、このチャットにアクセスできません。',
            ], 403);
          }
        } else {
          return response()->json([
            'message' => 'グループが見つかりません。',
          ], 404);
        }
      } else {
        return response()->json([
          'message' => 'グループIDが見つかりません。',
        ], 404);
      }
    }

    // メンバーチャットの場合、グループメンバーかどうかを確認
    if ($chatRoom->isMemberChat()) {
      // グループが存在し、両方のユーザーがそのグループのメンバーであることを確認
      if ($chatRoom->group_id) {
        $group = $chatRoom->group;
        if ($group) {
          // 両方のユーザーがグループメンバーかチェック
          $otherUserId = $chatRoom->participant1_id === $user->id
            ? $chatRoom->participant2_id
            : $chatRoom->participant1_id;

          if (!$group->hasMember($user->id) || !$group->hasMember($otherUserId)) {
            return response()->json([
              'message' => 'グループメンバーではないため、このチャットにアクセスできません。',
            ], 403);
          }
        } else {
          return response()->json([
            'message' => 'グループが見つかりません。',
          ], 404);
        }
      } else {
        // グループに関連しないメンバーチャットの場合は友達関係を確認
        $otherUserId = $chatRoom->participant1_id === $user->id
          ? $chatRoom->participant2_id
          : $chatRoom->participant1_id;

        if ($otherUserId) {
          // 友達関係を確認
          $currentFriends = $user->friends()->pluck('id')->toArray();
          if (!in_array($otherUserId, $currentFriends)) {
            return response()->json([
              'message' => '友達関係が解除されたため、このチャットにアクセスできません。',
              'friendship_status' => 'unfriended'
            ], 403);
          }
        }
      }
    }

    $messages = $chatRoom->messages()
      ->whereNull('admin_deleted_at') // 管理者によって削除されていないメッセージのみ
      ->with(['sender' => function ($query) {
        $query->select('id', 'name', 'friend_id'); // 送信者の基本情報を選択
      }, 'adminSender' => function ($query) {
        $query->select('id', 'name'); // 管理者送信者の基本情報を選択
      }])
      ->orderBy('sent_at', 'desc') // 最新のメッセージから表示
      ->paginate(20); // ページネーション

    // グループチャットの場合、各メッセージ送信者の退室状態を付加
    if ($chatRoom->isGroupChat() && $chatRoom->group) {
      $group = $chatRoom->group;
      foreach ($messages as $message) {
        if ($message->sender_id) {
          // 送信者がグループメンバーかつ退室済みかどうかをチェック
          $memberInfo = $group->groupMembers()
            ->where('user_id', $message->sender_id)
            ->first();

          if ($memberInfo && $memberInfo->left_at) {
            // カスタムプロパティとして退室状態を追加
            $message->sender_has_left = true;
            $message->sender_left_at = $memberInfo->left_at;
          } else {
            $message->sender_has_left = false;
            $message->sender_left_at = null;
          }
        }
      }
    }

    // メッセージを取得後、このチャットルームを既読にする（新アーキテクチャでは簡素化）
    // 既読管理は新アーキテクチャでは別途実装する予定のため、一旦コメントアウト
    // $participant = $chatRoom->participants()->where('user_id', $user->id)->first();
    // if ($participant && $messages->isNotEmpty()) {
    //   $participant->update([
    //     'last_read_at' => now(),
    //   ]);
    // }

    return response()->json($messages);
  }

  /**
   * 新しいメッセージを送信する
   */
  public function store(ChatRoom $chatRoom, Request $request)
  {
    try {
      Log::info('メッセージ送信処理を開始', [
        'chat_room_id' => $chatRoom->id,
        'room_token' => $chatRoom->room_token,
        'request_data' => $request->all()
      ]);

      $user = Auth::user();

      Log::info('認証ユーザー確認完了', [
        'user_id' => $user->id,
        'is_deleted' => $user->isDeleted()
      ]);

      // 削除されたユーザーはメッセージ送信不可
      if ($user->isDeleted()) {
        return response()->json(['message' => 'アカウントが削除されています。'], 403);
      }

      // ユーザーがこのチャットルームの参加者であることを確認
      if (!$chatRoom->hasParticipant($user->id)) {
        return response()->json(['message' => 'このチャットルームにメッセージを送信する権限がありません。'], 403);
      }

      Log::info('基本権限チェック完了');

      // グループチャットの場合、グループメンバーかどうかを確認
      if ($chatRoom->isGroupChat()) {
        Log::info('グループチャット権限チェック開始');

        if ($chatRoom->group_id) {
          $group = $chatRoom->group;
          if ($group) {
            // ユーザーがグループメンバーかチェック - 新アーキテクチャでは、グループチャットの場合はhasParticipantで十分
            if (!$chatRoom->hasParticipant($user->id)) {
              Log::warning('グループメンバーではないためメッセージ送信拒否', [
                'user_id' => $user->id,
                'group_id' => $group->id
              ]);
              return response()->json([
                'message' => 'グループメンバーではないため、このチャットにメッセージを送信できません。',
              ], 403);
            }

            Log::info('グループメンバーチェック完了');
          } else {
            return response()->json([
              'message' => 'グループが見つかりません。',
            ], 404);
          }
        } else {
          return response()->json([
            'message' => 'グループIDが見つかりません。',
          ], 404);
        }
      }

      // メンバーチャットの場合、グループメンバーかどうかを確認
      if ($chatRoom->isMemberChat()) {
        // グループが存在し、両方のユーザーがそのグループのメンバーであることを確認
        if ($chatRoom->group_id) {
          $group = $chatRoom->group;
          if ($group) {
            // 両方のユーザーがグループメンバーかチェック
            $otherUserId = $chatRoom->participant1_id === $user->id
              ? $chatRoom->participant2_id
              : $chatRoom->participant1_id;

            if (!$group->hasMember($user->id) || !$group->hasMember($otherUserId)) {
              return response()->json([
                'message' => 'グループメンバーではないため、このチャットにメッセージを送信できません。',
              ], 403);
            }
          } else {
            return response()->json([
              'message' => 'グループが見つかりません。',
            ], 404);
          }
        } else {
          // グループに関連しないメンバーチャットの場合は友達関係を確認
          $otherUserId = $chatRoom->participant1_id === $user->id
            ? $chatRoom->participant2_id
            : $chatRoom->participant1_id;

          if ($otherUserId) {
            // 友達関係を確認
            $currentFriends = $user->friends()->pluck('id')->toArray();
            if (!in_array($otherUserId, $currentFriends)) {
              return response()->json([
                'message' => '友達関係が解除されたため、このチャットにメッセージを送信できません。',
                'friendship_status' => 'unfriended'
              ], 403);
            }
          }
        }
      }

      Log::info('友達関係チェック完了');

      $request->validate([
        'text_content' => 'required|string|max:5000', // 最大文字数など適宜調整
        // 'content_type' => 'sometimes|in:text,image,file' // 将来的な拡張用
      ]);

      Log::info('バリデーション完了');

      $message = $chatRoom->messages()->create([
        'sender_id' => $user->id,
        'text_content' => $request->input('text_content'),
        'content_type' => 'text', // MVPではtext固定
        'sent_at' => now(),
      ]);

      Log::info('メッセージ作成完了', ['message_id' => $message->id]);

      $message->load(['sender' => function ($query) {
        $query->select('id', 'name', 'friend_id');
      }, 'adminSender' => function ($query) {
        $query->select('id', 'name');
      }]);

      // グループチャットの場合、送信者の退室状態を付加
      if ($chatRoom->isGroupChat() && $chatRoom->group) {
        $group = $chatRoom->group;
        if ($message->sender_id) {
          $memberInfo = $group->groupMembers()
            ->where('user_id', $message->sender_id)
            ->first();

          if ($memberInfo && $memberInfo->left_at) {
            $message->sender_has_left = true;
            $message->sender_left_at = $memberInfo->left_at;
          } else {
            $message->sender_has_left = false;
            $message->sender_left_at = null;
          }
        }
      }

      Log::info('メッセージリレーション読み込み完了');

      // プッシュ通知の送信
      // 同じチャットルームの参加者全員（自分以外）に通知を送信する
      $participants = $chatRoom->getParticipants()->reject(function ($user_id) use ($user) {
        return $user_id === $user->id;
      });

      Log::info('参加者取得完了', ['participants_count' => $participants->count()]);

      if ($participants->isNotEmpty()) {
        // メッセージプレビュー（長い場合は短縮する）
        $messagePreview = mb_substr($message->text_content, 0, 50);
        if (mb_strlen($message->text_content) > 50) {
          $messagePreview .= '...';
        }

        Log::info('通知送信開始');

        $notificationController = new NotificationController();

        foreach ($participants as $participantUserId) {
          // 各参加者にプッシュ通知を送信
          $participantUser = User::find($participantUserId);
          if ($participantUser && !$participantUser->isDeleted()) {
            try {
              $notificationController->sendNewMessageNotification(
                $participantUser,
                $user->name,
                $messagePreview,
                $chatRoom->id,
                $chatRoom->room_token
              );
            } catch (\Exception $e) {
              Log::warning('新しいメッセージ通知の送信に失敗しました', [
                'recipient_user_id' => $participantUser->id,
                'sender_user_id' => $user->id,
                'chat_room_id' => $chatRoom->id,
                'error' => $e->getMessage()
              ]);
              // 通知エラーは無視して処理を続行
            }
          }
        }

        Log::info('通知送信完了');
      }

      Log::info('メッセージ送信処理完了', ['message_id' => $message->id]);

      // TODO: リアルタイムで相手にメッセージを通知するイベントを発行 (例: NewMessageEvent)
      // broadcast(new NewMessageEvent($message))->toOthers();

      return response()->json($message, 201);
    } catch (\Exception $e) {
      Log::error('メッセージ送信処理でエラーが発生しました', [
        'chat_room_id' => $chatRoom->id ?? 'unknown',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'message' => 'メッセージの送信に失敗しました。'
      ], 500);
    }
  }

  /**
   * Display the specified resource.
   */
  public function show(string $id)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, string $id)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(string $id)
  {
    //
  }
}
