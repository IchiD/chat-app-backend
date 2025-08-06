<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Notifications\PushNotification;
use App\Models\ChatRoomRead;
use App\Models\MessageRead;
use App\Notifications\NewMessageNotification;

class MessagesController extends Controller
{
  /**
   * 特定のチャットルームのメッセージ一覧を取得する
   */
  public function index(ChatRoom $chatRoom, Request $request)
  {
    try {
      Log::info('メッセージ一覧取得開始', [
        'chat_room_id' => $chatRoom->id,
        'room_token' => $chatRoom->room_token,
        'type' => $chatRoom->type,
        'group_id' => $chatRoom->group_id
      ]);

      $user = Auth::user();
      Log::info('認証ユーザー取得完了', ['user_id' => $user->id]);

      // ユーザーがこのチャットルームの参加者であることを確認
      if (!$chatRoom->hasParticipant($user->id)) {
        Log::warning('アクセス権がありません', [
          'user_id' => $user->id,
          'chat_room_id' => $chatRoom->id
        ]);
        return response()->json(['message' => 'アクセス権がありません。'], 403);
      }
      Log::info('参加者チェック完了');

      // 削除されたユーザーはアクセス不可
      if ($user->isDeleted()) {
        Log::warning('削除されたユーザーのアクセス試行', ['user_id' => $user->id]);
        return response()->json(['message' => 'アカウントが削除されています。'], 403);
      }
      Log::info('ユーザー状態チェック完了');

    // friend_chatまたはmember_chatの場合の追加チェック
    if ($chatRoom->type === 'friend_chat' || $chatRoom->type === 'member_chat') {
      // グループに所属していることを確認（member_chatの場合）
      if ($chatRoom->type === 'member_chat' && $chatRoom->group) {
        try {
          $isMember = $chatRoom->group->activeMembers()
            ->where('user_id', $user->id)
            ->exists();

          if (!$isMember) {
            return response()->json([
              'message' => 'このグループのメンバーではないため、チャットにアクセスできません。',
              'membership_status' => 'not_member'
            ], 403);
          }
        } catch (\Exception $e) {
          Log::error('グループメンバーシップの確認でエラー', [
            'chat_room_id' => $chatRoom->id,
            'group_id' => $chatRoom->group_id,
            'user_id' => $user->id,
            'error' => $e->getMessage()
          ]);
          // エラーが発生した場合はアクセスを拒否
          return response()->json([
            'message' => 'グループメンバーシップを確認できませんでした。',
            'error' => config('app.debug') ? $e->getMessage() : null
          ], 500);
        }
      }

      // 友達関係を確認（friend_chatの場合）
      if ($chatRoom->type === 'friend_chat') {
        $otherUserId = $chatRoom->participant1_id === $user->id
          ? $chatRoom->participant2_id
          : $chatRoom->participant1_id;

        if ($otherUserId) {
          try {
            // 友達関係を確認
            $currentFriends = $user->friends()->pluck('id')->toArray();
            if (!in_array($otherUserId, $currentFriends)) {
              return response()->json([
                'message' => '友達関係が解除されたため、このチャットにアクセスできません。',
                'friendship_status' => 'unfriended'
              ], 403);
            }
          } catch (\Exception $e) {
            Log::error('友達関係の確認でエラー', [
              'user_id' => $user->id,
              'other_user_id' => $otherUserId,
              'error' => $e->getMessage()
            ]);
            // エラーが発生した場合はアクセスを拒否
            return response()->json([
              'message' => 'チャットへのアクセス権限を確認できませんでした。',
              'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
          }
        }
      }
    }

      Log::info('メッセージクエリ開始');
      
      $messages = $chatRoom->messages()
        ->whereNull('admin_deleted_at') // 管理者によって削除されていないメッセージのみ
        ->with(['sender' => function ($query) {
          $query->select('id', 'name', 'friend_id'); // 送信者の基本情報を選択
        }, 'adminSender' => function ($query) {
          $query->select('id', 'name'); // 管理者送信者の基本情報を選択
        }, 'messageReads' => function ($query) {
          $query->select('message_id', 'user_id', 'read_at'); // 既読情報を選択
        }])
        ->orderBy('sent_at', 'desc') // 最新のメッセージから表示
        ->paginate(20); // ページネーション
      
      Log::info('メッセージクエリ完了', ['messages_count' => $messages->count()]);

    // グループチャットの場合、各メッセージ送信者の退室状態を付加
    if ($chatRoom->isGroupChat() && $chatRoom->group) {
      try {
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
      } catch (\Exception $e) {
        Log::error('グループメンバー状態の取得でエラー', [
          'chat_room_id' => $chatRoom->id,
          'error' => $e->getMessage()
        ]);
        // エラーが発生しても処理を継続
      }
    }

      Log::info('既読状態処理開始');
      // 各メッセージに既読状態を追加
      foreach ($messages as $message) {
        try {
          // 1対1チャットの場合（friend_chat, support_chat, member_chat）
          if ($chatRoom->type === 'friend_chat' || $chatRoom->type === 'support_chat' || $chatRoom->type === 'member_chat') {
            // 自分が送信したメッセージの場合、相手が既読したかチェック
            if ($message->sender_id === $user->id) {
              $message->is_read = $message->isReadByOtherParticipant($user->id);
            } else {
              // 相手が送信したメッセージの場合、自分が既読したかチェック（通常は不要だが念のため）
              $message->is_read = $message->isReadByUser($user->id);
            }
          }
          // グループチャットの場合
          else if ($chatRoom->isGroupChat()) {
            $message->read_count = $message->getReadCount();
            // 既読したユーザーリストも含める場合（オプション）
            // $message->read_by = $message->getReadUsersList();
          }
        } catch (\Exception $e) {
          Log::error('既読状態処理でエラー', [
            'message_id' => $message->id,
            'error' => $e->getMessage()
          ]);
          // エラーが発生してもデフォルト値を設定して継続
          $message->is_read = false;
          if ($chatRoom->isGroupChat()) {
            $message->read_count = 0;
          }
        }
      }
      Log::info('既読状態処理完了');

      // メッセージを取得後、このチャットルームを既読にする
      if ($messages->isNotEmpty()) {
        try {
          Log::info('既読更新処理開始');
          // チャットルーム単位の既読更新（既存処理）
          ChatRoomRead::updateLastRead($user->id, $chatRoom->id);

          // 個別メッセージの既読記録（新規処理）
          $unreadMessageIds = $messages->filter(function ($message) use ($user) {
            // 自分が送信したメッセージと管理者メッセージは除外
            return $message->sender_id !== $user->id
              && !$message->isReadByUser($user->id);
          })->pluck('id')->toArray();

          if (!empty($unreadMessageIds)) {
            MessageRead::markMultipleAsRead($unreadMessageIds, $user->id);
          }
          Log::info('既読更新処理完了');
        } catch (\Exception $e) {
          Log::error('既読更新処理でエラー', [
            'chat_room_id' => $chatRoom->id,
            'user_id' => $user->id,
            'error' => $e->getMessage()
          ]);
          // 既読更新のエラーは処理を停止させない
        }
      }

      Log::info('メッセージ一覧取得成功', [
        'chat_room_id' => $chatRoom->id,
        'user_id' => $user->id,
        'messages_count' => $messages->count(),
        'total' => $messages->total()
      ]);
      return response()->json($messages);
    } catch (\Exception $e) {
      Log::error('MessagesController::index エラー', [
        'chat_room_id' => $chatRoom->id,
        'user_id' => isset($user) ? $user->id : null,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
      ]);

      return response()->json([
        'message' => 'メッセージの取得に失敗しました。',
        'error' => config('app.debug') ? $e->getMessage() : null
      ], 500);
    }
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
            try {
              // 友達関係を確認
              $currentFriends = $user->friends()->pluck('id')->toArray();
              if (!in_array($otherUserId, $currentFriends)) {
                return response()->json([
                  'message' => '友達関係が解除されたため、このチャットにメッセージを送信できません。',
                  'friendship_status' => 'unfriended'
                ], 403);
              }
            } catch (\Exception $e) {
              Log::error('友達関係の確認でエラー（送信時）', [
                'user_id' => $user->id,
                'other_user_id' => $otherUserId,
                'error' => $e->getMessage()
              ]);
              // エラーが発生した場合は送信を拒否
              return response()->json([
                'message' => 'メッセージの送信権限を確認できませんでした。',
                'error' => config('app.debug') ? $e->getMessage() : null
              ], 500);
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

      // メッセージ作成
      $messageData = [
        'chat_room_id' => $chatRoom->id,
        'sender_id' => $user->id,
        'text_content' => $request->input('text_content'),
        'content_type' => 'text', // MVPではtext固定
        'sent_at' => now(),
      ];

      $message = $chatRoom->messages()->create($messageData);

      // チャットルームの更新日時を更新して、最近のチャットルーム一覧で正しい順序で表示されるようにする
      $chatRoom->touch();

      Log::info('メッセージ作成完了', ['message_id' => $message->id]);

      $message->load(['sender' => function ($query) {
        $query->select('id', 'name', 'friend_id');
      }, 'adminSender' => function ($query) {
        $query->select('id', 'name');
      }]);

      // グループチャットの場合、送信者の退室状態を付加
      if ($chatRoom->isGroupChat() && $chatRoom->group) {
        try {
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
        } catch (\Exception $e) {
          Log::warning('送信者の退室状態取得でエラー', [
            'chat_room_id' => $chatRoom->id,
            'message_id' => $message->id,
            'error' => $e->getMessage()
          ]);
          // エラーが発生しても処理を継続
          $message->sender_has_left = false;
          $message->sender_left_at = null;
        }
      }

      Log::info('メッセージリレーション読み込み完了');

      // プッシュ通知の送信（非同期）
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

        $frontendUrl = config('app.frontend_url', 'https://chat-app-frontend-sigma-puce.vercel.app');

        Log::info('非同期通知送信開始');

        foreach ($participants as $participantUserId) {
          // 各参加者に通知を送信（非同期）
          $participantUser = User::find($participantUserId);
          if ($participantUser && !$participantUser->isDeleted()) {
            try {
              $chatUrl = $frontendUrl . '/chat?room=' . $chatRoom->room_token;
              
              // ユーザーの通知設定を取得
              $notificationPrefs = $participantUser->notification_preferences ?? [
                'email' => ['messages' => true, 'group_messages' => true],
                'push' => ['messages' => true, 'group_messages' => true],
              ];
              
              // チャットタイプに応じて通知設定を確認
              $isGroupChat = in_array($chatRoom->type, ['group_chat', 'member_chat']);
              $shouldSendEmail = $isGroupChat 
                ? ($notificationPrefs['email']['group_messages'] ?? true)
                : ($notificationPrefs['email']['messages'] ?? true);
              $shouldSendPush = $isGroupChat
                ? ($notificationPrefs['push']['group_messages'] ?? true)
                : ($notificationPrefs['push']['messages'] ?? true);
              
              // プッシュ通知を送信（設定が有効な場合）
              if ($shouldSendPush) {
                $participantUser->notify(new \App\Notifications\PushNotification(
                  $user->name . 'からのメッセージ',
                  $messagePreview,
                  [
                    'url' => $frontendUrl . '/chat',
                    'type' => 'new_message',
                    'room_id' => $chatRoom->id,
                    'room_token' => $chatRoom->room_token,
                    'timestamp' => now()->timestamp
                  ],
                  [
                    'tag' => 'chat-' . $chatRoom->id,
                    'requireInteraction' => true
                  ]
                ));
              }

              // メール通知も送信（設定が有効な場合）
              if ($shouldSendEmail) {
                $participantUser->notify(new NewMessageNotification(
                  $user->name,
                  $messagePreview,
                  $chatUrl
                ));
              }
              
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

        Log::info('非同期通知キューへの追加完了');
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
   * メッセージの既読状態を取得する（軽量版）
   */
  public function getReadStatus(ChatRoom $chatRoom, Request $request)
  {
    $user = Auth::user();

    // アクセス権限チェック
    if (!$chatRoom->hasParticipant($user->id)) {
      return response()->json(['message' => 'アクセス権がありません。'], 403);
    }

    // 自分が送信したメッセージのIDを取得
    $messageIds = $chatRoom->messages()
      ->where('sender_id', $user->id)
      ->whereNull('admin_deleted_at')
      ->pluck('id');

    $readStatuses = [];

    foreach ($messageIds as $messageId) {
      $message = Message::find($messageId);
      if (!$message) continue;

      $readStatus = [
        'id' => $messageId,
      ];

      // チャットタイプによって既読情報を変更
      if ($chatRoom->type === 'friend_chat' || $chatRoom->type === 'support_chat' || $chatRoom->type === 'member_chat') {
        // 1対1チャットの場合
        $readStatus['is_read'] = $message->isReadByOtherParticipant($user->id);
      } else if ($chatRoom->isGroupChat()) {
        // グループチャットの場合
        $readStatus['read_count'] = $message->getReadCount();
      }

      $readStatuses[] = $readStatus;
    }

    return response()->json($readStatuses);
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
