<?php

namespace App\Services;

use App\Models\Friendship;
use App\Models\User;
use App\Models\ChatRoom;
use App\Models\ChatRoomRead;
use App\Repositories\ChatRoomRepository;
use Illuminate\Support\Facades\DB;

class ChatRoomService extends BaseService
{
    private ChatRoomRepository $repository;

    public function __construct(ChatRoomRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * 指定されたチャットルームで現在のユーザー以外の参加者を取得
     */
    private function getOtherParticipant(ChatRoom $chatRoom, User $currentUser): ?User
    {
        return $chatRoom->participant1_id === $currentUser->id
            ? $chatRoom->participant2
            : $chatRoom->participant1;
    }

    /**
     * チャットルームのレスポンス用データを構築
     */
    private function buildChatRoomPayload(ChatRoom $chatRoom, User $currentUser, bool $existing): array
    {
        $chatRoom->load([
            'participant1' => fn($q) => $q->select('id', 'name', 'friend_id', 'deleted_at'),
            'participant2' => fn($q) => $q->select('id', 'name', 'friend_id', 'deleted_at'),
            'latestMessage' => function ($q) {
                $q->with([
                    'sender' => fn($s) => $s->select('id', 'name'),
                    'adminSender' => fn($a) => $a->select('id', 'name'),
                ]);
            },
        ]);

        $other = $this->getOtherParticipant($chatRoom, $currentUser);

        return [
            'id' => $chatRoom->id,
            'type' => $chatRoom->type,
            'room_token' => $chatRoom->room_token,
            'group_id' => $chatRoom->group_id,
            'participant1_id' => $chatRoom->participant1_id,
            'participant2_id' => $chatRoom->participant2_id,
            'created_at' => $chatRoom->created_at,
            'updated_at' => $chatRoom->updated_at,
            'other_participant' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'friend_id' => $other->friend_id,
            ] : null,
            'latest_message' => $chatRoom->latestMessage,
            'unread_messages_count' => $existing
                ? ChatRoomRead::getUnreadCount($currentUser->id, $chatRoom->id)
                : 0,
        ];
    }

    public function getUserChatRoomsList(User $user, int $page = 1, int $perPage = 15): array
    {
        try {
            $chatRoomIds = $this->repository->getChatRoomIdsForUser($user);
            $chatRooms   = $this->repository->fetchChatRoomsForIds($chatRoomIds);

            $memberGroupIds = \App\Models\GroupMember::where('user_id', $user->id)
                ->whereNull('left_at')
                ->pluck('group_id')
                ->toArray();
            $friendIds = $user->friends()->pluck('id')->toArray();
        } catch (\Exception $e) {
            \Log::error('ChatRoomService::getUserChatRoomsList error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'data' => [],
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 0,
            ];
        }

        $filtered = $chatRooms->filter(function ($chatRoom) use ($user, $friendIds, $memberGroupIds) {
            if ($chatRoom->type === 'friend_chat') {
                $other = $this->getOtherParticipant($chatRoom, $user);
                return $other && !$other->isDeleted() && !$other->is_banned;
            }

            if ($chatRoom->type === 'support_chat') {
                return (bool) $chatRoom->latestMessage;
            }

            if ($chatRoom->type === 'group_chat') {
                // グループが削除されている場合は除外
                if (!$chatRoom->group) {
                    return false;
                }
                return !($chatRoom->group->owner_user_id === $user->id);
            }

            if ($chatRoom->type === 'member_chat') {
                // グループが削除されている場合は除外
                if (!$chatRoom->group) {
                    return false;
                }
                if ($chatRoom->group->owner_user_id === $user->id) {
                    return false;
                }
                if (!in_array($chatRoom->group->id, $memberGroupIds)) {
                    return false;
                }
                $other = $this->getOtherParticipant($chatRoom, $user);
                return $other && !$other->isDeleted() && !$other->is_banned;
            }

            return true;
        });

        $unreadCounts = ChatRoomRead::getUnreadCountsForChatRooms($user->id, $filtered->pluck('id')->toArray());

        $processed = $filtered->map(function ($chatRoom) use ($user, $unreadCounts) {
            $unreadCount = $unreadCounts[$chatRoom->id] ?? 0;
            $result = [
                'id' => $chatRoom->id,
                'type' => $chatRoom->type,
                'room_token' => $chatRoom->room_token,
                'group_id' => $chatRoom->group_id,
                'participant1_id' => $chatRoom->participant1_id,
                'participant2_id' => $chatRoom->participant2_id,
                'created_at' => $chatRoom->created_at,
                'updated_at' => $chatRoom->updated_at,
                'latest_message' => $chatRoom->latestMessage,
                'unread_messages_count' => $unreadCount,
            ];

            if ($chatRoom->type === 'group_chat' && $chatRoom->group) {
                try {
                    $result['name'] = $chatRoom->group->name;
                    $result['group_name'] = $chatRoom->group->name;
                    $result['participant_count'] = $chatRoom->group->getMembersCount();
                    $result['participants'] = [[
                        'id' => $chatRoom->group->id,
                        'name' => $chatRoom->group->name,
                        'friend_id' => null,
                    ]];
                } catch (\Exception $e) {
                    \Log::error('Error processing group_chat', [
                        'chat_room_id' => $chatRoom->id,
                        'group_id' => $chatRoom->group_id,
                        'error' => $e->getMessage()
                    ]);
                    return null; // この項目をスキップ
                }
            } elseif ($chatRoom->type === 'member_chat' && $chatRoom->group) {
                try {
                    $groupOwner = User::find($chatRoom->group->owner_user_id);
                    $other = $this->getOtherParticipant($chatRoom, $user);
                    $result['name'] = $chatRoom->group->name;
                    $result['group_name'] = $chatRoom->group->name;
                    $result['group_owner'] = $groupOwner ? [
                        'id' => $groupOwner->id,
                        'name' => $groupOwner->name,
                        'friend_id' => $groupOwner->friend_id,
                    ] : null;
                    $result['other_participant'] = $other;
                    $result['participants'] = $other ? [[
                        'id' => $other->id,
                        'name' => $other->name,
                        'friend_id' => $other->friend_id,
                    ]] : [];
                } catch (\Exception $e) {
                    \Log::error('Error processing member_chat', [
                        'chat_room_id' => $chatRoom->id,
                        'group_id' => $chatRoom->group_id,
                        'error' => $e->getMessage()
                    ]);
                    return null; // この項目をスキップ
                }
            } elseif ($chatRoom->type === 'support_chat') {
                $result['name'] = 'サポート';
                $result['participants'] = [[
                    'id' => 0,
                    'name' => 'サポート',
                    'friend_id' => null,
                ]];
            } else {
                $other = $this->getOtherParticipant($chatRoom, $user);
                $result['other_participant'] = $other;
                $result['participants'] = $other ? [[
                    'id' => $other->id,
                    'name' => $other->name,
                    'friend_id' => $other->friend_id,
                ]] : [];
            }

            return $result;
        })->filter(function ($item) {
            return $item !== null; // nullの項目を除外
        })->sortByDesc(function ($room) {
            return $room['latest_message'] ? $room['latest_message']->sent_at : $room['created_at'];
        })->values();

        $offset = ($page - 1) * $perPage;
        $items = $processed->slice($offset, $perPage)->values();

        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $processed->count(),
            'last_page' => ceil($processed->count() / $perPage),
        ];
    }

    public function createFriendChatRoom(User $currentUser, int $recipientId): array
    {
        $recipient = User::find($recipientId);
        if (!$recipient) {
            return $this->errorResponse('not_found', '指定された受信ユーザーが見つかりません。') + ['http_status' => 404];
        }
        if ($recipient->isDeleted()) {
            return $this->errorResponse('deleted_user', '指定されたユーザーは削除されています。') + ['http_status' => 403];
        }

        $friendship = Friendship::getFriendship($currentUser->id, $recipientId);
        if (!$friendship || $friendship->status !== Friendship::STATUS_ACCEPTED) {
            return $this->errorResponse('not_friend', '友達関係にないユーザーとはチャットを開始できません。') + ['http_status' => 403];
        }

        $existing = $this->repository->findFriendChatRoom($currentUser, $recipient);
        if ($existing) {
            return [
                'status' => self::STATUS_SUCCESS,
                'http_status' => 200,
                'data' => $this->buildChatRoomPayload($existing, $currentUser, true),
            ];
        }

        $chatRoom = null;
        DB::transaction(function () use ($currentUser, $recipient, &$chatRoom) {
            $chatRoom = $this->repository->createFriendChatRoom($currentUser, $recipient);
        });

        if ($chatRoom) {
            return [
                'status' => self::STATUS_SUCCESS,
                'http_status' => 201,
                'data' => $this->buildChatRoomPayload($chatRoom, $currentUser, false),
            ];
        }

        return $this->errorResponse('create_failed', 'チャットルームの作成に失敗しました。') + ['http_status' => 500];
    }
}
