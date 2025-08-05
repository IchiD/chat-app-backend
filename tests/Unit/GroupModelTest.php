<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class GroupModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * グループ作成時にQRコードトークンが自動生成されるテスト
     */
    public function test_group_generates_qr_code_token_on_creation()
    {
        $group = Group::factory()->create();

        $this->assertNotNull($group->qr_code_token);
        $this->assertEquals(32, strlen($group->qr_code_token));
    }

    /**
     * QRコードトークンの一意性テスト
     */
    public function test_qr_code_token_is_unique()
    {
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();

        $this->assertNotEquals($group1->qr_code_token, $group2->qr_code_token);
    }

    /**
     * chat_stylesのキャストテスト
     */
    public function test_chat_styles_is_cast_to_array()
    {
        $group = Group::factory()->create([
            'chat_styles' => ['group', 'group_member'],
        ]);

        $this->assertIsArray($group->chat_styles);
        $this->assertContains('group', $group->chat_styles);
        $this->assertContains('group_member', $group->chat_styles);
    }

    /**
     * オーナーリレーションテスト
     */
    public function test_owner_relationship()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $group->owner);
        $this->assertEquals($user->id, $group->owner->id);
    }

    /**
     * 削除されたオーナーも取得できるテスト
     */
    public function test_owner_relationship_includes_trashed_users()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $user->id,
        ]);
        
        $user->delete();

        $group->refresh();
        $this->assertInstanceOf(User::class, $group->owner);
        $this->assertTrue($group->owner->trashed());
    }

    /**
     * グループメンバーリレーションテスト
     */
    public function test_group_members_relationship()
    {
        $group = Group::factory()->create();
        $members = GroupMember::factory()->count(3)->create([
            'group_id' => $group->id,
        ]);

        $this->assertCount(3, $group->groupMembers);
        $this->assertInstanceOf(GroupMember::class, $group->groupMembers->first());
    }

    /**
     * アクティブメンバーのみ取得テスト
     */
    public function test_active_members_excludes_left_members()
    {
        $group = Group::factory()->create();
        
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'left_at' => null,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'left_at' => now(),
        ]);

        $this->assertCount(1, $group->activeMembers);
    }

    /**
     * メンバー存在確認メソッドテスト
     */
    public function test_has_member_method()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();
        $nonMember = User::factory()->create();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($group->hasMember($user->id));
        $this->assertFalse($group->hasMember($nonMember->id));
    }

    /**
     * メンバー数取得メソッドテスト
     */
    public function test_get_members_count_method()
    {
        $group = Group::factory()->create();
        
        GroupMember::factory()->count(3)->create([
            'group_id' => $group->id,
            'left_at' => null,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'left_at' => now(),
        ]);

        $this->assertEquals(3, $group->getMembersCount());
    }

    /**
     * グループチャットスタイル確認メソッドテスト
     */
    public function test_chat_style_check_methods()
    {
        $groupWithBoth = Group::factory()->create([
            'chat_styles' => ['group', 'group_member'],
        ]);

        $groupOnlyMember = Group::factory()->create([
            'chat_styles' => ['group_member'],
        ]);

        $this->assertTrue($groupWithBoth->hasGroupChat());
        $this->assertTrue($groupWithBoth->hasMemberChat());
        
        $this->assertFalse($groupOnlyMember->hasGroupChat());
        $this->assertTrue($groupOnlyMember->hasMemberChat());
    }

    /**
     * QRコードトークン再生成メソッドテスト
     */
    public function test_regenerate_qr_token_method()
    {
        $group = Group::factory()->create([
            'qr_code_token' => 'old_token',
        ]);

        $group->regenerateQrToken();

        $this->assertNotEquals('old_token', $group->qr_code_token);
        $this->assertEquals(32, strlen($group->qr_code_token));
    }

    /**
     * メンバー追加可能チェックメソッドテスト
     */
    public function test_can_add_member_method()
    {
        $group = Group::factory()->create([
            'max_members' => 2,
        ]);

        GroupMember::factory()->create([
            'group_id' => $group->id,
        ]);

        $this->assertTrue($group->canAddMember());

        GroupMember::factory()->create([
            'group_id' => $group->id,
        ]);

        $this->assertFalse($group->canAddMember());
    }

    /**
     * グループ削除状態チェックメソッドテスト
     */
    public function test_is_deleted_method()
    {
        $activeGroup = Group::factory()->create([
            'deleted_at' => null,
        ]);

        $deletedGroup = Group::factory()->create([
            'deleted_at' => now(),
        ]);

        $this->assertFalse($activeGroup->isDeleted());
        $this->assertTrue($deletedGroup->isDeleted());
    }

    /**
     * 管理者によるグループ削除テスト
     */
    public function test_delete_by_admin_method()
    {
        $admin = Admin::factory()->create();
        $group = Group::factory()->create();
        
        // グループチャットルーム作成
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        $result = $group->deleteByAdmin($admin->id, '規約違反');

        $this->assertTrue($result);
        $this->assertNotNull($group->deleted_at);
        $this->assertEquals($admin->id, $group->deleted_by);
        $this->assertEquals('規約違反', $group->deleted_reason);

        // 関連チャットルームも削除されることを確認
        $chatRoom->refresh();
        $this->assertNotNull($chatRoom->deleted_at);
    }

    /**
     * ユーザー自身によるグループ削除テスト
     */
    public function test_delete_by_self_method()
    {
        $group = Group::factory()->create();
        
        $result = $group->deleteBySelf('オーナーアカウント削除');

        $this->assertTrue($result);
        $this->assertNotNull($group->deleted_at);
        $this->assertNull($group->deleted_by);
        $this->assertEquals('オーナーアカウント削除', $group->deleted_reason);
    }

    /**
     * 管理者によるグループ復活テスト
     */
    public function test_restore_by_admin_method()
    {
        $admin = Admin::factory()->create();
        $group = Group::factory()->create();
        
        // グループと関連チャットルームを削除
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);
        
        $group->deleteByAdmin($admin->id, 'テスト削除');

        // グループを復活
        $result = $group->restoreByAdmin();

        $this->assertTrue($result);
        $this->assertNull($group->deleted_at);
        $this->assertNull($group->deleted_by);
        $this->assertNull($group->deleted_reason);

        // 自動削除されたチャットルームも復活することを確認
        $chatRoom->refresh();
        $this->assertNull($chatRoom->deleted_at);
    }

    /**
     * グループチャットルームリレーションテスト
     */
    public function test_group_chat_room_relationship()
    {
        $group = Group::factory()->create();
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        $groupChatRoom = $group->groupChatRoom;

        $this->assertInstanceOf(ChatRoom::class, $groupChatRoom);
        $this->assertEquals($chatRoom->id, $groupChatRoom->id);
        $this->assertEquals('group_chat', $groupChatRoom->type);
    }

    /**
     * メンバー間チャットルームリレーションテスト
     */
    public function test_member_chat_rooms_relationship()
    {
        $group = Group::factory()->create();
        
        ChatRoom::factory()->count(2)->create([
            'type' => 'member_chat',
            'group_id' => $group->id,
        ]);
        ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        $this->assertCount(2, $group->memberChatRooms);
        $this->assertTrue($group->memberChatRooms->every(function ($room) {
            return $room->type === 'member_chat';
        }));
    }

    /**
     * メンバーユーザー直接取得リレーションテスト
     */
    public function test_members_relationship()
    {
        $group = Group::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $index => $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $index === 0 ? 'owner' : 'member',
                'owner_nickname' => "メンバー{$index}",
            ]);
        }

        $members = $group->members;

        $this->assertCount(3, $members);
        $this->assertInstanceOf(User::class, $members->first());
        
        // ピボットデータの確認
        $firstMember = $members->first();
        $this->assertNotNull($firstMember->pivot->joined_at);
        $this->assertNull($firstMember->pivot->left_at);
        $this->assertEquals('owner', $firstMember->pivot->role);
        $this->assertEquals('メンバー0', $firstMember->pivot->owner_nickname);
    }

    /**
     * 削除されたメンバーは members リレーションに含まれないテスト
     */
    public function test_members_relationship_excludes_left_members()
    {
        $group = Group::factory()->create();
        $activeUser = User::factory()->create();
        $leftUser = User::factory()->create();

        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $activeUser->id,
            'left_at' => null,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $leftUser->id,
            'left_at' => now(),
        ]);

        $members = $group->members;

        $this->assertCount(1, $members);
        $this->assertEquals($activeUser->id, $members->first()->id);
    }

    /**
     * ソフトデリートテスト
     */
    public function test_group_soft_deletes()
    {
        $group = Group::factory()->create();
        $groupId = $group->id;

        $group->delete();

        $this->assertSoftDeleted('groups', ['id' => $groupId]);
        
        // withTrashed で取得可能
        $trashedGroup = Group::withTrashed()->find($groupId);
        $this->assertNotNull($trashedGroup);
        $this->assertTrue($trashedGroup->trashed());
    }
}