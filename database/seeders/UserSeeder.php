<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // 1. アクティブなユーザー群 (10人)
    $activeUsers = User::factory(10)->create([
      'is_verified' => true,
      'email_verified_at' => now(),
      'is_banned' => false,
      'deleted_at' => null,
    ]);

    // 2. 新規登録未確認ユーザー (5人)
    $unverifiedUsers = User::factory(5)->create([
      'is_verified' => false,
      'email_verified_at' => null,
      'is_banned' => false,
      'deleted_at' => null,
      'token_expires_at' => Carbon::now()->addHours(1),
    ]);

    // 3. 確認期限切れユーザー (3人)
    $expiredUsers = User::factory(3)->create([
      'is_verified' => false,
      'email_verified_at' => null,
      'is_banned' => false,
      'deleted_at' => null,
      'token_expires_at' => Carbon::now()->subHours(1),
    ]);

    // 4. バンされたユーザー (2人)
    $bannedUsers = User::factory(2)->create([
      'is_verified' => true,
      'email_verified_at' => now(),
      'is_banned' => true,
      'deleted_at' => null,
    ]);

    // 5. 削除されたユーザー (2人)
    $deletedUsers = User::factory(2)->create([
      'is_verified' => true,
      'email_verified_at' => now(),
      'is_banned' => false,
      'deleted_at' => now(),
      'deleted_reason' => '管理者による削除',
      'deleted_by' => 1, // 管理者ID（AdminSeederで作成されていることを想定）
    ]);

    // 6. 特定のテストユーザー
    $testUsers = [
      [
        'name' => '田中太郎',
        'email' => 'active@example.com',
        'password' => Hash::make('password'),
        'is_verified' => true,
        'email_verified_at' => now(),
        'is_banned' => false,
        'deleted_at' => null,
      ],
      [
        'name' => '佐藤花子',
        'email' => 'newuser@example.com',
        'password' => Hash::make('password'),
        'is_verified' => false,
        'email_verified_at' => null,
        'is_banned' => false,
        'deleted_at' => null,
        'token_expires_at' => Carbon::now()->addHours(1),
      ],
      [
        'name' => '鈴木一郎',
        'email' => 'banned@example.com',
        'password' => Hash::make('password'),
        'is_verified' => true,
        'email_verified_at' => now(),
        'is_banned' => true,
        'deleted_at' => null,
      ],
    ];

    foreach ($testUsers as $userData) {
      User::factory()->create($userData);
    }

    $this->command->info('UserSeeder: 様々な状態のユーザー（合計25人）を作成しました。');
  }
}
