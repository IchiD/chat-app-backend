<?php

namespace App\Repositories;

use App\Models\ChatRoom;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Collection;

class ChatRoomRepository
{
    /**
     * 指定ユーザーが参加するチャットルームID一覧を取得
     */
    public function getChatRoomIdsForUser(User $user): array
    {
        $chatRoomIds = ChatRoom::where(function ($query) use ($user) {
            $query->where('participant1_id', $user->id)
                  ->orWhere('participant2_id', $user->id);
        })->pluck('id')->toArray();

        $memberGroupIds = GroupMember::where('user_id', $user->id)
            ->whereNull('left_at')
            ->pluck('group_id')
            ->toArray();

        if (!empty($memberGroupIds)) {
            // グループ関連チャットを一度に取得してマージ
            $groupChatRoomIds = ChatRoom::whereIn('group_id', $memberGroupIds)
                ->where('type', 'group_chat')
                ->pluck('id')
                ->toArray();

            // グループ内個別チャットルームも取得
            $memberChatRoomIds = ChatRoom::whereIn('group_id', $memberGroupIds)
                ->where('type', 'member_chat')
                ->where(function ($query) use ($user) {
                    $query->where('participant1_id', $user->id)
                          ->orWhere('participant2_id', $user->id);
                })
                ->pluck('id')
                ->toArray();

            $chatRoomIds = array_unique(array_merge($chatRoomIds, $groupChatRoomIds, $memberChatRoomIds));
        }

        return $chatRoomIds;
    }

    /**
     * ID一覧からチャットルーム情報を取得
     */
    public function fetchChatRoomsForIds(array $chatRoomIds): \Illuminate\Support\Collection
    {
        // 参加者・グループ・最新メッセージを事前ロードして一括取得
        return ChatRoom::whereIn('id', $chatRoomIds)
            ->whereIn('type', ['member_chat', 'friend_chat', 'group_chat', 'support_chat'])
            ->whereNull('deleted_at')
            ->with([
                'participant1' => function ($query) {
                    $query->select('id', 'name', 'friend_id', 'deleted_at', 'is_banned');
                },
                'participant2' => function ($query) {
                    $query->select('id', 'name', 'friend_id', 'deleted_at', 'is_banned');
                },
                'group' => function ($query) {
                    $query->select('id', 'name', 'owner_user_id');
                },
                'latestMessage' => function ($query) {
                    $query->with([
                        'sender' => function ($senderQuery) {
                            $senderQuery->select('id', 'name');
                        },
                        'adminSender' => function ($adminQuery) {
                            $adminQuery->select('id', 'name');
                        },
                    ]);
                },
            ])
            ->get();
    }

    public function findFriendChatRoom(User $user1, User $user2): ?ChatRoom
    {
        return ChatRoom::where('type', 'friend_chat')
            ->where(function ($query) use ($user1, $user2) {
                $query->where(function ($q) use ($user1, $user2) {
                    $q->where('participant1_id', $user1->id)
                      ->where('participant2_id', $user2->id);
                })->orWhere(function ($q) use ($user1, $user2) {
                    $q->where('participant1_id', $user2->id)
                      ->where('participant2_id', $user1->id);
                });
            })
            ->withTrashed()
            ->first();
    }

    public function createFriendChatRoom(User $currentUser, User $recipient): ChatRoom
    {
        return ChatRoom::create([
            'type' => 'friend_chat',
            'group_id' => null,
            'participant1_id' => $currentUser->id,
            'participant2_id' => $recipient->id,
        ]);
    }
}
