<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\OperationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者ログインテスト
     */
    public function test_admin_can_login()
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
        ]);

        $response = $this->postJson('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'admin' => ['id', 'name', 'email'],
        ]);
    }

    /**
     * 一般ユーザーが管理者エンドポイントにアクセスできないことを確認
     */
    public function test_regular_user_cannot_access_admin_endpoints()
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson('/admin/users');

        $response->assertStatus(403);
    }

    /**
     * 管理者がユーザー一覧を取得できることを確認
     */
    public function test_admin_can_get_users_list()
    {
        $admin = Admin::factory()->create();
        User::factory()->count(5)->create();

        $this->actingAs($admin, 'admin');

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'friend_id', 'created_at']
            ],
            'meta' => ['current_page', 'total']
        ]);
    }

    /**
     * 管理者がユーザーをバンできることを確認
     */
    public function test_admin_can_ban_user()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'admin');

        $response = $this->putJson("/admin/users/{$user->id}/ban", [
            'reason' => 'Violation of terms of service',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User banned successfully']);

        // ユーザーがバンされたことを確認
        $user->refresh();
        $this->assertTrue($user->is_banned);
        $this->assertEquals('Violation of terms of service', $user->deleted_reason);

        // 操作ログが記録されたことを確認
        $this->assertDatabaseHas('operation_logs', [
            'category' => 'backend',
            'action' => 'user_ban',
            'description' => "User ID: {$user->id} banned by Admin ID: {$admin->id}",
        ]);
    }

    /**
     * 管理者がユーザーを削除できることを確認
     */
    public function test_admin_can_delete_user()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'admin');

        $response = $this->deleteJson("/admin/users/{$user->id}", [
            'reason' => 'Account policy violation',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'User deleted successfully']);

        // ユーザーがソフトデリートされたことを確認
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // 削除情報が記録されたことを確認
        $user->refresh();
        $this->assertEquals($admin->id, $user->deleted_by);
        $this->assertEquals('Account policy violation', $user->deleted_reason);
        $this->assertNotNull($user->deleted_at);
    }

    /**
     * 管理者がチャットルーム一覧を取得できることを確認
     */
    public function test_admin_can_get_chat_rooms_list()
    {
        $admin = Admin::factory()->create();
        ChatRoom::factory()->count(3)->create(['type' => 'friend_chat']);
        ChatRoom::factory()->count(2)->create(['type' => 'group_chat']);
        ChatRoom::factory()->create(['type' => 'support_chat']); // サポートチャットは除外される

        $this->actingAs($admin, 'admin');

        $response = $this->getJson('/admin/conversations');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data'); // サポートチャットを除く5件
    }

    /**
     * 管理者がメッセージを削除できることを確認
     */
    public function test_admin_can_delete_message()
    {
        $admin = Admin::factory()->create();
        $chatRoom = ChatRoom::factory()->create(['type' => 'friend_chat']);
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'text_content' => 'Inappropriate content',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->deleteJson("/admin/messages/{$message->id}", [
            'reason' => 'Inappropriate content',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Message deleted successfully']);

        // メッセージが管理者によって削除されたことを確認
        $message->refresh();
        $this->assertNotNull($message->admin_deleted_at);
        $this->assertEquals($admin->id, $message->admin_deleted_by);
        $this->assertEquals('Inappropriate content', $message->admin_deleted_reason);
    }

    /**
     * 管理者操作ログの記録テスト
     */
    public function test_admin_actions_are_logged()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'admin');

        // ユーザー削除操作
        $this->deleteJson("/admin/users/{$user->id}", [
            'reason' => 'Test deletion',
        ]);

        // ログが記録されていることを確認
        $log = OperationLog::where('category', 'backend')
                          ->where('action', 'user_delete')
                          ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString("User ID: {$user->id}", $log->description);
        $this->assertStringContainsString("Admin ID: {$admin->id}", $log->description);
    }

    /**
     * 管理者権限の階層テスト（スーパー管理者のみ）
     */
    public function test_only_super_admin_can_create_new_admin()
    {
        $regularAdmin = Admin::factory()->create(['role' => 'admin']);
        $superAdmin = Admin::factory()->create(['role' => 'super_admin']);

        // 通常の管理者では新規管理者を作成できない
        $this->actingAs($regularAdmin, 'admin');
        $response = $this->postJson('/admin/admins', [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);
        $response->assertStatus(403);

        // スーパー管理者は新規管理者を作成できる
        $this->actingAs($superAdmin, 'admin');
        $response = $this->postJson('/admin/admins', [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);
        $response->assertStatus(201);
    }

    /**
     * 管理者ダッシュボードの統計情報取得テスト
     */
    public function test_admin_can_get_dashboard_statistics()
    {
        $admin = Admin::factory()->create();
        
        // テストデータ作成
        User::factory()->count(10)->create();
        User::factory()->count(2)->create(['is_banned' => true]);
        ChatRoom::factory()->count(5)->create(['type' => 'friend_chat']);
        Message::factory()->count(20)->create();

        $this->actingAs($admin, 'admin');

        $response = $this->getJson('/admin/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_users',
            'active_users',
            'banned_users',
            'total_chat_rooms',
            'total_messages',
            'messages_today',
        ]);
    }
}