<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // スーパーアドミンが存在しない場合のみ作成
        if (!Admin::where('role', 'super_admin')->exists()) {
            Admin::create([
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
            ]);

            $this->command->info('スーパーアドミンが作成されました。');
            $this->command->info('Email: admin@example.com');
            $this->command->info('Password: password123');
        } else {
            $this->command->info('スーパーアドミンは既に存在します。');
        }

        // 通常のアドミンも作成（必要に応じて）
        if (!Admin::where('email', 'admin2@example.com')->exists()) {
            Admin::create([
                'name' => 'Admin User',
                'email' => 'admin2@example.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]);

            $this->command->info('通常のアドミンが作成されました。');
            $this->command->info('Email: admin2@example.com');
            $this->command->info('Password: password123');
        }
    }
}