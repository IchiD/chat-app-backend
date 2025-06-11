<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Friendship;
use Illuminate\Database\Seeder;

class FriendshipSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // アクティブなユーザーのみ取得（削除・バンされていない）
    $activeUsers = User::where('is_verified', true)
      ->where('is_banned', false)
      ->whereNull('deleted_at')
      ->get();

    if ($activeUsers->count() < 2) {
      $this->command->warn('友達関係を作成するには、最低2人のアクティブなユーザーが必要です。');
      return;
    }

    $friendshipCount = 0;

    // 1. 承認済みの友達関係を作成 (10組)
    for ($i = 0; $i < 10 && $i < $activeUsers->count() - 1; $i++) {
      $user1 = $activeUsers[$i];
      $user2 = $activeUsers[$i + 1];

      // 既存の友達関係がないかチェック
      $existingFriendship = Friendship::where(function ($query) use ($user1, $user2) {
        $query->where('user_id', $user1->id)->where('friend_id', $user2->id);
      })->orWhere(function ($query) use ($user1, $user2) {
        $query->where('user_id', $user2->id)->where('friend_id', $user1->id);
      })->first();

      if (!$existingFriendship) {
        Friendship::create([
          'user_id' => $user1->id,
          'friend_id' => $user2->id,
          'status' => Friendship::STATUS_ACCEPTED,
          'message' => 'よろしくお願いします！',
        ]);
        $friendshipCount++;
      }
    }

    // 2. 申請中の友達関係を作成 (5組)
    $pendingCount = 0;
    for ($i = 0; $i < 5 && $pendingCount < 5; $i++) {
      $randomUsers = $activeUsers->random(2);
      $user1 = $randomUsers[0];
      $user2 = $randomUsers[1];

      // 既存の関係がないかチェック
      $existingFriendship = Friendship::where(function ($query) use ($user1, $user2) {
        $query->where('user_id', $user1->id)->where('friend_id', $user2->id);
      })->orWhere(function ($query) use ($user1, $user2) {
        $query->where('user_id', $user2->id)->where('friend_id', $user1->id);
      })->first();

      if (!$existingFriendship) {
        Friendship::create([
          'user_id' => $user1->id,
          'friend_id' => $user2->id,
          'status' => Friendship::STATUS_PENDING,
          'message' => '友達になりませんか？',
        ]);
        $pendingCount++;
      }
    }

    // 3. 拒否された友達関係を作成 (3組)
    $rejectedCount = 0;
    for ($i = 0; $i < 10 && $rejectedCount < 3; $i++) {
      $randomUsers = $activeUsers->random(2);
      $user1 = $randomUsers[0];
      $user2 = $randomUsers[1];

      // 既存の関係がないかチェック
      $existingFriendship = Friendship::where(function ($query) use ($user1, $user2) {
        $query->where('user_id', $user1->id)->where('friend_id', $user2->id);
      })->orWhere(function ($query) use ($user1, $user2) {
        $query->where('user_id', $user2->id)->where('friend_id', $user1->id);
      })->first();

      if (!$existingFriendship) {
        Friendship::create([
          'user_id' => $user1->id,
          'friend_id' => $user2->id,
          'status' => Friendship::STATUS_REJECTED,
          'message' => 'すみません...',
        ]);
        $rejectedCount++;
      }
    }

    // 4. 特定のユーザー間で複雑な友達関係を作成
    if ($activeUsers->count() >= 5) {
      $specificUsers = $activeUsers->take(5);

      // ユーザー1とユーザー2は友達
      $this->createFriendshipIfNotExists($specificUsers[0], $specificUsers[1], Friendship::STATUS_ACCEPTED, '同期です！');

      // ユーザー1とユーザー3は申請中
      $this->createFriendshipIfNotExists($specificUsers[0], $specificUsers[2], Friendship::STATUS_PENDING, '一緒に仕事しませんか？');

      // ユーザー2とユーザー3は友達
      $this->createFriendshipIfNotExists($specificUsers[1], $specificUsers[2], Friendship::STATUS_ACCEPTED, 'よろしく！');

      // ユーザー3とユーザー4は拒否された関係
      $this->createFriendshipIfNotExists($specificUsers[2], $specificUsers[3], Friendship::STATUS_REJECTED, '申し訳ございません');
    }

    $this->command->info("FriendshipSeeder: 友達関係を作成しました（承認済み: {$friendshipCount}組、申請中: {$pendingCount}組、拒否: {$rejectedCount}組）");
  }

  /**
   * 友達関係が存在しない場合にのみ作成
   */
  private function createFriendshipIfNotExists(User $user1, User $user2, int $status, string $message): void
  {
    $existingFriendship = Friendship::where(function ($query) use ($user1, $user2) {
      $query->where('user_id', $user1->id)->where('friend_id', $user2->id);
    })->orWhere(function ($query) use ($user1, $user2) {
      $query->where('user_id', $user2->id)->where('friend_id', $user1->id);
    })->first();

    if (!$existingFriendship) {
      Friendship::create([
        'user_id' => $user1->id,
        'friend_id' => $user2->id,
        'status' => $status,
        'message' => $message,
      ]);
    }
  }
}
