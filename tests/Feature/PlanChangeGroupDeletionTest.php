<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ChatRoom;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class PlanChangeGroupDeletionTest extends TestCase
{
  use RefreshDatabase;

  /**
   * Stripeのwebhookでfreeプランに変更された際にグループが削除されることをテスト
   */
  public function test_groups_are_deleted_when_subscription_canceled_to_free_via_webhook()
  {
    // ユーザーとグループを作成
    $user = User::factory()->create(['plan' => 'standard', 'subscription_status' => 'active']);
    $subscription = Subscription::factory()->create([
      'user_id' => $user->id,
      'plan' => 'standard',
      'status' => 'active',
      'stripe_subscription_id' => 'sub_test123',
    ]);

    $group1 = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Group 1',
      'max_members' => 50,
    ]);

    $group2 = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Group 2',
      'max_members' => 50,
    ]);

    // グループに関連するチャットルームを作成
    $chatRoom1 = ChatRoom::factory()->create([
      'group_id' => $group1->id,
      'type' => 'group_chat',
    ]);

    $chatRoom2 = ChatRoom::factory()->create([
      'group_id' => $group2->id,
      'type' => 'group_chat',
    ]);

    // Webhookイベントをシミュレート
    $stripeService = new StripeService();
    $stripeService->handleWebhook('customer.subscription.deleted', [
      'id' => 'sub_test123',
      'status' => 'canceled',
      'current_period_end' => time(),
    ]);

    // ユーザーの状態を確認
    $user->refresh();
    $this->assertEquals('free', $user->plan);
    $this->assertEquals('canceled', $user->subscription_status);

    // グループが論理削除されていることを確認
    $group1->refresh();
    $group2->refresh();
    $this->assertNotNull($group1->deleted_at);
    $this->assertNotNull($group2->deleted_at);
    $this->assertEquals('プラン解約によるグループ削除（Freeプランではグループ機能が利用できません）', $group1->deleted_reason);
    $this->assertEquals('プラン解約によるグループ削除（Freeプランではグループ機能が利用できません）', $group2->deleted_reason);

    // チャットルームも削除されていることを確認
    $chatRoom1->refresh();
    $chatRoom2->refresh();
    $this->assertNotNull($chatRoom1->deleted_at);
    $this->assertNotNull($chatRoom2->deleted_at);
    $this->assertStringContainsString('グループ削除に伴う自動削除', $chatRoom1->deleted_reason);
    $this->assertStringContainsString('グループ削除に伴う自動削除', $chatRoom2->deleted_reason);
  }

  /**
   * プレミアムからスタンダードへの変更ではグループが削除されないことをテスト
   */
  public function test_groups_are_not_deleted_when_downgrading_from_premium_to_standard()
  {
    // ユーザーとグループを作成
    $user = User::factory()->create(['plan' => 'premium', 'subscription_status' => 'active']);
    $subscription = Subscription::factory()->create([
      'user_id' => $user->id,
      'plan' => 'premium',
      'status' => 'active',
      'stripe_subscription_id' => 'sub_test456',
    ]);

    $group = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Premium Group',
      'max_members' => 200,
    ]);

    // Webhookイベントをシミュレート（プラン変更）
    $stripeService = new StripeService();
    $stripeService->handleWebhook('customer.subscription.updated', [
      'id' => 'sub_test456',
      'status' => 'active',
      'items' => [
        'data' => [
          ['price' => ['product' => 'prod_standard']]
        ]
      ],
      'metadata' => ['plan' => 'standard'],
    ]);

    // サブスクリプションを手動で更新（実際のWebhookハンドラーの動作をシミュレート）
    $subscription->update(['plan' => 'standard']);
    $user->update(['plan' => 'standard']);

    // グループが削除されていないことを確認
    $group->refresh();
    $this->assertNull($group->deleted_at);
    $this->assertEquals(50, $group->max_members); // 上限は変更される
  }

  /**
   * フリープランのユーザーはグループを作成できないことをテスト
   */
  public function test_free_plan_user_cannot_create_group()
  {
    $user = User::factory()->create(['plan' => 'free']);
    $this->actingAs($user);

    $response = $this->postJson('/api/groups', [
      'name' => 'Test Group',
      'description' => 'Test Description',
      'chatStyles' => ['group'],
    ]);

    $response->assertStatus(403);
    $response->assertJson([
      'status' => 'error',
      'message' => 'グループ機能を利用するには有料プランへのアップグレードが必要です',
    ]);

    // グループが作成されていないことを確認
    $groupCount = Group::where('owner_user_id', $user->id)->count();
    $this->assertEquals(0, $groupCount);
  }

  /**
   * 既にグループを持つユーザーがfreeプランに変更された場合、新しいグループは作成できないことをテスト
   */
  public function test_user_with_deleted_groups_cannot_create_new_group_on_free_plan()
  {
    // スタンダードプランのユーザーとグループを作成
    $user = User::factory()->create(['plan' => 'standard']);
    $group = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Original Group',
      'max_members' => 50,
    ]);

    // プランをfreeに変更してグループを論理削除
    $user->update(['plan' => 'free']);
    $group->deleteBySelf('プラン解約によるグループ削除（Freeプランではグループ機能が利用できません）');

    // 新しいグループを作成しようとする
    $this->actingAs($user);
    $response = $this->postJson('/api/groups', [
      'name' => 'New Group',
      'description' => 'Test Description',
      'chatStyles' => ['group'],
    ]);

    $response->assertStatus(403);
    $response->assertJson([
      'status' => 'error',
      'message' => 'グループ機能を利用するには有料プランへのアップグレードが必要です',
    ]);
  }

  /**
   * 削除されたグループは復元できることをテスト
   */
  public function test_deleted_groups_can_be_restored_when_upgrading_plan()
  {
    // ユーザーとグループを作成
    $user = User::factory()->create(['plan' => 'standard']);
    $group = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Test Group',
      'max_members' => 50,
    ]);

    $chatRoom = ChatRoom::factory()->create([
      'group_id' => $group->id,
      'type' => 'group_chat',
    ]);

    // グループを論理削除
    $group->deleteBySelf('プラン解約によるグループ削除（Freeプランではグループ機能が利用できません）');

    // グループとチャットルームが削除されていることを確認
    $group->refresh();
    $chatRoom->refresh();
    $this->assertNotNull($group->deleted_at);
    $this->assertNotNull($chatRoom->deleted_at);

    // プランをアップグレード
    $user->update(['plan' => 'premium']);

    // グループを手動で復元（実際のアプリケーションでは管理機能で行う）
    $group->restoreByAdmin();

    // グループとチャットルームが復元されていることを確認
    $group->refresh();
    $chatRoom->refresh();
    $this->assertNull($group->deleted_at);
    $this->assertNull($chatRoom->deleted_at);
  }
}