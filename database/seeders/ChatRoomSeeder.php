<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Friendship;
use App\Models\Group;
use Illuminate\Database\Seeder;

class ChatRoomSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // 1. 友達同士のメンバーチャット（1対1チャット）を作成
    $this->createFriendChatRooms();

    // 2. グループチャットを作成
    $this->createGroupChatRooms();

    // 3. サポートチャットを作成
    $this->createSupportChatRooms();

    $this->command->info('ChatRoomSeeder: チャットルームを作成しました。');
  }

  /**
   * 友達同士のチャット（1対1）を作成
   */
  private function createFriendChatRooms(): void
  {
    // 承認済みの友達関係を取得
    $acceptedFriendships = Friendship::where('status', Friendship::STATUS_ACCEPTED)
      ->whereNull('deleted_at')
      ->with(['user', 'friend'])
      ->get();

    $memberChatCount = 0;
    $friendChatCount = 0;

    foreach ($acceptedFriendships as $friendship) {
      // アクティブなユーザーかチェック
      if (!$this->isActiveUser($friendship->user) || !$this->isActiveUser($friendship->friend)) {
        continue;
      }

      // ランダムにmember_chatまたはfriend_chatを選択
      $chatType = rand(0, 1) === 0 ? 'member_chat' : 'friend_chat';

      // 既存のチャットルームがないかチェック（両タイプで）
      $existingRoom = ChatRoom::where(function ($query) use ($friendship) {
        $query->where('participant1_id', $friendship->user_id)
          ->where('participant2_id', $friendship->friend_id);
      })
        ->orWhere(function ($query) use ($friendship) {
          $query->where('participant1_id', $friendship->friend_id)
            ->where('participant2_id', $friendship->user_id);
        })
        ->whereIn('type', ['member_chat', 'friend_chat'])
        ->first();

      if (!$existingRoom) {
        ChatRoom::create([
          'type' => $chatType,
          'participant1_id' => $friendship->user_id,
          'participant2_id' => $friendship->friend_id,
          'group_id' => null,
        ]);

        if ($chatType === 'member_chat') {
          $memberChatCount++;
        } else {
          $friendChatCount++;
        }
      }
    }

    $this->command->info("FriendChatRooms: {$memberChatCount}個のメンバーチャットと{$friendChatCount}個のフレンドチャットを作成しました。");
  }

  /**
   * グループチャットを作成
   */
  private function createGroupChatRooms(): void
  {
    // アクティブなユーザーを取得
    $activeUsers = User::where('is_verified', true)
      ->where('is_banned', false)
      ->whereNull('deleted_at')
      ->get();

    if ($activeUsers->count() < 3) {
      $this->command->warn('グループチャットを作成するには、最低3人のアクティブなユーザーが必要です。');
      return;
    }

    $groupChats = [
      [
        'name' => '開発チーム',
        'description' => '日々の開発に関する情報共有',
        'member_count' => 5
      ],
      [
        'name' => '趣味の会',
        'description' => '趣味について語り合いましょう',
        'member_count' => 4
      ],
      [
        'name' => '勉強会',
        'description' => '技術の勉強会です',
        'member_count' => 6
      ],
      [
        'name' => 'お疲れ様チャット',
        'description' => '日々の疲れを癒やしましょう',
        'member_count' => 3
      ],
    ];

    $createdGroups = 0;
    $usedOwners = collect(); // 使用済みオーナーを記録

    foreach ($groupChats as $groupData) {
      // 必要なメンバー数が足りない場合はスキップ
      if ($activeUsers->count() < $groupData['member_count']) {
        continue;
      }

      // 使用済みでないユーザーからオーナーを選択
      $availableOwners = $activeUsers->whereNotIn('id', $usedOwners);

      if ($availableOwners->isEmpty()) {
        // 使用済みオーナーをリセット（全員が使われた場合）
        $availableOwners = $activeUsers;
        $usedOwners = collect();
      }

      $owner = $availableOwners->random();
      $usedOwners->push($owner->id);

      // オーナーのプランを課金プランに設定
      $owner->update([
        'plan' => collect(['standard', 'premium'])->random()
      ]);

      // グループを作成
      $group = Group::create([
        'name' => $groupData['name'],
        'description' => $groupData['description'],
        'owner_user_id' => $owner->id,
        'max_members' => 50,
        'chat_styles' => ['group'], // グループチャット機能を有効化
      ]);

      // グループチャットルームを作成
      $chatRoom = ChatRoom::create([
        'type' => 'group_chat',
        'group_id' => $group->id,
        'participant1_id' => null,
        'participant2_id' => null,
      ]);

      // オーナーを必ずメンバーに追加
      $group->groupMembers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'joined_at' => now(),
      ]);

      // 残りのメンバーをランダムに追加（オーナーは除く）
      $remainingCount = $groupData['member_count'] - 1; // オーナー分を引く
      if ($remainingCount > 0) {
        $otherUsers = $activeUsers->where('id', '!=', $owner->id);
        $selectedMembers = $otherUsers->random(min($remainingCount, $otherUsers->count()));

        foreach ($selectedMembers as $member) {
          // 既に追加されていないかチェック
          if (!$group->groupMembers()->where('user_id', $member->id)->exists()) {
            $group->groupMembers()->create([
              'user_id' => $member->id,
              'role' => 'member',
              'joined_at' => now(),
            ]);
          }
        }
      }

      $createdGroups++;
    }

    $this->command->info("GroupChatRooms: {$createdGroups}個のグループチャットを作成しました。");
  }

  /**
   * サポートチャットを作成
   */
  private function createSupportChatRooms(): void
  {
    // アクティブなユーザーを取得
    $activeUsers = User::where('is_verified', true)
      ->where('is_banned', false)
      ->whereNull('deleted_at')
      ->get();

    if ($activeUsers->isEmpty()) {
      $this->command->warn('サポートチャットを作成するには、アクティブなユーザーが必要です。');
      return;
    }

    $supportChatCount = 0;
    $maxSupportChats = min(8, $activeUsers->count()); // 最大8個または全ユーザー数の少ない方

    // ランダムなユーザーを選んでサポートチャットを作成
    $selectedUsers = $activeUsers->random($maxSupportChats);

    foreach ($selectedUsers as $user) {
      // 既存のサポートチャットがないかチェック
      $existingSupport = ChatRoom::where('type', 'support_chat')
        ->where('participant1_id', $user->id)
        ->first();

      if (!$existingSupport) {
        ChatRoom::create([
          'type' => 'support_chat',
          'participant1_id' => $user->id, // ユーザー
          'participant2_id' => null, // サポートチャットでは null（管理者は別途管理）
          'group_id' => null,
        ]);
        $supportChatCount++;
      }
    }

    $this->command->info("SupportChatRooms: {$supportChatCount}個のサポートチャットを作成しました。");
  }

  /**
   * ユーザーがアクティブかどうかをチェック
   */
  private function isActiveUser(User $user): bool
  {
    return $user->is_verified && !$user->is_banned && is_null($user->deleted_at);
  }
}
