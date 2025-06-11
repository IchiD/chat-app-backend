<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
  /**
   * Seed the application's database.
   */
  public function run(): void
  {
    // 基本的なテストデータ（簡単な確認用）
    // Admin Seeder（管理者アカウント作成）
    $this->call(AdminSeeder::class);

    // User::factory(10)->create();

    User::factory()->create([
      'name' => 'Test User',
      'email' => 'test@example.com',
    ]);

    // 包括的なテストデータを作成する場合は以下を使用
    // $this->call(ComprehensiveSeeder::class);
  }
}
