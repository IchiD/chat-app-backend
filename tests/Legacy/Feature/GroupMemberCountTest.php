<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Admin;

class GroupMemberCountTest extends TestCase
{
  use RefreshDatabase;

  /**
   * 削除されたユーザーがメンバー数カウントから除外されることを確認
   */
  public function test_deleted_users_are_excluded_from_member_count()
  {
    // オーナーユーザーを作成
    $owner = User::factory()->create();

    // グループを作成（最大10人）
    $group = Group::create([
      'name' => 'Test Group',
      'description' => 'Test Description',
      'max_members' => 10,
      'chat_styles' => ['group'],
      'owner_user_id' => $owner->id,
    ]);

    // オーナーをメンバーとして追加
    GroupMember::create([
      'group_id' => $group->id,
      'user_id' => $owner->id,
      'role' => 'owner',
      'joined_at' => now(),
    ]);

    // 通常のメンバーを5人追加
    $members = [];
    for ($i = 0; $i < 5; $i++) {
      $member = User::factory()->create();
      $members[] = $member;
      GroupMember::create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
      ]);
    }

    // この時点でメンバー数は6人（オーナー + 5人）
    $this->assertEquals(6, $group->getMembersCount());
    $this->assertTrue($group->canAddMember());

    // 1人目のメンバーを管理者によって削除（バン）
    $admin = Admin::factory()->create();
    $members[0]->banByAdmin($admin->id, 'Test ban');

    // 削除後のメンバー数は5人になるはず
    $this->assertEquals(5, $group->getMembersCount());
    $this->assertTrue($group->canAddMember());

    // 2人目のメンバーを自己削除
    $members[1]->softDeleteByUser('Test self delete');

    // 削除後のメンバー数は4人になるはず
    $this->assertEquals(4, $group->getMembersCount());
    $this->assertTrue($group->canAddMember());

    // 3人目のメンバーをグループから削除（left_atを設定）
    $groupMember = GroupMember::where('group_id', $group->id)
      ->where('user_id', $members[2]->id)
      ->first();
    $groupMember->removeMember('kicked_by_owner', $owner->id, false);

    // 削除後のメンバー数は3人になるはず
    $this->assertEquals(3, $group->getMembersCount());
    $this->assertTrue($group->canAddMember());

    // アクティブなメンバーを取得して確認
    $activeMembers = $group->activeMembers()->get();
    $this->assertEquals(3, $activeMembers->count());

    // アクティブなメンバーのユーザーIDを確認
    $activeUserIds = $activeMembers->pluck('user_id')->toArray();
    $this->assertContains($owner->id, $activeUserIds);
    $this->assertContains($members[3]->id, $activeUserIds);
    $this->assertContains($members[4]->id, $activeUserIds);
    $this->assertNotContains($members[0]->id, $activeUserIds); // バンされたユーザー
    $this->assertNotContains($members[1]->id, $activeUserIds); // 自己削除したユーザー
    $this->assertNotContains($members[2]->id, $activeUserIds); // グループから削除されたユーザー
  }

  /**
   * メンバー数上限のチェックが正しく動作することを確認
   */
  public function test_member_limit_check_works_correctly()
  {
    // オーナーユーザーを作成
    $owner = User::factory()->create();

    // グループを作成（最大3人）
    $group = Group::create([
      'name' => 'Small Group',
      'description' => 'Test Description',
      'max_members' => 3,
      'chat_styles' => ['group'],
      'owner_user_id' => $owner->id,
    ]);

    // オーナーをメンバーとして追加
    GroupMember::create([
      'group_id' => $group->id,
      'user_id' => $owner->id,
      'role' => 'owner',
      'joined_at' => now(),
    ]);

    // 2人のメンバーを追加（合計3人で上限）
    for ($i = 0; $i < 2; $i++) {
      $member = User::factory()->create();
      GroupMember::create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'role' => 'member',
        'joined_at' => now(),
      ]);
    }

    // 上限に達しているので追加できない
    $this->assertEquals(3, $group->getMembersCount());
    $this->assertFalse($group->canAddMember());

    // APIエンドポイントをテスト
    $this->actingAs($owner, 'sanctum');
    $newUser = User::factory()->create();

    $response = $this->postJson("/api/conversations/groups/{$group->id}/members", [
      'friend_id' => $newUser->friend_id,
    ]);

    $response->assertStatus(422)
      ->assertJson(['message' => 'メンバー数が上限に達しています']);
  }
}
