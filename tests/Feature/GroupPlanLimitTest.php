<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GroupPlanLimitTest extends TestCase
{
  use RefreshDatabase;

  /**
   * スタンダードプランのユーザーがグループを作成する際、上限が50人になることをテスト
   */
  public function test_standard_plan_user_creates_group_with_50_max_members()
  {
    $user = User::factory()->create(['plan' => 'standard']);
    $this->actingAs($user);

    $response = $this->postJson('/api/groups', [
      'name' => 'Test Group',
      'description' => 'Test Description',
      'chatStyles' => ['group'],
    ]);

    $response->assertStatus(201);
    $group = Group::where('owner_user_id', $user->id)->first();
    $this->assertNotNull($group);
    $this->assertEquals(50, $group->max_members);
  }

  /**
   * プレミアムプランのユーザーがグループを作成する際、上限が200人になることをテスト
   */
  public function test_premium_plan_user_creates_group_with_200_max_members()
  {
    $user = User::factory()->create(['plan' => 'premium']);
    $this->actingAs($user);

    $response = $this->postJson('/api/groups', [
      'name' => 'Test Group',
      'description' => 'Test Description',
      'chatStyles' => ['group'],
    ]);

    $response->assertStatus(201);
    $group = Group::where('owner_user_id', $user->id)->first();
    $this->assertNotNull($group);
    $this->assertEquals(200, $group->max_members);
  }

  /**
   * プレミアムからスタンダードへのダウングレードで51人以上のグループがある場合はブロックされることをテスト
   */
  public function test_downgrade_blocked_when_group_has_more_than_50_members()
  {
    // プレミアムユーザーを作成
    $user = User::factory()->create(['plan' => 'premium']);

    // 51人のメンバーがいるグループを作成
    $group = Group::factory()->create([
      'owner_user_id' => $user->id,
      'max_members' => 200,
    ]);

    // 51人のメンバーを追加（オーナー含む）
    GroupMember::factory()->create([
      'group_id' => $group->id,
      'user_id' => $user->id,
      'role' => 'owner',
    ]);

    for ($i = 0; $i < 50; $i++) {
      $member = User::factory()->create();
      GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'role' => 'member',
      ]);
    }

    // ダウングレードのリクエストをシミュレート（実際のStripe処理は除く）
    $this->actingAs($user);
    $response = $this->postJson('/api/stripe/create-checkout-session', [
      'plan' => 'standard',
    ]);

    // エラーレスポンスを確認
    $response->assertStatus(500);
    $response->assertJson([
      'status' => 'error',
      'error_type' => 'downgrade_blocked',
      'message' => 'グループ編集ページでメンバーの人数を50人以下にしてください。',
    ]);
  }
}
