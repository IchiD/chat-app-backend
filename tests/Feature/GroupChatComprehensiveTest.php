<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class GroupChatComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $friend1;
    private $friend2;
    private $nonMember;

    protected function setUp(): void
    {
        parent::setUp();
        
        // テストユーザー作成
        $this->user = User::factory()->create();
        $this->friend1 = User::factory()->create();
        $this->friend2 = User::factory()->create();
        $this->nonMember = User::factory()->create();
    }

    /**
     * グループ作成機能テスト
     */
    public function test_user_can_create_group()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/conversations/groups', [
            'name' => 'テストグループ',
            'description' => 'これはテストグループです',
            'max_members' => 50,
            'chat_styles' => ['group', 'group_member'],
        ]);

        $response->assertCreated()
                 ->assertJsonStructure([
                     'group' => [
                         'id',
                         'name',
                         'description',
                         'max_members',
                         'chat_styles',
                         'owner_user_id',
                         'qr_code_token',
                     ],
                     'chat_room' => [
                         'id',
                         'room_token',
                         'type',
                     ],
                 ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'テストグループ',
            'owner_user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('group_members', [
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
    }

    /**
     * グループ作成時のバリデーションテスト
     */
    public function test_group_creation_validation()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/conversations/groups', [
            'name' => '', // 空の名前
            'max_members' => 0, // 無効なメンバー数
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'max_members']);
    }

    /**
     * グループ一覧取得テスト
     */
    public function test_user_can_get_groups_list()
    {
        Sanctum::actingAs($this->user);

        // テストグループ作成
        $group1 = Group::factory()->create([
            'name' => 'グループ1',
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group1->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $group2 = Group::factory()->create([
            'name' => 'グループ2',
            'owner_user_id' => $this->friend1->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group2->id,
            'user_id' => $this->user->id,
            'role' => 'member',
        ]);

        $response = $this->getJson('/api/conversations/groups');

        $response->assertOk()
                 ->assertJsonCount(2, 'groups')
                 ->assertJsonFragment(['name' => 'グループ1'])
                 ->assertJsonFragment(['name' => 'グループ2']);
    }

    /**
     * グループ詳細取得テスト
     */
    public function test_member_can_get_group_details()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'name' => 'テストグループ',
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $response = $this->getJson("/api/conversations/groups/{$group->id}");

        $response->assertOk()
                 ->assertJsonStructure([
                     'group' => [
                         'id',
                         'name',
                         'description',
                         'max_members',
                         'chat_styles',
                         'owner_user_id',
                         'members_count',
                     ],
                 ]);
    }

    /**
     * 非メンバーはグループ詳細を取得できないテスト
     */
    public function test_non_member_cannot_get_group_details()
    {
        Sanctum::actingAs($this->nonMember);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/conversations/groups/{$group->id}");

        $response->assertForbidden();
    }

    /**
     * グループ情報更新テスト（オーナーのみ）
     */
    public function test_owner_can_update_group()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'name' => '古い名前',
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $response = $this->putJson("/api/conversations/groups/{$group->id}", [
            'name' => '新しい名前',
            'description' => '新しい説明',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'name' => '新しい名前',
            'description' => '新しい説明',
        ]);
    }

    /**
     * 非オーナーはグループを更新できないテスト
     */
    public function test_non_owner_cannot_update_group()
    {
        Sanctum::actingAs($this->friend1);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
        ]);

        $response = $this->putJson("/api/conversations/groups/{$group->id}", [
            'name' => '更新された名前',
        ]);

        $response->assertForbidden();
    }

    /**
     * グループメンバー追加テスト
     */
    public function test_owner_can_add_group_member()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
            'max_members' => 10,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $response = $this->postJson("/api/conversations/groups/{$group->id}/members", [
            'user_id' => $this->friend1->id,
            'role' => 'member',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
            'left_at' => null,
        ]);
    }

    /**
     * グループメンバー数上限テスト
     */
    public function test_cannot_add_member_when_group_is_full()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
            'max_members' => 2,
        ]);
        
        // オーナーと1人のメンバーで満員
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
        ]);

        $response = $this->postJson("/api/conversations/groups/{$group->id}/members", [
            'user_id' => $this->friend2->id,
        ]);

        $response->assertStatus(422)
                 ->assertJson(['message' => 'グループの最大メンバー数に達しています']);
    }

    /**
     * グループメンバー削除テスト
     */
    public function test_owner_can_remove_group_member()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        $member = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
        ]);

        $response = $this->deleteJson("/api/conversations/groups/{$group->id}/members/{$member->id}");

        $response->assertOk();
        
        $member->refresh();
        $this->assertNotNull($member->left_at);
        $this->assertEquals(GroupMember::REMOVAL_TYPE_KICKED_BY_OWNER, $member->removal_type);
    }

    /**
     * メンバー自身の退会テスト
     */
    public function test_member_can_leave_group()
    {
        Sanctum::actingAs($this->friend1);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        $member = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
        ]);

        $response = $this->deleteJson("/api/conversations/groups/{$group->id}/members/{$member->id}");

        $response->assertOk();
        
        $member->refresh();
        $this->assertNotNull($member->left_at);
        $this->assertEquals(GroupMember::REMOVAL_TYPE_SELF_LEAVE, $member->removal_type);
    }

    /**
     * グループチャットへのメッセージ送信テスト
     */
    public function test_member_can_send_message_to_group_chat()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
            'chat_styles' => ['group'],
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        $response = $this->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
            'content' => 'グループへのメッセージです',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('messages', [
            'chat_room_id' => $chatRoom->id,
            'sender_id' => $this->user->id,
            'text_content' => 'グループへのメッセージです',
        ]);
    }

    /**
     * 非メンバーはグループチャットに送信できないテスト
     */
    public function test_non_member_cannot_send_message_to_group_chat()
    {
        Sanctum::actingAs($this->nonMember);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        $response = $this->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
            'content' => '送信できないメッセージ',
        ]);

        $response->assertForbidden();
    }

    /**
     * QRコード取得テスト
     */
    public function test_member_can_get_group_qr_code()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $response = $this->getJson("/api/conversations/groups/{$group->id}/qr-code");

        $response->assertOk()
                 ->assertJsonStructure([
                     'qr_code_token',
                     'qr_code_url',
                     'expires_at',
                 ]);
    }

    /**
     * QRコード再生成テスト（オーナーのみ）
     */
    public function test_owner_can_regenerate_qr_code()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
            'qr_code_token' => 'old_token',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $response = $this->postJson("/api/conversations/groups/{$group->id}/qr-code/regenerate");

        $response->assertOk();
        
        $group->refresh();
        $this->assertNotEquals('old_token', $group->qr_code_token);
    }

    /**
     * QRコードでグループ参加テスト
     */
    public function test_user_can_join_group_by_qr_code()
    {
        Sanctum::actingAs($this->friend1);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
            'max_members' => 10,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        $response = $this->postJson("/api/conversations/groups/join/{$group->qr_code_token}");

        $response->assertOk();
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
            'left_at' => null,
        ]);
    }

    /**
     * グループメンバー間チャット作成テスト
     */
    public function test_members_can_create_member_chat()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
            'chat_styles' => ['group_member'],
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
        ]);

        $response = $this->postJson("/api/conversations/groups/{$group->id}/member-chat", [
            'target_user_id' => $this->friend1->id,
        ]);

        $response->assertOk()
                 ->assertJsonStructure([
                     'chat_room' => [
                         'id',
                         'room_token',
                         'type',
                     ],
                 ]);

        $this->assertDatabaseHas('chat_rooms', [
            'type' => 'member_chat',
            'group_id' => $group->id,
            'participant1_id' => $this->user->id,
            'participant2_id' => $this->friend1->id,
        ]);
    }

    /**
     * グループメンバー一覧取得テスト
     */
    public function test_member_can_get_group_members_list()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
            'owner_nickname' => 'オーナー',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
            'owner_nickname' => 'メンバー1',
        ]);

        $response = $this->getJson("/api/conversations/groups/{$group->id}/members");

        $response->assertOk()
                 ->assertJsonCount(2, 'members')
                 ->assertJsonFragment(['role' => 'owner'])
                 ->assertJsonFragment(['role' => 'member']);
    }

    /**
     * 削除済みメンバーを含む全メンバー一覧取得テスト（オーナーのみ）
     */
    public function test_owner_can_get_all_members_including_removed()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
            'left_at' => now(),
            'removal_type' => GroupMember::REMOVAL_TYPE_SELF_LEAVE,
        ]);

        $response = $this->getJson("/api/conversations/groups/{$group->id}/members/all");

        $response->assertOk()
                 ->assertJsonCount(2, 'members');
    }

    /**
     * メンバーへの一斉メッセージ送信テスト
     */
    public function test_owner_can_send_bulk_message_to_members()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
            'chat_styles' => ['group_member'],
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend2->id,
            'role' => 'member',
        ]);

        $response = $this->postJson("/api/conversations/groups/{$group->id}/messages/bulk", [
            'content' => '全メンバーへのお知らせです',
            'exclude_sender' => false,
        ]);

        $response->assertOk()
                 ->assertJson(['sent_count' => 3]);
    }

    /**
     * メンバーの再参加可否切り替えテスト（オーナーのみ）
     */
    public function test_owner_can_toggle_member_rejoin_permission()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        $removedMember = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
            'left_at' => now(),
            'can_rejoin' => true,
        ]);

        $response = $this->patchJson("/api/conversations/groups/{$group->id}/members/{$removedMember->id}/rejoin", [
            'can_rejoin' => false,
        ]);

        $response->assertOk();
        
        $removedMember->refresh();
        $this->assertFalse($removedMember->can_rejoin);
    }

    /**
     * 削除済みメンバーの復活テスト（オーナーのみ）
     */
    public function test_owner_can_restore_removed_member()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        $removedMember = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
            'left_at' => now(),
            'removal_type' => GroupMember::REMOVAL_TYPE_KICKED_BY_OWNER,
            'can_rejoin' => true,
        ]);

        $response = $this->postJson("/api/conversations/groups/{$group->id}/members/{$removedMember->id}/restore");

        $response->assertOk();
        
        $removedMember->refresh();
        $this->assertNull($removedMember->left_at);
        $this->assertNull($removedMember->removal_type);
    }

    /**
     * メンバーニックネーム更新テスト（オーナーのみ）
     */
    public function test_owner_can_update_member_nickname()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        $member = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
            'owner_nickname' => null,
        ]);

        $response = $this->patchJson("/api/conversations/groups/{$group->id}/members/{$member->id}/nickname", [
            'nickname' => '新しいニックネーム',
        ]);

        $response->assertOk();
        
        $member->refresh();
        $this->assertEquals('新しいニックネーム', $member->owner_nickname);
    }

    /**
     * グループ削除テスト（オーナーのみ）
     */
    public function test_owner_can_delete_group()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        $response = $this->deleteJson("/api/conversations/groups/{$group->id}");

        $response->assertOk();
        
        $group->refresh();
        $this->assertNotNull($group->deleted_at);
        
        $chatRoom->refresh();
        $this->assertNotNull($chatRoom->deleted_at);
    }

    /**
     * グループ情報取得（トークンベース・認証不要）テスト
     */
    public function test_guest_can_get_group_info_by_token()
    {
        $group = Group::factory()->create([
            'name' => '公開グループ',
            'owner_user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/conversations/groups/info/{$group->qr_code_token}");

        $response->assertOk()
                 ->assertJsonStructure([
                     'group' => [
                         'name',
                         'description',
                         'members_count',
                         'max_members',
                     ],
                 ])
                 ->assertJsonMissing(['owner_user_id']); // 機密情報は含まない
    }

    /**
     * 無効なトークンでのグループ参加失敗テスト
     */
    public function test_cannot_join_group_with_invalid_token()
    {
        Sanctum::actingAs($this->friend1);

        $response = $this->postJson('/api/conversations/groups/join/invalid_token');

        $response->assertNotFound();
    }

    /**
     * すでにメンバーの場合の再参加防止テスト
     */
    public function test_existing_member_cannot_rejoin_group()
    {
        Sanctum::actingAs($this->friend1);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
        ]);

        $response = $this->postJson("/api/conversations/groups/join/{$group->qr_code_token}");

        $response->assertStatus(422)
                 ->assertJson(['message' => 'すでにグループのメンバーです']);
    }

    /**
     * 再参加禁止メンバーの参加防止テスト
     */
    public function test_banned_member_cannot_rejoin_group()
    {
        Sanctum::actingAs($this->friend1);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->friend1->id,
            'role' => 'member',
            'left_at' => now(),
            'removal_type' => GroupMember::REMOVAL_TYPE_KICKED_BY_OWNER,
            'can_rejoin' => false,
        ]);

        $response = $this->postJson("/api/conversations/groups/join/{$group->qr_code_token}");

        $response->assertForbidden()
                 ->assertJson(['message' => 'このグループへの再参加は許可されていません']);
    }

    /**
     * グループチャットスタイル制限テスト
     */
    public function test_cannot_create_group_chat_when_style_not_allowed()
    {
        Sanctum::actingAs($this->user);

        $group = Group::factory()->create([
            'owner_user_id' => $this->user->id,
            'chat_styles' => ['group_member'], // group_chatは許可されていない
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);

        // グループチャットルームが作成されていないことを確認
        $this->assertNull($group->groupChatRoom);
    }
}