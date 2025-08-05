<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\Friendship;
use App\Models\OperationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AdminFunctionalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者ログインテスト
     */
    public function test_admin_can_login()
    {
        $admin = Admin::factory()->create([
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
        Admin::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
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
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.dashboard'));

        $response->assertSuccessful();
        $response->assertViewIs('admin.dashboard');
        $response->assertViewHas(['admin', 'userCount', 'adminCount', 'chatRoomCount']);
    }

    /**
     * 一般ユーザーの管理画面アクセス拒否テスト
     */
    public function test_regular_user_cannot_access_admin_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
                         ->get(route('admin.dashboard'));

        $response->assertStatus(302); // リダイレクト（認証失敗）
    }

    /**
     * 管理者ユーザー一覧取得テスト
     */
    public function test_admin_can_view_users_list()
    {
        $admin = Admin::factory()->create();
        $users = User::factory()->count(5)->create();

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.users'));

        $response->assertSuccessful();
        $response->assertViewIs('admin.users.index');
        $response->assertViewHas('users');
    }

    /**
     * 管理者ユーザー詳細表示テスト
     */
    public function test_admin_can_view_user_details()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.users.show', $user->id));

        $response->assertSuccessful();
        $response->assertViewIs('admin.users.show');
        $response->assertViewHas(['user', 'stats']);
    }

    /**
     * 管理者ユーザー削除テスト
     */
    public function test_admin_can_delete_user()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin, 'admin')
                         ->delete(route('admin.users.delete', $user->id), [
                             'reason' => 'Test deletion reason'
                         ]);

        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('success');

        // ユーザーがソフトデリートされたことを確認
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    /**
     * 管理者ユーザー復活テスト
     */
    public function test_admin_can_restore_user()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        
        // ユーザーを削除
        $user->deleteByAdmin($admin->id, 'Test deletion');

        $response = $this->actingAs($admin, 'admin')
                         ->post(route('admin.users.restore', $user->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // ユーザーが復活したことを確認
        $user->refresh();
        $this->assertNull($user->deleted_at);
    }

    /**
     * 管理者チャットルーム一覧表示テスト
     */
    public function test_admin_can_view_conversations_list()
    {
        $admin = Admin::factory()->create();
        $users = User::factory()->count(2)->create();
        $chatRooms = ChatRoom::factory()->count(3)->create([
            'type' => 'friend_chat',
            'participant1_id' => $users[0]->id,
            'participant2_id' => $users[1]->id,
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.conversations'));

        $response->assertSuccessful();
        $response->assertViewIs('admin.conversations.index');
        $response->assertViewHas('chatRooms');
    }

    /**
     * 管理者チャットルーム詳細表示テスト
     */
    public function test_admin_can_view_conversation_details()
    {
        $admin = Admin::factory()->create();
        $users = User::factory()->count(2)->create();
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'friend_chat',
            'participant1_id' => $users[0]->id,
            'participant2_id' => $users[1]->id,
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.conversations.detail', $chatRoom->id));

        $response->assertSuccessful();
        $response->assertViewIs('admin.conversations.detail');
        $response->assertViewHas('chatRoom');
    }

    /**
     * 管理者メッセージ削除テスト
     */
    public function test_admin_can_delete_message()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'support_chat',
            'participant1_id' => $user->id,
        ]);
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'sender_id' => $user->id,
            'text_content' => 'Test message',
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->delete(route('admin.conversations.messages.delete', [
                             'conversationId' => $chatRoom->id,
                             'messageId' => $message->id
                         ]), [
                             'reason' => 'Inappropriate content'
                         ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // メッセージが管理者削除されたことを確認
        $message->refresh();
        $this->assertNotNull($message->admin_deleted_at);
        $this->assertEquals($admin->id, $message->admin_deleted_by);
    }

    /**
     * スーパー管理者のみ管理者作成可能テスト
     */
    public function test_only_super_admin_can_create_admin()
    {
        $superAdmin = Admin::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin, 'admin')
                         ->post(route('admin.admins.create'), [
                             'name' => 'New Admin',
                             'email' => 'newadmin@example.com',
                             'password' => 'password123',
                             'role' => 'admin',
                         ]);

        $response->assertRedirect(route('admin.admins'));
        $response->assertSessionHas('success');

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
        $admin = Admin::factory()->create(['role' => 'admin']);

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
     * 管理者操作ログ記録テスト
     */
    public function test_admin_actions_are_logged()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        // ユーザー削除操作を実行
        $this->actingAs($admin, 'admin')
             ->delete(route('admin.users.delete', $user->id), [
                 'reason' => 'Test deletion'
             ]);

        // OperationLogに記録されていることを確認
        $this->assertDatabaseHas('operation_logs', [
            'category' => 'backend',
            'action' => 'delete_user',
        ]);

        $log = OperationLog::where('category', 'backend')
                          ->where('action', 'delete_user')
                          ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString((string)$user->id, $log->description);
        $this->assertStringContainsString((string)$admin->id, $log->description);
    }

    /**
     * 管理者統計情報取得テスト
     */
    public function test_admin_can_get_dashboard_statistics()
    {
        $admin = Admin::factory()->create();
        
        // テストデータ作成
        User::factory()->count(10)->create();
        $chatRooms = ChatRoom::factory()->count(5)->create();
        Message::factory()->count(20)->create([
            'chat_room_id' => $chatRooms->first()->id,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.dashboard'));

        $response->assertSuccessful();
        
        // ビューに統計データが含まれていることを確認
        $response->assertViewHas('userCount', 10);
        $response->assertViewHas('chatRoomCount', 5);
        $response->assertViewHas('todayMessagesCount');
        $response->assertViewHas('todayActiveUsersCount');
    }

    /**
     * 管理者ログアウトテスト
     */
    public function test_admin_can_logout()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
                         ->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    /**
     * 管理者サポートチャット一覧表示テスト
     */
    public function test_admin_can_view_support_conversations()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $supportChat = ChatRoom::factory()->create([
            'type' => 'support_chat',
            'participant1_id' => $user->id,
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.support'));

        $response->assertSuccessful();
        $response->assertViewIs('admin.support.index');
        $response->assertViewHas('conversations');
    }

    /**
     * 管理者サポートチャット返信テスト
     */
    public function test_admin_can_reply_to_support_chat()
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $supportChat = ChatRoom::factory()->create([
            'type' => 'support_chat',
            'participant1_id' => $user->id,
        ]);

        $response = $this->actingAs($admin, 'admin')
                         ->post(route('admin.support.reply', $supportChat->id), [
                             'message' => 'Thank you for contacting support.',
                         ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 管理者メッセージが作成されたことを確認
        $this->assertDatabaseHas('messages', [
            'chat_room_id' => $supportChat->id,
            'admin_sender_id' => $admin->id,
            'text_content' => 'Thank you for contacting support.',
        ]);
    }

    /**
     * 管理者友達関係管理テスト
     */
    public function test_admin_can_manage_friendships()
    {
        $admin = Admin::factory()->create();
        $users = User::factory()->count(2)->create();
        $friendship = Friendship::factory()->create([
            'user_id' => $users[0]->id,
            'friend_id' => $users[1]->id,
            'status' => Friendship::STATUS_ACCEPTED,
        ]);

        // 友達関係一覧表示
        $response = $this->actingAs($admin, 'admin')
                         ->get(route('admin.friendships'));

        $response->assertSuccessful();
        $response->assertViewIs('admin.friendships.index');

        // 友達関係削除
        $response = $this->actingAs($admin, 'admin')
                         ->delete(route('admin.friendships.delete', $friendship->id), [
                             'reason' => 'Policy violation'
                         ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 友達関係がソフトデリートされたことを確認
        $this->assertSoftDeleted('friendships', ['id' => $friendship->id]);
    }
}