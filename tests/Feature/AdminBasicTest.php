<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AdminBasicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 基本的なテーブルのみ作成
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role')->default('admin');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * 管理者ログインテスト
     */
    public function test_admin_can_login()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->post(route('admin.login'), [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /**
     * 管理者ログイン失敗テスト
     */
    public function test_admin_login_with_invalid_credentials()
    {
        Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->post(route('admin.login'), [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('admin');
    }

    /**
     * 管理者ダッシュボードアクセステスト
     */
    public function test_admin_can_access_dashboard()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.dashboard'));

        $response->assertSuccessful();
    }

    /**
     * 一般ユーザーの管理画面アクセス拒否テスト
     */
    public function test_regular_user_cannot_access_admin_dashboard()
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user)
                         ->get(route('admin.dashboard'));

        $response->assertStatus(302);
    }

    /**
     * 管理者ユーザー一覧取得テスト
     */
    public function test_admin_can_view_users_list()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        // テストユーザー作成
        User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.users'));

        $response->assertSuccessful();
    }

    /**
     * スーパー管理者のみ管理者作成可能テスト
     */
    public function test_only_super_admin_can_create_admin()
    {
        $superAdmin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($superAdmin, 'admin')
                         ->post(route('admin.admins.create'), [
                             'name' => 'New Admin',
                             'email' => 'newadmin@example.com',
                             'password' => 'password123',
                             'role' => 'admin',
                         ]);

        $response->assertRedirect(route('admin.admins'));

        $this->assertDatabaseHas('admins', [
            'email' => 'newadmin@example.com',
            'name' => 'New Admin',
            'role' => 'admin',
        ]);
    }

    /**
     * 通常管理者は管理者作成不可テスト
     */
    public function test_regular_admin_cannot_create_admin()
    {
        $admin = Admin::create([
            'name' => 'Regular Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->post(route('admin.admins.create'), [
                             'name' => 'New Admin',
                             'email' => 'newadmin@example.com',
                             'password' => 'password123',
                             'role' => 'admin',
                         ]);

        $response->assertStatus(403);
    }

    /**
     * 管理者ログアウトテスト
     */
    public function test_admin_can_logout()
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }
}