<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ChatRoom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LargeGroupSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    $this->command->info('LargeGroupSeeder: 50人メンバーの大規模グループを作成中...');

    // 十分なユーザーがない場合は追加作成
    $activeUsers = User::where('is_verified', true)
      ->where('is_banned', false)
      ->whereNull('deleted_at')
      ->get();

    if ($activeUsers->count() < 50) {
      $neededUsers = 50 - $activeUsers->count();
      $this->command->info("追加で{$neededUsers}人のユーザーを作成します...");

      for ($i = 1; $i <= $neededUsers; $i++) {
        User::factory()->create([
          'name' => "テストユーザー{$i}",
          'email' => "test{$i}@largegroup.com",
          'password' => Hash::make('password'),
          'is_verified' => true,
          'email_verified_at' => now(),
          'is_banned' => false,
          'deleted_at' => null,
          'plan' => 'free', // デフォルトはフリープラン
        ]);
      }

      // 再取得
      $activeUsers = User::where('is_verified', true)
        ->where('is_banned', false)
        ->whereNull('deleted_at')
        ->get();
    }

    // オーナーをプレミアムプランに設定
    $owner = $activeUsers->first();
    $owner->update(['plan' => 'premium']);

    // 大規模グループを作成
    $group = Group::create([
      'name' => '50人の大規模テストグループ',
      'description' => 'max_members機能をテストするための50人のメンバーを持つグループです。プランによる制限やメンバー追加制限の動作確認に使用します。',
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

    // オーナーを最初のメンバーとして追加
    GroupMember::create([
      'group_id' => $group->id,
      'user_id' => $owner->id,
      'role' => 'owner',
      'joined_at' => now(),
    ]);

    // 残り49人のメンバーを追加
    $otherUsers = $activeUsers->where('id', '!=', $owner->id)->take(49);

    foreach ($otherUsers as $index => $user) {
      // 一部のユーザーには管理者権限を付与
      $role = ($index < 5) ? 'admin' : 'member';

      GroupMember::create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'role' => $role,
        'joined_at' => now()->subMinutes(rand(1, 1440)), // ランダムな参加時間
      ]);
    }

    // グループの最終状態を確認
    $finalMemberCount = $group->getMembersCount();
    $canAddMore = $group->canAddMember() ? 'はい' : 'いいえ';

    $this->command->info("✅ 大規模グループが作成されました:");
    $this->command->info("   グループ名: {$group->name}");
    $this->command->info("   グループID: {$group->id}");
    $this->command->info("   オーナー: {$owner->name} ({$owner->email})");
    $this->command->info("   メンバー数: {$finalMemberCount}人");
    $this->command->info("   max_members: {$group->max_members}人");
    $this->command->info("   メンバー追加可能: {$canAddMore}");
    $this->command->info("   QRコードトークン: {$group->qr_code_token}");

    // メンバー構成の詳細
    $ownerCount = $group->groupMembers()->where('role', 'owner')->count();
    $adminCount = $group->groupMembers()->where('role', 'admin')->count();
    $memberCount = $group->groupMembers()->where('role', 'member')->count();

    $this->command->info("   役割構成: オーナー({$ownerCount}) + 管理者({$adminCount}) + メンバー({$memberCount}) = {$finalMemberCount}人");

    // テストシナリオの提案
    $this->command->line('');
    $this->command->info('🧪 テストシナリオ:');
    $this->command->info('1. グループの詳細ページでメンバー数が50人と表示されることを確認');
    $this->command->info('2. 新しいユーザーでグループ参加を試行し、「グループが満員です」エラーが表示されることを確認');
    $this->command->info('3. 管理画面でmax_membersを60に変更し、メンバー追加が可能になることを確認');
    $this->command->info('4. フリープランのユーザーがグループ機能にアクセスできないことを確認');
  }
}
