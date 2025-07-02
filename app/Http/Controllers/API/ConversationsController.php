<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use App\Models\Group;
use App\Models\ChatRoom;
use App\Models\GroupMember;
use App\Services\ChatRoomService;
use App\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConversationsController extends Controller
{
  /**
   * ユーザーが参加しているチャットの一覧を取得する
   */
  public function index(Request $request, ChatRoomService $service)
  {
    $user = Auth::user();
    if ($user->isDeleted()) {
      return response()->json(["message" => "アカウントが削除されています。"], 403);
    }
    $page = (int) $request->get("page", 1);
    $perPage = 15;
    $result = $service->getUserChatRoomsList($user, $page, $perPage);
    return response()->json($result);
  }


  /**
   * 新しいチャットを開始する (1対1のダイレクトメッセージ)
   */
  public function store(Request $request, ChatRoomService $service)
  {
    $request->validate([
      'recipient_id' => [
        'required',
        'integer',
        Rule::exists('users', 'id')->where(function ($query) {
          return $query->where('id', '!=', Auth::id());
        }),
      ],
    ]);
    $currentUser = Auth::user();
    $result = $service->createFriendChatRoom($currentUser, $request->input('recipient_id'));

    if (($result['status'] ?? null) === BaseService::STATUS_ERROR) {
      return response()->json(
        ['message' => $result['message']],
        $result['http_status'] ?? 500
      );
    }

    return response()->json($result['data'], $result['http_status']);
  }


  /**
   * 特定のチャット情報を取得 (オプション)
   */
  public function show(Conversation $conversation, Request $request)
  {
    $user = Auth::user();
    // ユーザーがこのチャットの参加者であることを確認
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
   * チャットを既読にする
   */
  public function markAsRead(Conversation $conversation)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // ユーザーがこのチャットの参加者であることを確認
    $participant = $conversation->conversationParticipants()
      ->where('user_id', $user->id)
      ->first();

    if (!$participant) {
      return response()->json(['message' => 'このチャットの参加者ではありません。'], 403);
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
   * チャットルームを既読にする（新構造）
   */
  public function markAsReadByRoomId(ChatRoom $chatRoom)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // ユーザーがこのチャットルームの参加者であることを確認
    if (!$chatRoom->hasParticipant($user->id)) {
      return response()->json(['message' => 'このチャットルームの参加者ではありません。'], 403);
    }

    // 既読管理テーブルを更新
    \App\Models\ChatRoomRead::updateLastRead($user->id, $chatRoom->id);

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
   * room_token を使用して特定のチャットルーム情報を取得
   */
  public function showByToken(Request $request, string $room_token)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // チャットルームを検索
    $chatRoom = ChatRoom::where('room_token', $room_token)->first();

    if (!$chatRoom) {
      return response()->json(['message' => 'チャットルームが見つかりません。'], 404);
    }

    // ユーザーがこのチャットルームの参加者であることを確認
    if (!$chatRoom->hasParticipant($user->id)) {
      return response()->json(['message' => 'アクセス権がありません。'], 403);
    }

    // friend_chatの場合、相手ユーザーが削除・バンされていないかを確認
    if ($chatRoom->type === 'friend_chat') {
      $otherUserId = $chatRoom->participant1_id === $user->id
        ? $chatRoom->participant2_id
        : $chatRoom->participant1_id;

      $otherUser = User::find($otherUserId);

      if (!$otherUser || $otherUser->isDeleted() || $otherUser->is_banned) {
        return response()->json([
          'message' => '相手のアカウントが削除またはバンされたため、このチャットにアクセスできません。',
          'user_status' => 'deleted_or_banned'
        ], 403);
      }

      // 友達関係を確認
      $friendship = Friendship::getFriendship($user->id, $otherUserId);
      if (!$friendship || $friendship->status !== Friendship::STATUS_ACCEPTED) {
        return response()->json([
          'message' => '友達関係が解除されたため、このチャットにアクセスできません。',
          'friendship_status' => 'unfriended'
        ], 403);
      }
    }

    // メンバーチャットの場合、グループメンバーシップまたは友達関係を確認
    if ($chatRoom->type === 'member_chat') {
      // 相手ユーザーを特定
      $otherUserId = $chatRoom->participant1_id === $user->id
        ? $chatRoom->participant2_id
        : $chatRoom->participant1_id;

      $otherUser = User::find($otherUserId);

      if (!$otherUser || $otherUser->isDeleted() || $otherUser->is_banned) {
        return response()->json([
          'message' => '相手のアカウントが削除またはバンされたため、このチャットにアクセスできません。',
          'user_status' => 'deleted_or_banned'
        ], 403);
      }

      // グループに関連するメンバーチャットの場合、グループメンバーシップを確認
      if ($chatRoom->group_id) {
        $group = Group::find($chatRoom->group_id);
        if (!$group) {
          return response()->json([
            'message' => 'グループが存在しないため、このチャットにアクセスできません。',
            'group_status' => 'not_found'
          ], 403);
        }

        // 両方のユーザーがグループメンバーであることを確認
        if (!$group->hasMember($user->id) || !$group->hasMember($otherUserId)) {
          return response()->json([
            'message' => 'グループメンバー関係が解除されたため、このチャットにアクセスできません。',
            'membership_status' => 'not_member'
          ], 403);
        }
      } else {
        // グループに関連しないメンバーチャットの場合、友達関係を確認
        $friendship = Friendship::getFriendship($user->id, $otherUserId);
        if (!$friendship || $friendship->status !== Friendship::STATUS_ACCEPTED) {
          return response()->json([
            'message' => '友達関係が解除されたため、このチャットにアクセスできません。',
            'friendship_status' => 'unfriended'
          ], 403);
        }
      }
    }

    // チャットルーム情報をロード
    $chatRoom->load([
      'participant1' => function ($query) {
        $query->select('id', 'name', 'friend_id', 'deleted_at', 'is_banned');
      },
      'participant2' => function ($query) {
        $query->select('id', 'name', 'friend_id', 'deleted_at', 'is_banned');
      },
      'group' => function ($query) {
        $query->select('id', 'name', 'owner_user_id');
      }
    ]);

    // 相手の参加者情報を取得
    $otherParticipant = $chatRoom->participant1_id === $user->id
      ? $chatRoom->participant2
      : $chatRoom->participant1;

    // レスポンス用データを準備
    $responseData = [
      'id' => $chatRoom->id,
      'room_token' => $chatRoom->room_token,
      'type' => $chatRoom->type,
      'group_id' => $chatRoom->group_id,
      'participant1_id' => $chatRoom->participant1_id,
      'participant2_id' => $chatRoom->participant2_id,
      'created_at' => $chatRoom->created_at,
      'updated_at' => $chatRoom->updated_at,
      'other_participant' => $otherParticipant ? [
        'id' => $otherParticipant->id,
        'name' => $otherParticipant->name,
        'friend_id' => $otherParticipant->friend_id,
      ] : null,
      'group' => $chatRoom->group ? [
        'id' => $chatRoom->group->id,
        'name' => $chatRoom->group->name,
      ] : null,
    ];

    // チャットタイプに応じて追加情報を設定
    if ($chatRoom->type === 'group_chat' && $chatRoom->group) {
      // グループチャットの場合は参加者数とオーナー情報を追加
      $participantCount = $chatRoom->group->getMembersCount();
      $groupOwner = User::find($chatRoom->group->owner_user_id);

      $responseData['name'] = $chatRoom->group->name;
      $responseData['group_name'] = $chatRoom->group->name;
      $responseData['participant_count'] = $participantCount;
      $responseData['group_owner'] = $groupOwner ? [
        'id' => $groupOwner->id,
        'name' => $groupOwner->name,
        'friend_id' => $groupOwner->friend_id,
      ] : null;
      $responseData['participants'] = [
        [
          'id' => $chatRoom->group->id,
          'name' => $chatRoom->group->name,
          'friend_id' => null,
        ]
      ];
    } elseif ($chatRoom->type === 'member_chat' && $chatRoom->group) {
      // メンバーチャットの場合はグループ情報とオーナー情報を追加
      $groupOwner = User::find($chatRoom->group->owner_user_id);

      $responseData['name'] = $chatRoom->group->name;
      $responseData['group_name'] = $chatRoom->group->name;
      $responseData['group_owner'] = $groupOwner ? [
        'id' => $groupOwner->id,
        'name' => $groupOwner->name,
        'friend_id' => $groupOwner->friend_id,
      ] : null;
      $responseData['participants'] = $otherParticipant ? [
        [
          'id' => $otherParticipant->id,
          'name' => $otherParticipant->name,
          'friend_id' => $otherParticipant->friend_id,
        ]
      ] : [];
    } elseif ($chatRoom->type === 'support_chat') {
      $responseData['name'] = 'サポート';
      $responseData['participants'] = [
        [
          'id' => 0,
          'name' => 'サポート',
          'friend_id' => null,
        ]
      ];
    } else {
      // friend_chatの場合
      $responseData['participants'] = $otherParticipant ? [
        [
          'id' => $otherParticipant->id,
          'name' => $otherParticipant->name,
          'friend_id' => $otherParticipant->friend_id,
        ]
      ] : [];
    }

    return response()->json($responseData);
  }

  /**
   * サポートチャットを作成または取得する
   */
  public function createSupportConversation(Request $request)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // 既存のサポートチャットルームを検索
    $existingChatRoom = ChatRoom::where('type', 'support_chat')
      ->where('participant1_id', $user->id)
      ->with([
        'participant1' => function ($query) {
          $query->select('id', 'name', 'friend_id');
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

    if ($existingChatRoom) {
      return response()->json([
        'id' => $existingChatRoom->id,
        'type' => $existingChatRoom->type,
        'room_token' => $existingChatRoom->room_token,
        'participant1_id' => $existingChatRoom->participant1_id,
        'participant2_id' => null, // サポートチャットは1対1ではない
        'participants' => [
          [
            'id' => 0,
            'name' => 'サポート',
            'friend_id' => null,
          ]
        ],
        'latest_message' => $existingChatRoom->latestMessage,
        'unread_messages_count' => \App\Models\ChatRoomRead::getUnreadCount($user->id, $existingChatRoom->id),
        'created_at' => $existingChatRoom->created_at,
        'updated_at' => $existingChatRoom->updated_at,
      ], 200);
    }

    // 新しいサポートチャットルームを作成
    $chatRoom = null;
    DB::transaction(function () use ($user, &$chatRoom) {
      $newChatRoom = ChatRoom::create([
        'type' => 'support_chat',
        'participant1_id' => $user->id,
        'participant2_id' => null, // サポートチャットは管理者が後から参加
      ]);

      // 新アーキテクチャでは participant1_id で参加者を管理するため
      // 別途 Participant テーブルへの追加は不要

      $chatRoom = $newChatRoom;
    });

    if ($chatRoom) {
      return response()->json([
        'id' => $chatRoom->id,
        'type' => $chatRoom->type,
        'room_token' => $chatRoom->room_token,
        'participant1_id' => $chatRoom->participant1_id,
        'participant2_id' => null,
        'participants' => [
          [
            'id' => 0,
            'name' => 'サポート',
            'friend_id' => null,
          ]
        ],
        'latest_message' => null,
        'unread_messages_count' => 0,
        'created_at' => $chatRoom->created_at,
        'updated_at' => $chatRoom->updated_at,
      ], 201);
    }

    return response()->json(['message' => 'サポートチャットの作成に失敗しました。'], 500);
  }

  /**
   * ユーザーのサポートチャットを取得する
   */
  public function getSupportConversation(Request $request)
  {
    $user = Auth::user();

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    $chatRoom = ChatRoom::where('type', 'support_chat')
      ->where('participant1_id', $user->id)
      ->with([
        'participant1' => function ($query) {
          $query->select('id', 'name', 'friend_id');
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

    if (!$chatRoom) {
      return response()->json(['message' => 'サポートチャットが見つかりません。'], 404);
    }

    return response()->json([
      'id' => $chatRoom->id,
      'type' => $chatRoom->type,
      'room_token' => $chatRoom->room_token,
      'participant1_id' => $chatRoom->participant1_id,
      'participant2_id' => null,
      'participants' => [
        [
          'id' => 0,
          'name' => 'サポート',
          'friend_id' => null,
        ]
      ],
      'latest_message' => $chatRoom->latestMessage,
      'unread_messages_count' => \App\Models\ChatRoomRead::getUnreadCount($user->id, $chatRoom->id),
      'created_at' => $chatRoom->created_at,
      'updated_at' => $chatRoom->updated_at,
    ], 200);
  }

  /**
   * 新しいグループチャットを作成
   */
  public function createGroup(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:100',
      'description' => 'nullable|string',
      'max_members' => 'nullable|integer|min:1|max:500',
      'chatStyles' => 'required|array|min:1',
      'chatStyles.*' => 'in:group,group_member',
    ]);

    $user = Auth::user();
    $chatStyles = $request->chatStyles;

    // ユーザーのプランに基づいてデフォルトの上限人数を設定
    $defaultMaxMembers = 50; // デフォルトはスタンダードプラン
    if ($user->plan === 'premium') {
      $defaultMaxMembers = 200;
    }

    $result = DB::transaction(function () use ($user, $request, $chatStyles, $defaultMaxMembers) {
      // 1. グループを作成
      $group = Group::create([
        'name' => $request->name,
        'description' => $request->description,
        'max_members' => $request->max_members ?? $defaultMaxMembers,
        'owner_user_id' => $user->id,
        'chat_styles' => $chatStyles,
      ]);

      // 2. オーナーをグループメンバーとして必ず追加（チャットスタイルに関わらず）
      GroupMember::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'joined_at' => now(),
        'role' => 'owner',
      ]);

      $chatRooms = [];

      // 3. 'group'スタイルが選択されている場合、グループ全体チャットルームを作成
      if (in_array('group', $chatStyles)) {
        $groupChatRoom = ChatRoom::create([
          'type' => 'group_chat',
          'group_id' => $group->id,
        ]);

        $chatRooms[] = $groupChatRoom;
      }

      return [
        'group' => $group,
        'chat_rooms' => $chatRooms,
      ];
    });

    return response()->json([
      'message' => 'グループが作成されました',
      'group' => $result['group'],
      'chat_rooms' => $result['chat_rooms'],
      'chat_styles' => $chatStyles,
    ], 201);
  }

  /**
   * ユーザーのグループ一覧を取得
   */
  public function getGroups()
  {
    $user = Auth::user();

    // 新しいGroupモデルを使用
    $groups = Group::where('owner_user_id', $user->id)
      ->withCount(['chatRooms', 'activeMembers as member_count'])
      ->with(['chatRooms'])
      ->get();

    // 各グループに未読メッセージ数とロール情報を追加
    $processedGroups = $groups->map(function ($group) use ($user) {
      $groupData = $group->toArray();

      // オーナーなのでroleはowner
      $groupData['role'] = 'owner';

      // グループチャットルームの未読メッセージ数を取得
      $groupChatRoom = $group->groupChatRoom;
      $unreadCount = 0;

      if ($groupChatRoom) {
        $unreadCount = \App\Models\ChatRoomRead::getUnreadCount($user->id, $groupChatRoom->id);
      }

      // グループ内個別チャットルームの未読メッセージ数も追加
      $memberChatRooms = $group->memberChatRooms()
        ->where(function ($query) use ($user) {
          $query->where('participant1_id', $user->id)
            ->orWhere('participant2_id', $user->id);
        })
        ->get();

      foreach ($memberChatRooms as $memberChatRoom) {
        $memberUnreadCount = \App\Models\ChatRoomRead::getUnreadCount($user->id, $memberChatRoom->id);
        $unreadCount += $memberUnreadCount;
      }

      $groupData['unread_messages_count'] = $unreadCount;

      return $groupData;
    });

    return response()->json($processedGroups);
  }

  /**
   * グループの詳細を取得
   */
  public function showGroup(Group $group)
  {
    $user = Auth::user();

    // オーナーかチェック
    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $group->load([
      'chatRooms',
      'activeMembers.user:id,name,friend_id,email'
    ]);

    // グループ全体チャットのroom_tokenを追加
    $groupChatRoom = $group->groupChatRoom()->first();
    $groupData = $group->toArray();
    $groupData['room_token'] = $groupChatRoom ? $groupChatRoom->room_token : null;

    // チャットスタイル情報を追加
    $groupData['chat_styles'] = $group->chat_styles;

    // メンバー数を追加（activeMembers の件数）
    $groupData['member_count'] = $group->activeMembers()->count();

    return response()->json($groupData);
  }

  /**
   * グループを更新
   */
  public function updateGroup(Group $group, Request $request)
  {
    $request->validate([
      'name' => 'sometimes|string|max:100',
      'description' => 'nullable|string',
      'max_members' => 'nullable|integer|min:1|max:500',
      'chatStyles' => 'sometimes|array|min:1',
      'chatStyles.*' => 'string|in:group,group_member',
    ]);

    $user = Auth::user();

    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    // 更新データを準備
    $updateData = $request->only('name', 'description', 'max_members');

    // chat_stylesの処理とチャットルーム管理
    if ($request->has('chatStyles')) {
      $newChatStyles = $request->chatStyles;
      $oldChatStyles = $group->chat_styles ?? [];

      // 既存のチャットスタイルが削除されていないかチェック
      foreach ($oldChatStyles as $oldStyle) {
        if (!in_array($oldStyle, $newChatStyles)) {
          return response()->json([
            'message' => 'チャットスタイルの削除はできません。既存のスタイルは保持する必要があります。',
            'current_styles' => $oldChatStyles,
            'attempted_styles' => $newChatStyles
          ], 422);
        }
      }

      $updateData['chat_styles'] = $newChatStyles;

      // トランザクション内でグループ更新とチャットルーム管理を実行
      DB::transaction(function () use ($group, $updateData, $newChatStyles, $oldChatStyles) {
        $group->update($updateData);

        // グループチャットルーム管理（新規追加のみ）
        if (in_array('group', $newChatStyles) && !in_array('group', $oldChatStyles)) {
          // グループチャットルームを作成
          $this->createGroupChatRoom($group);
        }

        // メンバー間チャット管理（新規追加のみ）
        if (in_array('group_member', $newChatStyles) && !in_array('group_member', $oldChatStyles)) {
          // 既存メンバーとの個別チャットを作成
          $this->createMemberChatsForExistingMembers($group);
        }
      });
    } else {
      $group->update($updateData);
    }

    return response()->json($group);
  }

  /**
   * グループを削除
   */
  public function destroyGroup(Group $group)
  {
    $user = Auth::user();

    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $group->delete();
    return response()->json(['message' => 'グループが削除されました']);
  }

  /**
   * グループにメンバーを追加（新アーキテクチャ対応）
   */
  public function addGroupMember(Group $group, Request $request)
  {
    $request->validate([
      'friend_id' => [
        'required',
        'string',
        'size:6',
        Rule::exists('users', 'friend_id')->where(function ($query) {
          $query->whereNull('deleted_at')->where('is_banned', false);
        }),
      ],
    ], [
      'friend_id.required' => 'フレンドIDは必須です',
      'friend_id.string' => 'フレンドIDは文字列である必要があります',
      'friend_id.size' => 'フレンドIDは6文字である必要があります',
      'friend_id.exists' => '指定されたフレンドIDのユーザーが見つからないか、削除・バンされています',
    ]);

    $user = Auth::user();

    // オーナーかチェック
    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    // friend_idからユーザーを取得（削除・バンされていないユーザーのみ）
    $targetUser = User::where('friend_id', $request->friend_id)
      ->whereNull('deleted_at')
      ->where('is_banned', false)
      ->first();
    if (!$targetUser) {
      return response()->json(['message' => '指定されたフレンドIDのユーザーが見つからないか、削除・バンされています'], 404);
    }

    // 自分自身を追加しようとしていないかチェック
    if ($targetUser->id === $user->id) {
      return response()->json(['message' => '自分自身をグループに追加することはできません'], 422);
    }

    // メンバー数制限チェック
    $currentCount = $group->getMembersCount();
    $maxMembers = $group->max_members;
    $canAdd = $group->canAddMember();

    \Log::info('メンバー追加時のデバッグ情報', [
      'group_id' => $group->id,
      'current_count' => $currentCount,
      'max_members' => $maxMembers,
      'can_add_member' => $canAdd,
      'target_user_id' => $targetUser->id,
      'target_friend_id' => $targetUser->friend_id,
    ]);

    if (!$canAdd) {
      return response()->json(['message' => 'メンバー数が上限に達しています'], 422);
    }

    // 既に参加しているかチェック（アクティブなメンバーのみ）
    if ($group->hasMember($targetUser->id)) {
      \Log::info('ユーザーは既にメンバーです', [
        'group_id' => $group->id,
        'target_user_id' => $targetUser->id,
        'target_friend_id' => $targetUser->friend_id,
      ]);
      return response()->json(['message' => '既に参加しています'], 422);
    }

    // 再参加禁止のチェック
    $existingMember = GroupMember::where('group_id', $group->id)
      ->where('user_id', $targetUser->id)
      ->whereNotNull('left_at')
      ->where('can_rejoin', false)
      ->first();

    if ($existingMember) {
      return response()->json(['message' => 'このユーザーはグループへの再参加が禁止されています'], 422);
    }

    $result = DB::transaction(function () use ($group, $targetUser, $user) {
      // 論理削除されたメンバーレコードがあるかチェック
      $existingMember = GroupMember::where('group_id', $group->id)
        ->where('user_id', $targetUser->id)
        ->first();

      if ($existingMember) {
        // 既存のメンバーレコードを復活
        $existingMember->update([
          'left_at' => null,
          'joined_at' => now(),
          'role' => 'member',
        ]);
        $groupMember = $existingMember;
      } else {
        // 新しいメンバーレコードを作成
        $groupMember = GroupMember::create([
          'group_id' => $group->id,
          'user_id' => $targetUser->id,
          'joined_at' => now(),
          'role' => 'member',
        ]);
      }

      // group_memberスタイルが選択されている場合、作成者との個別チャットを作成
      if ($group->hasMemberChat()) {
        $this->createOwnerMemberChatForGroup($group, $targetUser->id);
      }

      return $groupMember;
    });

    return response()->json($result, 201);
  }

  /**
   * グループオーナーと新メンバー間の個別チャットを作成
   */
  private function createOwnerMemberChat(Conversation $groupConversation, int $memberId)
  {
    // 既存の個別チャットがあるかチェック
    $existingChat = Conversation::where('type', 'group_member')
      ->where('group_conversation_id', $groupConversation->id)
      ->whereHas('conversationParticipants', function ($query) use ($groupConversation) {
        $query->where('user_id', $groupConversation->owner_user_id);
      })
      ->whereHas('conversationParticipants', function ($query) use ($memberId) {
        $query->where('user_id', $memberId);
      })
      ->first();

    if ($existingChat) {
      return $existingChat;
    }

    // 新しい個別チャットを作成
    $memberChat = Conversation::create([
      'type' => 'group_member',
      'name' => $groupConversation->name . ' - 作成者とメンバー間チャット',
      'group_conversation_id' => $groupConversation->id,
    ]);

    // TODO: 古いアーキテクチャ - 現在は使用されていない
    /*
    Participant::create([
      'conversation_id' => $memberChat->id,
      'user_id' => $groupConversation->owner_user_id,
      'joined_at' => now(),
    ]);

    Participant::create([
      'conversation_id' => $memberChat->id,
      'user_id' => $memberId,
      'joined_at' => now(),
    ]);
    */

    return $memberChat;
  }

  /**
   * グループからメンバーを削除（新アーキテクチャ対応）
   */
  public function removeGroupMember(Group $group, GroupMember $groupMember, Request $request)
  {
    $user = Auth::user();

    // オーナーかチェック
    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    // メンバーがこのグループに属しているかチェック
    if ($groupMember->group_id !== $group->id) {
      return response()->json(['message' => '無効なメンバーです'], 422);
    }

    // オーナーを削除しようとしていないかチェック
    if ($groupMember->role === 'owner') {
      return response()->json(['message' => 'オーナーは削除できません'], 422);
    }

    // 既に削除されているメンバーかチェック
    if ($groupMember->left_at !== null) {
      return response()->json(['message' => '既に削除されているメンバーです'], 422);
    }

    $request->validate([
      'can_rejoin' => 'boolean',
    ]);

    $canRejoin = $request->get('can_rejoin', true);

    // メンバーを削除（論理削除）
    $groupMember->removeMember('kicked_by_owner', $user->id, $canRejoin);

    return response()->json([
      'message' => 'メンバーが削除されました',
      'can_rejoin' => $canRejoin
    ]);
  }

  /**
   * QRコードトークンを取得
   */
  public function getGroupQrCode(Group $group)
  {
    $user = Auth::user();

    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    return response()->json(['qr_code_token' => $group->qr_code_token]);
  }

  /**
   * QRコードトークンを再生成
   */
  public function regenerateGroupQrCode(Group $group)
  {
    $user = Auth::user();

    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $group->regenerateQrToken();
    return response()->json(['qr_code_token' => $group->qr_code_token]);
  }

  /**
   * QRコードトークンでグループに参加
   */
  public function joinGroupByToken(Request $request, string $token)
  {
    $request->validate([
      // ログイン必須なのでニックネームは不要
    ]);

    $group = Group::where('qr_code_token', $token)->firstOrFail();
    $user = Auth::user();

    // メンバー数制限チェック
    if (!$group->canAddMember()) {
      return response()->json(['message' => 'グループのメンバー数が上限に達しています'], 422);
    }

    // 既に参加しているかチェック
    if ($group->hasMember($user->id)) {
      return response()->json(['message' => '既に参加しています'], 422);
    }

    // 再参加禁止のチェック
    $existingMember = GroupMember::where('group_id', $group->id)
      ->where('user_id', $user->id)
      ->whereNotNull('left_at')
      ->where('can_rejoin', false)
      ->first();

    if ($existingMember) {
      return response()->json(['message' => 'このグループへの再参加が禁止されています'], 422);
    }

    $result = DB::transaction(function () use ($group, $user) {
      // 論理削除されたメンバーレコードがあるかチェック
      $existingMember = GroupMember::where('group_id', $group->id)
        ->where('user_id', $user->id)
        ->first();

      if ($existingMember) {
        // 既存のメンバーレコードを復活
        $existingMember->update([
          'left_at' => null,
          'joined_at' => now(),
          'role' => 'member',
        ]);
        $groupMember = $existingMember;
      } else {
        // 新しいメンバーレコードを作成
        $groupMember = GroupMember::create([
          'group_id' => $group->id,
          'user_id' => $user->id,
          'joined_at' => now(),
          'role' => 'member',
        ]);
      }

      // group_memberスタイルが選択されている場合、作成者との個別チャットを作成
      if ($group->hasMemberChat()) {
        $this->createOwnerMemberChatForGroup($group, $user->id);
      }

      return $groupMember;
    });

    return response()->json($result, 201);
  }

  /**
   * グループメンバー一覧を取得（アクティブメンバーのみ）
   */
  public function getGroupMembers(Group $group)
  {
    $user = Auth::user();

    // グループメンバーかチェック
    if (!$group->hasMember($user->id)) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $members = $group->activeMembers()
      ->with('user:id,name,friend_id')
      ->where('user_id', '!=', $user->id) // 自分以外のメンバー
      ->get()
      ->map(function ($groupMember) use ($group, $user) {
        // 各メンバーとの個別チャットルームの未読メッセージ数を取得
        $unreadCount = 0;

        $memberChatRoom = ChatRoom::where('type', 'member_chat')
          ->where('group_id', $group->id)
          ->where(function ($query) use ($user, $groupMember) {
            $query->where(function ($q) use ($user, $groupMember) {
              $q->where('participant1_id', $user->id)
                ->where('participant2_id', $groupMember->user_id);
            })->orWhere(function ($q) use ($user, $groupMember) {
              $q->where('participant1_id', $groupMember->user_id)
                ->where('participant2_id', $user->id);
            });
          })
          ->first();

        if ($memberChatRoom) {
          $unreadCount = \App\Models\ChatRoomRead::getUnreadCount($user->id, $memberChatRoom->id);
        }

        return [
          'id' => $groupMember->user->id,
          'name' => $groupMember->user->name,
          'friend_id' => $groupMember->user->friend_id,
          'group_member_label' => $group->name . 'メンバー',
          'role' => $groupMember->role,
          'joined_at' => $groupMember->joined_at,
          'unread_messages_count' => $unreadCount, // 未読メッセージ数を追加
        ];
      });

    return response()->json($members);
  }

  /**
   * グループの全メンバー一覧を取得（削除済み含む）- オーナー専用
   */
  public function getAllGroupMembers(Group $group)
  {
    $user = Auth::user();

    // オーナーかチェック
    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    $members = $group->groupMembers()
      ->with(['removedByUser:id,name'])
      ->where('user_id', '!=', $user->id) // 自分以外のメンバー
      ->get()
      ->map(function ($groupMember) use ($group, $user) {
        // 削除済みユーザーも含めて取得
        $memberUser = \App\Models\User::withTrashed()->find($groupMember->user_id);

        // デバッグログ追加
        \Log::info('getAllGroupMembers - メンバー情報', [
          'group_member_id' => $groupMember->id,
          'user_id' => $groupMember->user_id,
          'left_at' => $groupMember->left_at,
          'removal_type' => $groupMember->removal_type,
          'memberUser_exists' => !is_null($memberUser),
          'memberUser_trashed' => $memberUser ? $memberUser->trashed() : null,
          'memberUser_deleted_at' => $memberUser ? $memberUser->deleted_at : null,
        ]);

        // 各メンバーとの個別チャットルームの未読メッセージ数を取得
        $unreadCount = 0;

        // アクティブなメンバーのみ未読数を計算
        if ($groupMember->left_at === null && $memberUser && !$memberUser->trashed()) {
          $memberChatRoom = ChatRoom::where('type', 'member_chat')
            ->where('group_id', $group->id)
            ->where(function ($query) use ($user, $groupMember) {
              $query->where(function ($q) use ($user, $groupMember) {
                $q->where('participant1_id', $user->id)
                  ->where('participant2_id', $groupMember->user_id);
              })->orWhere(function ($q) use ($user, $groupMember) {
                $q->where('participant1_id', $groupMember->user_id)
                  ->where('participant2_id', $user->id);
              });
            })
            ->first();

          if ($memberChatRoom) {
            $unreadCount = \App\Models\ChatRoomRead::getUnreadCount($user->id, $memberChatRoom->id);
          }
        }

        // グループから削除されているかユーザーが削除されているかをチェック
        $isRemovedFromGroup = !is_null($groupMember->left_at);
        $isUserDeleted = $memberUser && $memberUser->trashed();
        $isDeletedUser = $isRemovedFromGroup || $isUserDeleted || !$memberUser;

        // 削除済みユーザーまたはユーザーが見つからない場合の処理
        if (!$memberUser) {
          return [
            'id' => null,
            'member_id' => $groupMember->id,
            'name' => '削除済みユーザー',
            'friend_id' => '不明',
            'group_member_label' => $group->name . 'メンバー',
            'owner_nickname' => $groupMember->owner_nickname,
            'role' => $groupMember->role,
            'joined_at' => $groupMember->joined_at,
            'left_at' => $groupMember->left_at,
            'can_rejoin' => $groupMember->removal_type === 'user_leave' ? true : $groupMember->can_rejoin,
            'removal_type' => $groupMember->removal_type,
            'removed_by_user' => $groupMember->removedByUser ? [
              'id' => $groupMember->removedByUser->id,
              'name' => $groupMember->removedByUser->name,
            ] : null,
            'is_active' => false,
            'is_deleted_user' => true,
            'unread_messages_count' => 0,
          ];
        }

        return [
          'id' => $memberUser->id,
          'member_id' => $groupMember->id,
          'name' => $memberUser->name,
          'friend_id' => $memberUser->friend_id,
          'group_member_label' => $group->name . 'メンバー',
          'owner_nickname' => $groupMember->owner_nickname,
          'role' => $groupMember->role,
          'joined_at' => $groupMember->joined_at,
          'left_at' => $groupMember->left_at,
          'can_rejoin' => $groupMember->removal_type === 'user_leave' ? true : $groupMember->can_rejoin,
          'removal_type' => $groupMember->removal_type,
          'removed_by_user' => $groupMember->removedByUser ? [
            'id' => $groupMember->removedByUser->id,
            'name' => $groupMember->removedByUser->name,
          ] : null,
          'is_active' => !$isDeletedUser,
          'is_deleted_user' => $isDeletedUser,
          'unread_messages_count' => $unreadCount,
        ];
      });

    return response()->json($members);
  }

  /**
   * メンバーの再参加可否を切り替え（オーナー専用）
   */
  public function toggleMemberRejoin(Group $group, GroupMember $groupMember, Request $request)
  {
    $user = Auth::user();

    // オーナーかチェック
    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    // メンバーがこのグループに属しているかチェック
    if ($groupMember->group_id !== $group->id) {
      return response()->json(['message' => '無効なメンバーです'], 422);
    }

    $request->validate([
      'can_rejoin' => 'required|boolean',
    ]);

    $groupMember->update(['can_rejoin' => $request->can_rejoin]);

    return response()->json([
      'message' => '再参加設定を更新しました',
      'can_rejoin' => $request->can_rejoin
    ]);
  }

  /**
   * 削除済みメンバーを復活（オーナー専用）
   */
  public function restoreGroupMember(Group $group, GroupMember $groupMember)
  {
    $user = Auth::user();

    // オーナーかチェック
    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    // メンバーがこのグループに属しているかチェック
    if ($groupMember->group_id !== $group->id) {
      return response()->json(['message' => '無効なメンバーです'], 422);
    }

    // 削除されていないメンバーかチェック
    if ($groupMember->left_at === null) {
      return response()->json(['message' => 'このユーザーは復活できません'], 422);
    }

    // メンバー数制限チェック
    if (!$group->canAddMember()) {
      return response()->json(['message' => 'グループのメンバー数が上限に達しています'], 422);
    }

    // メンバーを復活
    $groupMember->restoreMember();

    return response()->json(['message' => 'メンバーを復活しました']);
  }

  /**
   * メンバーのニックネームを更新（オーナー専用）
   */
  public function updateMemberNickname(Group $group, GroupMember $groupMember, Request $request)
  {
    $user = Auth::user();

    // オーナーかチェック
    if ($group->owner_user_id !== $user->id) {
      return response()->json(['message' => __('errors.forbidden')], 403);
    }

    // メンバーがこのグループに属しているかチェック
    if ($groupMember->group_id !== $group->id) {
      return response()->json(['message' => '無効なメンバーです'], 422);
    }

    $request->validate([
      'owner_nickname' => 'nullable|string|max:100',
    ]);

    $groupMember->update(['owner_nickname' => $request->owner_nickname]);

    return response()->json([
      'message' => 'ニックネームを更新しました',
      'owner_nickname' => $request->owner_nickname
    ]);
  }

  /**
   * グループメンバーとの個別チャットルームを取得/作成（新アーキテクチャ対応）
   */
  public function getOrCreateMemberChat($groupId, Request $request)
  {
    // Groupモデルを取得
    $group = Group::findOrFail($groupId);

    \Log::info('個別チャット開始', [
      'group_id' => $groupId,
      'group_name' => $group->name,
      'request_data' => $request->all()
    ]);

    try {
      $request->validate([
        'target_user_id' => 'required|exists:users,id',
      ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
      \Log::error('個別チャットバリデーションエラー', [
        'errors' => $e->errors(),
        'request_data' => $request->all()
      ]);
      throw $e;
    }

    $user = Auth::user();
    $targetUserId = $request->target_user_id;

    \Log::info('バリデーション完了', [
      'user_id' => $user->id,
      'target_user_id' => $targetUserId
    ]);

    // 両方がグループメンバーかチェック
    $userIsMember = $group->hasMember($user->id);
    $targetIsMember = $group->hasMember($targetUserId);

    if (!$userIsMember || !$targetIsMember) {
      return response()->json(['message' => 'グループメンバーではありません'], 403);
    }

    // 既存のメンバー間チャットルームを検索
    $existingChatRoom = ChatRoom::where('type', 'member_chat')
      ->where('group_id', $group->id)
      ->where(function ($query) use ($user, $targetUserId) {
        $query->where(function ($q) use ($user, $targetUserId) {
          $q->where('participant1_id', $user->id)
            ->where('participant2_id', $targetUserId);
        })->orWhere(function ($q) use ($user, $targetUserId) {
          $q->where('participant1_id', $targetUserId)
            ->where('participant2_id', $user->id);
        });
      })
      ->first();

    if ($existingChatRoom) {
      return response()->json([
        'id' => $existingChatRoom->id,
        'room_token' => $existingChatRoom->room_token,
        'type' => $existingChatRoom->type,
        'group_id' => $existingChatRoom->group_id,
        'participant1_id' => $existingChatRoom->participant1_id,
        'participant2_id' => $existingChatRoom->participant2_id,
        'display_name' => $existingChatRoom->getOtherParticipantName($user->id),
      ]);
    }

    // 新しいメンバー間チャットルームを作成
    $chatRoom = DB::transaction(function () use ($user, $targetUserId, $group) {
      $chatRoom = ChatRoom::create([
        'type' => 'member_chat',
        'group_id' => $group->id,
        'participant1_id' => $user->id,
        'participant2_id' => $targetUserId,
      ]);

      // 新アーキテクチャでは participant1_id と participant2_id で参加者を管理するため
      // 別途 Participant テーブルへの追加は不要

      return $chatRoom;
    });

    return response()->json([
      'id' => $chatRoom->id,
      'room_token' => $chatRoom->room_token,
      'type' => $chatRoom->type,
      'group_id' => $chatRoom->group_id,
      'participant1_id' => $chatRoom->participant1_id,
      'participant2_id' => $chatRoom->participant2_id,
      'display_name' => $chatRoom->getOtherParticipantName($user->id),
    ], 201);
  }

  /**
   * グループメンバーに一斉メッセージ送信（新アーキテクチャ対応）
   */
  public function sendBulkMessageToMembers(Group $group, Request $request)
  {
    \Log::info('一斉送信開始', [
      'group_id' => $group->id,
      'request_data' => $request->all()
    ]);

    $request->validate([
      'target_user_ids' => 'required|array|min:1',
      'target_user_ids.*' => 'exists:users,id',
      'text_content' => 'required|string|max:5000',
    ]);

    $user = Auth::user();
    $targetUserIds = $request->target_user_ids;
    $textContent = $request->text_content;

    \Log::info('バリデーション完了', [
      'user_id' => $user->id,
      'target_user_ids' => $targetUserIds,
      'text_content_length' => strlen($textContent)
    ]);

    // 削除されたユーザーはアクセス不可
    if ($user->isDeleted()) {
      return response()->json(['message' => 'アカウントが削除されています。'], 403);
    }

    // 送信者がグループメンバーかチェック
    if (!$group->hasMember($user->id)) {
      return response()->json(['message' => 'グループメンバーではありません'], 403);
    }

    // 送信対象がすべてグループメンバーかチェック
    $validTargets = $group->members()
      ->whereIn('users.id', $targetUserIds)
      ->pluck('users.id')
      ->toArray();

    if (count($validTargets) !== count($targetUserIds)) {
      return response()->json(['message' => '無効な送信対象が含まれています'], 422);
    }

    $sentMessages = [];

    DB::transaction(function () use ($user, $validTargets, $textContent, $group, &$sentMessages) {
      foreach ($validTargets as $targetUserId) {
        // メンバー間チャットを取得/作成（新アーキテクチャ）
        $chatRoom = $this->getOrCreateMemberChatForGroup($group, $user->id, $targetUserId);

        // メッセージ送信
        $message = Message::create([
          'chat_room_id' => $chatRoom->id,
          'sender_id' => $user->id,
          'text_content' => $textContent,
          'sent_at' => now(),
        ]);

        $sentMessages[] = [
          'chat_room_id' => $chatRoom->id,
          'target_user_id' => $targetUserId,
          'message_id' => $message->id,
        ];
      }
    });

    return response()->json([
      'message' => '一斉送信が完了しました',
      'sent_count' => count($sentMessages),
      'sent_messages' => $sentMessages,
    ]);
  }

  /**
   * 内部用：メンバー間チャットを取得/作成
   */
  private function getOrCreateMemberChatInternal($userId, $targetUserId, $groupConversation)
  {
    // 既存のメンバー間チャットを検索
    $existingConversation = Conversation::where('type', 'group_member')
      ->where('group_conversation_id', $groupConversation->id)
      ->whereHas('conversationParticipants', function ($query) use ($userId) {
        $query->where('user_id', $userId);
      })
      ->whereHas('conversationParticipants', function ($query) use ($targetUserId) {
        $query->where('user_id', $targetUserId);
      })
      ->first();

    if ($existingConversation) {
      return $existingConversation;
    }

    // 新しいメンバー間チャットを作成
    $conversation = Conversation::create([
      'type' => 'group_member',
      'name' => $groupConversation->name . 'メンバー間チャット',
      'group_conversation_id' => $groupConversation->id,
    ]);

    // TODO: 古いアーキテクチャ - 現在は使用されていない
    /*
    Participant::create([
      'conversation_id' => $conversation->id,
      'user_id' => $userId,
      'joined_at' => now(),
    ]);

    Participant::create([
      'conversation_id' => $conversation->id,
      'user_id' => $targetUserId,
      'joined_at' => now(),
    ]);
    */

    return $conversation;
  }

  /**
   * 新しいアーキテクチャ用のオーナーとメンバー間チャット作成メソッド
   */
  private function createOwnerMemberChatForGroup(Group $group, int $memberId)
  {
    return $this->getOrCreateMemberChatForGroup($group, $group->owner_user_id, $memberId);
  }

  /**
   * グループ内の任意の2人のメンバー間チャットを取得/作成
   */
  private function getOrCreateMemberChatForGroup(Group $group, int $userId1, int $userId2)
  {
    // 既存のメンバー間チャットルームがあるかチェック
    $existingChatRoom = ChatRoom::where('type', 'member_chat')
      ->where('group_id', $group->id)
      ->where(function ($query) use ($userId1, $userId2) {
        // participant1_id と participant2_id の組み合わせでチェック
        $query->where(function ($q) use ($userId1, $userId2) {
          $q->where('participant1_id', $userId1)
            ->where('participant2_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
          $q->where('participant1_id', $userId2)
            ->where('participant2_id', $userId1);
        });
      })
      ->first();

    if ($existingChatRoom) {
      return $existingChatRoom;
    }

    // 新しい個別チャットルームを作成
    $memberChatRoom = ChatRoom::create([
      'type' => 'member_chat',
      'group_id' => $group->id,
      'participant1_id' => $userId1,
      'participant2_id' => $userId2,
    ]);

    // 新アーキテクチャでは participant1_id と participant2_id で参加者を管理するため
    // 別途 Participant テーブルへの追加は不要

    return $memberChatRoom;
  }

  /**
   * QRコードトークンでグループ情報を取得（認証不要）
   */
  public function getGroupInfoByToken(string $token)
  {
    $group = Group::where('qr_code_token', $token)
      ->with('owner:id,name')
      ->first();

    if (!$group) {
      return response()->json(['message' => 'グループが見つかりません'], 404);
    }

    return response()->json([
      'id' => $group->id,
      'name' => $group->name,
      'description' => $group->description,
      'member_count' => $group->getMembersCount(),
      'max_members' => $group->max_members,
      'owner_name' => $group->owner->name,
      'can_join' => $group->canAddMember(),
      'chat_styles' => $group->chat_styles,
    ]);
  }

  /**
   * グループチャットルームを作成
   */
  private function createGroupChatRoom(Group $group)
  {
    // 既存のグループチャットルームがあるかチェック
    $existingRoom = $group->groupChatRoom;
    if ($existingRoom) {
      return $existingRoom;
    }

    // オーナーがGroupMemberとして存在しない場合は追加
    $ownerMember = GroupMember::where('group_id', $group->id)
      ->where('user_id', $group->owner_user_id)
      ->first();

    if (!$ownerMember) {
      GroupMember::create([
        'group_id' => $group->id,
        'user_id' => $group->owner_user_id,
        'joined_at' => now(),
        'role' => 'owner',
      ]);
    }

    // 新しいグループチャットルームを作成
    return ChatRoom::create([
      'type' => 'group_chat',
      'group_id' => $group->id,
      'room_token' => Str::random(16),
    ]);
  }

  /**
   * 既存メンバーとの個別チャットを作成
   */
  private function createMemberChatsForExistingMembers(Group $group)
  {
    $activeMembers = $group->activeMembers()->get();

    foreach ($activeMembers as $member) {
      $this->createOwnerMemberChatForGroup($group, $member->user_id);
    }
  }
}
