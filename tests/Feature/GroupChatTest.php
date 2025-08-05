<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * グループ作成テスト
     */
    public function test_user_can_create_group()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/groups', [
            'name' => 'Test Group',
            'description' => 'This is a test group',
            'max_members' => 50,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'description',
            'owner_user_id',
            'qr_code_token',
            'chat_room' => ['id', 'room_token'],
        ]);

        // グループが作成されたことを確認
        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'owner_user_id' => $user->id,
        ]);

        // チャットルームが作成されたことを確認
        $group = Group::where('name', 'Test Group')->first();
        $this->assertDatabaseHas('chat_rooms', [
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        // オーナーがメンバーとして追加されたことを確認
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    /**
     * グループへのメンバー招待テスト
     */
    public function test_owner_can_invite_members_to_group()
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $group = Group::factory()->create(['owner_user_id' => $owner->id]);
        
        // オーナーをグループに追加
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/groups/{$group->id}/members", [
            'user_id' => $newMember->id,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'Member added successfully']);

        // メンバーが追加されたことを確認
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $newMember->id,
            'role' => 'member',
        ]);
    }

    /**
     * 非オーナーはメンバーを招待できないテスト
     */
    public function test_non_owner_cannot_invite_members()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $newMember = User::factory()->create();
        $group = Group::factory()->create(['owner_user_id' => $owner->id]);

        // メンバーとして追加
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($member);

        $response = $this->postJson("/api/groups/{$group->id}/members", [
            'user_id' => $newMember->id,
        ]);

        $response->assertStatus(403);
    }

    /**
     * グループメッセージ送信テスト
     */
    public function test_member_can_send_message_to_group()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        // ユーザーをグループメンバーに追加
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
            'text_content' => 'Hello group!',
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('messages', [
            'chat_room_id' => $chatRoom->id,
            'sender_id' => $user->id,
            'text_content' => 'Hello group!',
        ]);
    }

    /**
     * 非メンバーはグループメッセージを送信できないテスト
     */
    public function test_non_member_cannot_send_message_to_group()
    {
        $nonMember = User::factory()->create();
        $group = Group::factory()->create();
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        Sanctum::actingAs($nonMember);

        $response = $this->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
            'text_content' => 'This should fail',
        ]);

        $response->assertStatus(403);
    }

    /**
     * グループメンバー一覧取得テスト
     */
    public function test_member_can_get_group_members_list()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        
        // 複数のメンバーを追加
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 'member']);
        GroupMember::factory()->count(3)->create(['group_id' => $group->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/groups/{$group->id}/members");

        $response->assertStatus(200);
        $response->assertJsonCount(4, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'user_id', 'role', 'joined_at', 'user' => ['id', 'name']],
            ],
        ]);
    }

    /**
     * グループからの退会テスト
     */
    public function test_member_can_leave_group()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/groups/{$group->id}/leave");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Left group successfully']);

        // メンバーが退会したことを確認
        $member = GroupMember::where('group_id', $group->id)
                            ->where('user_id', $user->id)
                            ->first();
        
        $this->assertNotNull($member->left_at);
        $this->assertEquals('self_leave', $member->removal_type);
        $this->assertTrue($member->can_rejoin);
    }

    /**
     * オーナーによるメンバー削除テスト
     */
    public function test_owner_can_remove_member()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::factory()->create(['owner_user_id' => $owner->id]);
        
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 'member']);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/groups/{$group->id}/members/{$member->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Member removed successfully']);

        // メンバーが削除されたことを確認
        $removedMember = GroupMember::where('group_id', $group->id)
                                   ->where('user_id', $member->id)
                                   ->first();
        
        $this->assertNotNull($removedMember->left_at);
        $this->assertEquals('kicked_by_owner', $removedMember->removal_type);
        $this->assertEquals($owner->id, $removedMember->removed_by_user_id);
        $this->assertFalse($removedMember->can_rejoin);
    }

    /**
     * 最大メンバー数制限テスト
     */
    public function test_group_cannot_exceed_max_members()
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
            'max_members' => 3, // 最大3人
        ]);

        // オーナーと2人のメンバーを追加（計3人）
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner']);
        GroupMember::factory()->count(2)->create(['group_id' => $group->id]);

        Sanctum::actingAs($owner);

        // 4人目を追加しようとする
        $newMember = User::factory()->create();
        $response = $this->postJson("/api/groups/{$group->id}/members", [
            'user_id' => $newMember->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Group has reached maximum capacity']);
    }

    /**
     * グループのソフトデリートテスト
     */
    public function test_owner_can_delete_group()
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create(['owner_user_id' => $owner->id]);
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role' => 'owner']);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/groups/{$group->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Group deleted successfully']);

        // グループがソフトデリートされたことを確認
        $this->assertSoftDeleted('groups', ['id' => $group->id]);
        
        // チャットルームもソフトデリートされたことを確認
        $this->assertSoftDeleted('chat_rooms', ['id' => $chatRoom->id]);
    }

    /**
     * QRコードによるグループ参加テスト
     */
    public function test_user_can_join_group_by_qr_code()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/groups/join', [
            'qr_code_token' => $group->qr_code_token,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Joined group successfully']);

        // メンバーとして追加されたことを確認
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }
}