<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConversationsController extends Controller
{
  /**
   * ユーザーが参加している会話の一覧を取得する
   */
  public function index(Request $request)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // 現在の友達IDリストを取得
    $friendIds = $user->friends()->pluck('id')->toArray();

    $conversations = $user->conversations()
      ->with(['participants' => function ($query) use ($user) {
        // 自分以外の参加者情報を取得（削除されていないユーザーのみ）
        $query->where('users.id', '!=', $user->id)
          ->whereNull('users.deleted_at')
          ->select('users.id', 'users.name', 'users.friend_id');
      }, 'latestMessage' => function ($query) {
        $query->with(['sender' => function ($senderQuery) {
          $senderQuery->select('id', 'name');
        }, 'adminSender' => function ($adminQuery) {
          $adminQuery->select('id', 'name');
        }]);
      }])
      ->select('conversations.*') // Ensure all conversation fields, including room_token, are selected
      ->whereNull('conversations.deleted_at') // 削除されていない会話のみ
      ->where(function ($query) use ($user, $friendIds) {
        // サポート会話は常に表示
        $query->where('type', 'support')
          // ダイレクトメッセージの場合は、友達関係が続いている場合のみ表示
          ->orWhere('type', '!=', 'direct') // グループチャットは常に表示
          ->orWhere(function ($query) use ($user, $friendIds) {
            // ダイレクトメッセージで、かつ相手が友達である場合
            $query->where('type', 'direct')
              ->whereHas('participants', function ($query) use ($user, $friendIds) {
                $query->where('users.id', '!=', $user->id)
                  ->whereIn('users.id', $friendIds)
                  ->whereNull('users.deleted_at'); // 削除されていないユーザーのみ
              });
          });
      })
      ->withCount([
        'messages as unread_messages_count' => function ($query) use ($user) {
          // $query は Message モデルに対するクエリビルダ
          $query->where('sender_id', '!=', $user->id) // まず、自分が送信したメッセージは除外
            ->whereNull('admin_deleted_at') // 管理者によって削除されていないメッセージのみ
            ->where(function ($subQuery) use ($user) {
              // ログインユーザーの Participant 情報を取得し、条件に合うメッセージをカウント
              $subQuery->whereHas('conversation.conversationParticipants', function ($participantQuery) use ($user) {
                $participantQuery->where('user_id', $user->id)
                  ->where(function ($q) {
                    // last_read_at IS NULL OR messages.sent_at > participants.last_read_at
                    $q->whereNull('participants.last_read_at')
                      ->orWhereColumn('messages.sent_at', '>', 'participants.last_read_at');
                  });
              });
              // もし、一度も会話を開いておらず last_read_at が NULL の参加者レコードがない場合も考慮するなら、
              // sender_id != user.id のメッセージは全て未読とする必要があるかもしれない。
              // しかし、通常は会話参加時に participants レコードが作成され last_read_at が NULL で始まるため、
              // 上記の whereHas 内の whereNull('participants.last_read_at') でカバーされる想定。
            });
        }
      ])
      ->orderByDesc(
        // 最新メッセージのsent_atでソート、削除されたメッセージは除外
        DB::raw('(SELECT MAX(sent_at) FROM messages WHERE messages.conversation_id = conversations.id AND deleted_at IS NULL AND admin_deleted_at IS NULL)')
      )
      ->paginate(15);

    return response()->json($conversations);
  }

  /**
   * 新しい会話を開始する (1対1のダイレクトメッセージ)
   */
  public function store(Request $request)
  {
    $request->validate([
      'recipient_id' => [
        'required',
        'integer',
        Rule::exists('users', 'id')->where(function ($query) {
          return $query->where('id', '!=', Auth::id()); // 自分自身は指定不可
        }),
      ],
    ]);

    $currentUser = Auth::user();
    $recipientId = $request->input('recipient_id');
    $recipient = User::find($recipientId);

    if (!$recipient) {
      return response()->json(['message' => '指定された受信ユーザーが見つかりません。'], 404);
    }

    // 友達関係をチェック (双方が承認済みであること)
    $friendship = Friendship::getFriendship($currentUser->id, $recipientId);
    if (!$friendship || $friendship->status !== Friendship::STATUS_ACCEPTED) {
      return response()->json(['message' => '友達関係にないユーザーとは会話を開始できません。'], 403);
    }

    // 既存のダイレクト会話を検索
    $existingConversationQuery = $currentUser->conversations()
      ->where('type', 'direct')
      ->whereHas('participants', function ($query) use ($recipientId) {
        $query->where('user_id', $recipientId);
      });

    $existingConversation = $existingConversationQuery->with([
      'participants' => function ($query) use ($currentUser) {
        $query->where('users.id', '!=', $currentUser->id)->select('users.id', 'users.name', 'users.friend_id');
      },
      'latestMessage' => function ($query) {
        $query->with(['sender' => function ($senderQuery) {
          $senderQuery->select('id', 'name');
        }, 'adminSender' => function ($adminQuery) {
          $adminQuery->select('id', 'name');
        }]);
      }
    ])->first();

    if ($existingConversation) {
      // 既存の会話があればそれを返す
      // $existingConversation->load(...) は不要になる
      return response()->json($existingConversation, 200);
    }

    // 新しい会話を作成
    $conversation = null;
    DB::transaction(function () use ($currentUser, $recipient, &$conversation) {
      $newConversation = Conversation::create([
        'type' => 'direct',
        // room_token は Conversation モデルの creating イベントで自動生成される
      ]);

      // 参加者を追加
      $newConversation->conversationParticipants()->createMany([
        ['user_id' => $currentUser->id],
        ['user_id' => $recipient->id],
      ]);
      $conversation = $newConversation;
    });

    if ($conversation) {
      $conversation = $conversation->fresh([
        'participants' => function ($query) use ($currentUser) {
          $query->where('users.id', '!=', $currentUser->id)->select('users.id', 'users.name', 'users.friend_id');
        },
        'latestMessage' => function ($query) {
          $query->with(['sender' => function ($senderQuery) {
            $senderQuery->select('id', 'name');
          }, 'adminSender' => function ($adminQuery) {
            $adminQuery->select('id', 'name');
          }]);
        }
      ]);
      // 明示的にroom_tokenをロードしなくても、fresh()がモデルインスタンスを再取得するため
      // booted()で設定されたroom_tokenは含まれるはずですが、念のため確認。
      // 通常、Conversation::create() 後、モデルインスタンスにはidとbooted()で設定された値が含まれる。
      // fresh()はDBから最新の状態を読み込むため、room_tokenも含まれる。
      return response()->json($conversation, 201);
    }

    return response()->json(['message' => '会話の作成に失敗しました。'], 500);
  }

  /**
   * 特定の会話情報を取得 (オプション)
   */
  public function show(Conversation $conversation, Request $request)
  {
    $user = Auth::user();
    // ユーザーがこの会話の参加者であることを確認
    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
      return response()->json(['message' => 'アクセス権がありません。'], 403);
    }

    $conversation->load(['participants' => function ($query) use ($user) {
      $query->where('users.id', '!=', $user->id)->select('users.id', 'users.name', 'users.friend_id');
    }, 'latestMessage' => function ($query) {
      $query->with(['sender' => function ($senderQuery) {
        $senderQuery->select('id', 'name');
      }, 'adminSender' => function ($adminQuery) {
        $adminQuery->select('id', 'name');
      }]);
    }]);

    return response()->json($conversation);
  }

  /**
   * 会話を既読にする
   */
  public function markAsRead(Conversation $conversation)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // ユーザーがこの会話の参加者であることを確認
    $participant = $conversation->conversationParticipants()
      ->where('user_id', $user->id)
      ->first();

    if (!$participant) {
      return response()->json(['message' => 'この会話の参加者ではありません。'], 403);
    }

    // 最新メッセージを取得
    $latestMessage = $conversation->messages()
      ->whereNull('deleted_at')
      ->whereNull('admin_deleted_at')
      ->latest('sent_at')
      ->first();

    if ($latestMessage) {
      $participant->update([
        'last_read_message_id' => $latestMessage->id,
        'last_read_at' => now(),
      ]);
    }

    return response()->json(['message' => '既読にしました。']);
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

  /**
   * room_token を使用して特定の会話情報を取得
   */
  public function showByToken(Request $request, string $room_token)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // まず削除状態に関係なく会話を検索
    $conversation = Conversation::where('room_token', $room_token)->first();

    if (!$conversation) {
      return response()->json(['message' => '会話が見つかりません。'], 404);
    }

    // 削除された会話の場合は403エラーを返す
    if ($conversation->isDeleted()) {
      return response()->json(['message' => 'この会話は削除されています。'], 403);
    }

    // ユーザーがこの会話の参加者であることを確認
    if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
      return response()->json(['message' => 'アクセス権がありません。'], 403);
    }

    // ダイレクトメッセージの場合、友達関係を確認
    if ($conversation->type === 'direct') {
      // 会話の相手を取得（削除されていないユーザーのみ）
      $otherParticipant = $conversation->participants()
        ->where('users.id', '!=', $user->id)
        ->whereNull('users.deleted_at')
        ->first();

      if ($otherParticipant) {
        // 友達関係を確認
        $currentFriends = $user->friends()->pluck('id')->toArray();
        if (!in_array($otherParticipant->id, $currentFriends)) {
          return response()->json([
            'message' => '友達関係が解除されたため、このチャットにアクセスできません。',
            'friendship_status' => 'unfriended'
          ], 403);
        }
      } else {
        // 相手が削除されている場合
        return response()->json([
          'message' => '相手のアカウントが削除されたため、このチャットにアクセスできません。',
          'friendship_status' => 'user_deleted'
        ], 403);
      }
    }

    // 既存の show メソッドと同様の情報をロード
    $conversation->load([
      'participants' => function ($query) use ($user) {
        // 自分以外の参加者情報を取得（削除されていないユーザーのみ）
        $query->where('users.id', '!=', $user->id)
          ->whereNull('users.deleted_at')
          ->select('users.id', 'users.name', 'users.friend_id');
      },
      'latestMessage' => function ($query) {
        $query->with(['sender' => function ($senderQuery) {
          $senderQuery->select('id', 'name');
        }, 'adminSender' => function ($adminQuery) {
          $adminQuery->select('id', 'name');
        }]);
      },
      // 必要であれば、メッセージもここでページネーションしてロードすることも検討できます
      // 'messages' => function ($query) {
      //   $query->orderBy('sent_at', 'desc')->paginate(15); // 例: 最新15件
      // }
    ]);

    return response()->json($conversation);
  }

  /**
   * サポート会話を作成または取得する
   */
  public function createSupportConversation(Request $request)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // 既存のサポート会話を検索
    $existingConversation = $user->conversations()
      ->where('type', 'support')
      ->whereNull('deleted_at')
      ->with([
        'participants' => function ($query) use ($user) {
          $query->where('users.id', '!=', $user->id)->select('users.id', 'users.name', 'users.friend_id');
        },
        'latestMessage' => function ($query) {
          $query->with(['sender' => function ($senderQuery) {
            $senderQuery->select('id', 'name');
          }, 'adminSender' => function ($adminQuery) {
            $adminQuery->select('id', 'name');
          }]);
        }
      ])
      ->first();

    if ($existingConversation) {
      return response()->json($existingConversation, 200);
    }

    // 新しいサポート会話を作成
    $conversation = null;
    DB::transaction(function () use ($user, &$conversation) {
      $newConversation = Conversation::create([
        'type' => 'support',
      ]);

      // ユーザーを参加者として追加
      $newConversation->conversationParticipants()->create([
        'user_id' => $user->id,
      ]);

      $conversation = $newConversation;
    });

    if ($conversation) {
      $conversation = $conversation->fresh([
        'participants' => function ($query) use ($user) {
          $query->where('users.id', '!=', $user->id)->select('users.id', 'users.name', 'users.friend_id');
        },
        'latestMessage' => function ($query) {
          $query->with(['sender' => function ($senderQuery) {
            $senderQuery->select('id', 'name');
          }, 'adminSender' => function ($adminQuery) {
            $adminQuery->select('id', 'name');
          }]);
        }
      ]);

      return response()->json($conversation, 201);
    }

    return response()->json(['message' => 'サポート会話の作成に失敗しました。'], 500);
  }

  /**
   * ユーザーのサポート会話を取得する
   */
  public function getSupportConversation(Request $request)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    $conversation = $user->conversations()
      ->where('type', 'support')
      ->whereNull('deleted_at')
      ->with([
        'participants' => function ($query) use ($user) {
          $query->where('users.id', '!=', $user->id)->select('users.id', 'users.name', 'users.friend_id');
        },
        'latestMessage' => function ($query) {
          $query->with(['sender' => function ($senderQuery) {
            $senderQuery->select('id', 'name');
          }, 'adminSender' => function ($adminQuery) {
            $adminQuery->select('id', 'name');
          }]);
        }
      ])
      ->first();

    if (!$conversation) {
      return response()->json(['message' => 'サポート会話が見つかりません。'], 404);
    }

    return response()->json($conversation, 200);
  }

  /**
   * 新しいグループ会話を作成
   */
  public function createGroup(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:100',
      'description' => 'nullable|string',
      'max_members' => 'nullable|integer|min:1|max:500',
    ]);

    $user = Auth::user();

    $conversation = DB::transaction(function () use ($user, $request) {
      // グループ会話を作成
      $conversation = Conversation::create([
        'type' => 'group',
        'name' => $request->name,
        'description' => $request->description,
        'max_members' => $request->max_members ?? 50,
        'owner_user_id' => $user->id,
      ]);

      // オーナーを参加者として追加
      Participant::create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'joined_at' => now(),
      ]);

      return $conversation;
    });

    return response()->json($conversation, 201);
  }

  /**
   * ユーザーのグループ会話一覧を取得
   */
  public function getGroups()
  {
    $user = Auth::user();
    $groups = $user->ownedGroupConversations()
      ->withCount('conversationParticipants as member_count')
      ->get();

    return response()->json($groups);
  }

  /**
   * グループ会話の詳細を取得
   */
  public function showGroup(Conversation $conversation)
  {
    $user = Auth::user();

    // グループタイプかチェック
    if (!$conversation->isGroup()) {
      return response()->json(['message' => 'グループ会話ではありません'], 400);
    }

    // オーナーかチェック
    if ($conversation->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $conversation->load('conversationParticipants.user');
    return response()->json($conversation);
  }

  /**
   * グループ会話を更新
   */
  public function updateGroup(Conversation $conversation, Request $request)
  {
    $request->validate([
      'name' => 'sometimes|string|max:100',
      'description' => 'nullable|string',
      'max_members' => 'nullable|integer|min:1|max:500',
    ]);

    $user = Auth::user();

    if (!$conversation->isGroup() || $conversation->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $conversation->update($request->only('name', 'description', 'max_members'));
    return response()->json($conversation);
  }

  /**
   * グループ会話を削除
   */
  public function destroyGroup(Conversation $conversation)
  {
    $user = Auth::user();

    if (!$conversation->isGroup() || $conversation->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $conversation->delete();
    return response()->json(['message' => 'グループが削除されました']);
  }

  /**
   * グループにメンバーを追加
   */
  public function addGroupMember(Conversation $conversation, Request $request)
  {
    $request->validate([
      'user_id' => 'required|exists:users,id',
    ]);

    $user = Auth::user();

    if (!$conversation->isGroup() || $conversation->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    // メンバー数制限チェック
    if (!$conversation->canAddMember()) {
      return response()->json(['message' => 'メンバー数が上限に達しています'], 422);
    }

    // 既に参加しているかチェック
    if ($conversation->conversationParticipants()->where('user_id', $request->user_id)->exists()) {
      return response()->json(['message' => '既に参加しています'], 422);
    }

    $participant = Participant::create([
      'conversation_id' => $conversation->id,
      'user_id' => $request->user_id,
      'joined_at' => now(),
    ]);

    return response()->json($participant, 201);
  }

  /**
   * グループからメンバーを削除
   */
  public function removeGroupMember(Conversation $conversation, Participant $participant)
  {
    $user = Auth::user();

    if (!$conversation->isGroup() || $conversation->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    if ($participant->conversation_id !== $conversation->id) {
      return response()->json(['message' => '無効なメンバーです'], 422);
    }

    $participant->delete();
    return response()->json(['message' => 'メンバーが削除されました']);
  }

  /**
   * QRコードトークンを取得
   */
  public function getGroupQrCode(Conversation $conversation)
  {
    $user = Auth::user();

    if (!$conversation->isGroup() || $conversation->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    return response()->json(['qr_code_token' => $conversation->qr_code_token]);
  }

  /**
   * QRコードトークンを再生成
   */
  public function regenerateGroupQrCode(Conversation $conversation)
  {
    $user = Auth::user();

    if (!$conversation->isGroup() || $conversation->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $conversation->regenerateQrToken();
    return response()->json(['qr_code_token' => $conversation->qr_code_token]);
  }

  /**
   * QRコードトークンでグループに参加
   */
  public function joinGroupByToken(Request $request, string $token)
  {
    $request->validate([
      // ログイン必須なのでニックネームは不要
    ]);

    $conversation = Conversation::where('qr_code_token', $token)
      ->where('type', 'group')
      ->firstOrFail();

    $user = Auth::user();

    // メンバー数制限チェック
    if (!$conversation->canAddMember()) {
      return response()->json(['message' => 'グループのメンバー数が上限に達しています'], 422);
    }

    // 既に参加しているかチェック
    if ($conversation->conversationParticipants()->where('user_id', $user->id)->exists()) {
      return response()->json(['message' => '既に参加しています'], 422);
    }

    $participant = Participant::create([
      'conversation_id' => $conversation->id,
      'user_id' => $user->id,
      'joined_at' => now(),
    ]);

    return response()->json($participant, 201);
  }
}
