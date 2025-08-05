<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\GroupMember;
use App\Models\Group;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GroupMemberModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * グループメンバーの基本的な作成テスト
     */
    public function test_group_member_creation()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();

        $member = GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
            'owner_nickname' => 'テストメンバー',
        ]);

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 'member',
            'owner_nickname' => 'テストメンバー',
        ]);
    }

    /**
     * 日時のキャストテスト
     */
    public function test_datetime_casts()
    {
        $member = GroupMember::factory()->create([
            'joined_at' => '2024-01-01 10:00:00',
            'left_at' => '2024-01-02 15:30:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $member->joined_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $member->left_at);
    }

    /**
     * boolean型キャストテスト
     */
    public function test_can_rejoin_is_cast_to_boolean()
    {
        $member = GroupMember::factory()->create([
            'can_rejoin' => 1,
        ]);

        $this->assertIsBool($member->can_rejoin);
        $this->assertTrue($member->can_rejoin);
    }

    /**
     * グループリレーションテスト
     */
    public function test_group_relationship()
    {
        $group = Group::factory()->create();
        $member = GroupMember::factory()->create([
            'group_id' => $group->id,
        ]);

        $this->assertInstanceOf(Group::class, $member->group);
        $this->assertEquals($group->id, $member->group->id);
    }

    /**
     * ユーザーリレーションテスト
     */
    public function test_user_relationship()
    {
        $user = User::factory()->create();
        $member = GroupMember::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $member->user);
        $this->assertEquals($user->id, $member->user->id);
    }

    /**
     * アクティブメンバースコープテスト
     */
    public function test_active_scope()
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

        $activeMembers = GroupMember::where('group_id', $group->id)->active()->get();

        $this->assertCount(1, $activeMembers);
        $this->assertNull($activeMembers->first()->left_at);
    }

    /**
     * ロール別スコープテスト
     */
    public function test_with_role_scope()
    {
        $group = Group::factory()->create();
        
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'role' => 'owner',
        ]);
        GroupMember::factory()->count(2)->create([
            'group_id' => $group->id,
            'role' => 'member',
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'role' => 'admin',
        ]);

        $owners = GroupMember::where('group_id', $group->id)->withRole('owner')->get();
        $members = GroupMember::where('group_id', $group->id)->withRole('member')->get();
        $admins = GroupMember::where('group_id', $group->id)->withRole('admin')->get();

        $this->assertCount(1, $owners);
        $this->assertCount(2, $members);
        $this->assertCount(1, $admins);
    }

    /**
     * オーナー判定メソッドテスト
     */
    public function test_is_owner_method()
    {
        $owner = GroupMember::factory()->create(['role' => 'owner']);
        $member = GroupMember::factory()->create(['role' => 'member']);

        $this->assertTrue($owner->isOwner());
        $this->assertFalse($member->isOwner());
    }

    /**
     * 管理者判定メソッドテスト
     */
    public function test_is_admin_method()
    {
        $admin = GroupMember::factory()->create(['role' => 'admin']);
        $member = GroupMember::factory()->create(['role' => 'member']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($member->isAdmin());
    }

    /**
     * 削除実行ユーザーリレーションテスト
     */
    public function test_removed_by_user_relationship()
    {
        $remover = User::factory()->create();
        $member = GroupMember::factory()->create([
            'removed_by_user_id' => $remover->id,
        ]);

        $this->assertInstanceOf(User::class, $member->removedByUser);
        $this->assertEquals($remover->id, $member->removedByUser->id);
    }

    /**
     * 削除実行管理者リレーションテスト
     */
    public function test_removed_by_admin_relationship()
    {
        $admin = Admin::factory()->create();
        $member = GroupMember::factory()->create([
            'removed_by_admin_id' => $admin->id,
        ]);

        $this->assertInstanceOf(Admin::class, $member->removedByAdmin);
        $this->assertEquals($admin->id, $member->removedByAdmin->id);
    }

    /**
     * 削除済みメンバースコープテスト
     */
    public function test_removed_scope()
    {
        $group = Group::factory()->create();
        
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'left_at' => null,
        ]);
        GroupMember::factory()->count(2)->create([
            'group_id' => $group->id,
            'left_at' => now(),
        ]);

        $removedMembers = GroupMember::where('group_id', $group->id)->removed()->get();

        $this->assertCount(2, $removedMembers);
        $this->assertTrue($removedMembers->every(function ($member) {
            return !is_null($member->left_at);
        }));
    }

    /**
     * 再参加禁止メンバースコープテスト
     */
    public function test_cannot_rejoin_scope()
    {
        $group = Group::factory()->create();
        
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'can_rejoin' => true,
        ]);
        GroupMember::factory()->count(2)->create([
            'group_id' => $group->id,
            'can_rejoin' => false,
        ]);

        $bannedMembers = GroupMember::where('group_id', $group->id)->cannotRejoin()->get();

        $this->assertCount(2, $bannedMembers);
        $this->assertTrue($bannedMembers->every(function ($member) {
            return !$member->can_rejoin;
        }));
    }

    /**
     * メンバー削除メソッドテスト（自己退会）
     */
    public function test_remove_member_self_leave()
    {
        $user = User::factory()->create();
        $member = GroupMember::factory()->create([
            'user_id' => $user->id,
            'left_at' => null,
        ]);

        $member->removeMember(
            GroupMember::REMOVAL_TYPE_SELF_LEAVE,
            $user->id,
            true
        );

        $this->assertNotNull($member->left_at);
        $this->assertEquals(GroupMember::REMOVAL_TYPE_SELF_LEAVE, $member->removal_type);
        $this->assertEquals($user->id, $member->removed_by_user_id);
        $this->assertTrue($member->can_rejoin);
    }

    /**
     * メンバー削除メソッドテスト（オーナーによる削除）
     */
    public function test_remove_member_by_owner()
    {
        $owner = User::factory()->create();
        $member = GroupMember::factory()->create();

        $member->removeMember(
            GroupMember::REMOVAL_TYPE_KICKED_BY_OWNER,
            $owner->id,
            false
        );

        $this->assertNotNull($member->left_at);
        $this->assertEquals(GroupMember::REMOVAL_TYPE_KICKED_BY_OWNER, $member->removal_type);
        $this->assertEquals($owner->id, $member->removed_by_user_id);
        $this->assertFalse($member->can_rejoin);
    }

    /**
     * メンバー復活メソッドテスト
     */
    public function test_restore_member_method()
    {
        $member = GroupMember::factory()->create([
            'left_at' => now(),
            'removal_type' => GroupMember::REMOVAL_TYPE_SELF_LEAVE,
            'removed_by_user_id' => 1,
            'can_rejoin' => false,
        ]);

        $member->restoreMember();

        $this->assertNull($member->left_at);
        $this->assertNull($member->removal_type);
        $this->assertNull($member->removed_by_user_id);
        $this->assertTrue($member->can_rejoin);
    }

    /**
     * 削除タイプ表示名取得テスト
     */
    public function test_removal_type_display_attribute()
    {
        $selfLeave = GroupMember::factory()->create([
            'removal_type' => GroupMember::REMOVAL_TYPE_SELF_LEAVE,
        ]);
        $kickedByOwner = GroupMember::factory()->create([
            'removal_type' => GroupMember::REMOVAL_TYPE_KICKED_BY_OWNER,
        ]);
        $kickedByAdmin = GroupMember::factory()->create([
            'removal_type' => GroupMember::REMOVAL_TYPE_KICKED_BY_ADMIN,
        ]);
        $userDeleted = GroupMember::factory()->create([
            'removal_type' => GroupMember::REMOVAL_TYPE_USER_DELETED,
        ]);
        $userSelfDeleted = GroupMember::factory()->create([
            'removal_type' => GroupMember::REMOVAL_TYPE_USER_SELF_DELETED,
        ]);
        $unknown = GroupMember::factory()->create([
            'removal_type' => 'unknown_type',
        ]);

        $this->assertEquals('自己退会', $selfLeave->removal_type_display);
        $this->assertEquals('オーナーによる削除', $kickedByOwner->removal_type_display);
        $this->assertEquals('管理者による削除', $kickedByAdmin->removal_type_display);
        $this->assertEquals('ユーザー削除による自動削除', $userDeleted->removal_type_display);
        $this->assertEquals('ユーザー自己削除による自動削除', $userSelfDeleted->removal_type_display);
        $this->assertEquals('不明', $unknown->removal_type_display);
    }

    /**
     * 削除タイプ定数の値確認テスト
     */
    public function test_removal_type_constants()
    {
        $this->assertEquals('self_leave', GroupMember::REMOVAL_TYPE_SELF_LEAVE);
        $this->assertEquals('kicked_by_owner', GroupMember::REMOVAL_TYPE_KICKED_BY_OWNER);
        $this->assertEquals('kicked_by_admin', GroupMember::REMOVAL_TYPE_KICKED_BY_ADMIN);
        $this->assertEquals('user_deleted', GroupMember::REMOVAL_TYPE_USER_DELETED);
        $this->assertEquals('user_self_deleted', GroupMember::REMOVAL_TYPE_USER_SELF_DELETED);
    }
}