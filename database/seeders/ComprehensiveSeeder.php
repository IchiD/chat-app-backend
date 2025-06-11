<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ComprehensiveSeeder extends Seeder
{
  /**
   * Run the database seeds.
   * 
   * このSeederは全てのテストデータを順序立てて作成します。
   * 依存関係を考慮して適切な順序で実行されます。
   */
  public function run(): void
  {
    $this->command->info('=== 包括的なテストデータの作成を開始します ===');

    // 1. 管理者データの作成（既存）
    $this->command->info('1. 管理者データを作成中...');
    $this->call(AdminSeeder::class);

    // 2. 様々な状態のユーザーを作成
    $this->command->info('2. 多様なユーザーデータを作成中...');
    $this->call(UserSeeder::class);

    // 3. 友達関係を作成
    $this->command->info('3. 友達関係を作成中...');
    $this->call(FriendshipSeeder::class);

    // 4. チャットルーム（会話）を作成
    $this->command->info('4. チャットルームを作成中...');
    $this->call(ChatRoomSeeder::class);

    // 5. メッセージを作成
    $this->command->info('5. メッセージデータを作成中...');
    $this->call(MessageSeeder::class);

    $this->command->info('=== テストデータの作成が完了しました ===');
    $this->displaySummary();
  }

  /**
   * 作成されたデータの概要を表示
   */
  private function displaySummary(): void
  {
    $this->command->info('');
    $this->command->info('📊 作成されたデータの概要:');
    $this->command->line('────────────────────────────────');

    // ユーザー統計
    $userStats = $this->getUserStats();
    $this->command->info("👥 ユーザー: {$userStats['total']}人");
    $this->command->line("   ├─ アクティブ: {$userStats['active']}人");
    $this->command->line("   ├─ 未確認: {$userStats['unverified']}人");
    $this->command->line("   ├─ バン済み: {$userStats['banned']}人");
    $this->command->line("   └─ 削除済み: {$userStats['deleted']}人");

    // 友達関係統計
    $friendshipStats = $this->getFriendshipStats();
    $this->command->info("🤝 友達関係: {$friendshipStats['total']}組");
    $this->command->line("   ├─ 承認済み: {$friendshipStats['accepted']}組");
    $this->command->line("   ├─ 申請中: {$friendshipStats['pending']}組");
    $this->command->line("   └─ 拒否: {$friendshipStats['rejected']}組");

    // チャットルーム統計
    $chatRoomStats = $this->getChatRoomStats();
    $this->command->info("💬 チャットルーム: {$chatRoomStats['total']}個");
    $this->command->line("   ├─ メンバーチャット: {$chatRoomStats['member']}個");
    $this->command->line("   ├─ フレンドチャット: {$chatRoomStats['friend']}個");
    $this->command->line("   ├─ グループチャット: {$chatRoomStats['group']}個");
    $this->command->line("   └─ サポートチャット: {$chatRoomStats['support']}個");

    // メッセージ統計
    $messageStats = $this->getMessageStats();
    $this->command->info("📝 メッセージ: {$messageStats['total']}件");
    $this->command->line("   ├─ ユーザーメッセージ: {$messageStats['user']}件");
    $this->command->line("   └─ 管理者メッセージ: {$messageStats['admin']}件");

    // プラン統計
    $planStats = $this->getPlanStats();
    $this->command->info("💳 ユーザープラン:");
    $this->command->line("   ├─ 無料プラン: {$planStats['free']}人");
    $this->command->line("   ├─ スタンダードプラン: {$planStats['standard']}人");
    $this->command->line("   └─ プレミアムプラン: {$planStats['premium']}人");

    // グループオーナー統計
    $ownerStats = $this->getOwnerStats();
    $this->command->info("👑 グループオーナー: {$ownerStats['total']}人");
    $this->command->line("   ├─ スタンダードプラン: {$ownerStats['standard']}人");
    $this->command->line("   └─ プレミアムプラン: {$ownerStats['premium']}人");

    $this->command->info('');
    $this->command->info('✨ テストデータの準備が整いました！');
    $this->command->line('────────────────────────────────');
  }

  /**
   * ユーザー統計を取得
   */
  private function getUserStats(): array
  {
    return [
      'total' => \App\Models\User::count(),
      'active' => \App\Models\User::where('is_verified', true)
        ->where('is_banned', false)
        ->whereNull('deleted_at')
        ->count(),
      'unverified' => \App\Models\User::where('is_verified', false)->count(),
      'banned' => \App\Models\User::where('is_banned', true)->count(),
      'deleted' => \App\Models\User::whereNotNull('deleted_at')->count(),
    ];
  }

  /**
   * 友達関係統計を取得
   */
  private function getFriendshipStats(): array
  {
    return [
      'total' => \App\Models\Friendship::count(),
      'accepted' => \App\Models\Friendship::where('status', \App\Models\Friendship::STATUS_ACCEPTED)->count(),
      'pending' => \App\Models\Friendship::where('status', \App\Models\Friendship::STATUS_PENDING)->count(),
      'rejected' => \App\Models\Friendship::where('status', \App\Models\Friendship::STATUS_REJECTED)->count(),
    ];
  }

  /**
   * チャットルーム統計を取得
   */
  private function getChatRoomStats(): array
  {
    return [
      'total' => \App\Models\ChatRoom::count(),
      'member' => \App\Models\ChatRoom::where('type', 'member_chat')->count(),
      'friend' => \App\Models\ChatRoom::where('type', 'friend_chat')->count(),
      'group' => \App\Models\ChatRoom::where('type', 'group_chat')->count(),
      'support' => \App\Models\ChatRoom::where('type', 'support_chat')->count(),
    ];
  }

  /**
   * メッセージ統計を取得
   */
  private function getMessageStats(): array
  {
    return [
      'total' => \App\Models\Message::count(),
      'user' => \App\Models\Message::whereNotNull('sender_id')->count(),
      'admin' => \App\Models\Message::whereNotNull('admin_sender_id')->count(),
    ];
  }

  /**
   * プラン統計を取得
   */
  private function getPlanStats(): array
  {
    return [
      'free' => \App\Models\User::where('plan', 'free')->orWhereNull('plan')->count(),
      'standard' => \App\Models\User::where('plan', 'standard')->count(),
      'premium' => \App\Models\User::where('plan', 'premium')->count(),
    ];
  }

  /**
   * グループオーナー統計を取得
   */
  private function getOwnerStats(): array
  {
    $ownerIds = \App\Models\Group::pluck('owner_user_id')->unique();
    $owners = \App\Models\User::whereIn('id', $ownerIds)->get();

    return [
      'total' => $owners->count(),
      'standard' => $owners->where('plan', 'standard')->count(),
      'premium' => $owners->where('plan', 'premium')->count(),
    ];
  }
}
