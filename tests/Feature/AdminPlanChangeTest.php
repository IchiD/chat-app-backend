<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ChatRoom;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class AdminPlanChangeTest extends TestCase
{
  use RefreshDatabase;

  protected $admin;

  protected function setUp(): void
  {
    parent::setUp();
    
    // 管理者を作成
    $this->admin = Admin::factory()->create([
      'email' => 'admin@example.com',
      'password' => bcrypt('password'),
    ]);
  }

  /**
   * 管理者がユーザーのプランをfreeに変更した際にグループが削除されることをテスト
   */
  public function test_admin_changing_user_plan_to_free_deletes_groups()
  {
    // スタンダードプランのユーザーとグループを作成
    $user = User::factory()->create([
      'plan' => 'standard',
      'name' => 'Test User',
      'email' => 'user@example.com',
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

    // 管理者としてログイン
    $this->actingAs($this->admin, 'admin');

    // ユーザーのプランをfreeに変更
    $response = $this->put(route('admin.users.update', $user->id), [
      'name' => $user->name,
      'email' => $user->email,
      'is_verified' => true,
      'friend_id' => $user->friend_id,
      'plan' => 'free',
      'allow_re_registration' => true,
    ]);

    $response->assertRedirect(route('admin.users.show', $user->id));
    $response->assertSessionHas('success');

    // セッションメッセージにグループ削除の情報が含まれていることを確認
    $this->assertStringContainsString('2個のグループが削除されました', session('success'));

    // ユーザーのプランが変更されていることを確認
    $user->refresh();
    $this->assertEquals('free', $user->plan);

    // グループが論理削除されていることを確認
    $group1->refresh();
    $group2->refresh();
    $this->assertNotNull($group1->deleted_at);
    $this->assertNotNull($group2->deleted_at);
    $this->assertEquals('管理者によるプラン変更（Freeプラン）に伴うグループ削除', $group1->deleted_reason);
    $this->assertEquals('管理者によるプラン変更（Freeプラン）に伴うグループ削除', $group2->deleted_reason);

    // チャットルームも削除されていることを確認
    $chatRoom1->refresh();
    $this->assertNotNull($chatRoom1->deleted_at);
    $this->assertStringContainsString('グループ削除に伴う自動削除', $chatRoom1->deleted_reason);
  }

  /**
   * 管理者がプレミアムからスタンダードに変更してもグループは削除されないことをテスト
   */
  public function test_admin_downgrading_from_premium_to_standard_keeps_groups()
  {
    // プレミアムプランのユーザーとグループを作成
    $user = User::factory()->create([
      'plan' => 'premium',
      'name' => 'Premium User',
    ]);

    $group = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Premium Group',
      'max_members' => 200,
    ]);

    // 100人のメンバーを追加（スタンダードの上限50人を超える）
    for ($i = 0; $i < 100; $i++) {
      $member = User::factory()->create();
      GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $member->id,
      ]);
    }

    // 管理者としてログイン
    $this->actingAs($this->admin, 'admin');

    // プランをスタンダードに変更
    $response = $this->put(route('admin.users.update', $user->id), [
      'name' => $user->name,
      'email' => $user->email,
      'is_verified' => true,
      'friend_id' => $user->friend_id,
      'plan' => 'standard',
      'allow_re_registration' => false,
    ]);

    $response->assertRedirect(route('admin.users.show', $user->id));

    // グループが削除されていないことを確認
    $group->refresh();
    $this->assertNull($group->deleted_at);
    
    // ただし、max_membersは変更される可能性がある（実装による）
    // この例では、管理者による変更では max_members は変更されない
  }

  /**
   * 管理者がfreeプランからスタンダードに変更してもグループは自動復元されないことをテスト
   */
  public function test_admin_upgrading_from_free_to_standard_does_not_restore_groups()
  {
    // 削除されたグループを持つfreeプランのユーザーを作成
    $user = User::factory()->create([
      'plan' => 'free',
      'name' => 'Free User',
    ]);

    $group = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Deleted Group',
      'max_members' => 50,
      'deleted_at' => now(),
      'deleted_reason' => 'プラン解約によるグループ削除',
    ]);

    // 管理者としてログイン
    $this->actingAs($this->admin, 'admin');

    // プランをスタンダードに変更
    $response = $this->put(route('admin.users.update', $user->id), [
      'name' => $user->name,
      'email' => $user->email,
      'is_verified' => true,
      'friend_id' => $user->friend_id,
      'plan' => 'standard',
      'allow_re_registration' => false,
    ]);

    $response->assertRedirect(route('admin.users.show', $user->id));

    // ユーザーのプランが変更されていることを確認
    $user->refresh();
    $this->assertEquals('standard', $user->plan);

    // グループは削除されたままであることを確認（自動復元されない）
    $group->refresh();
    $this->assertNotNull($group->deleted_at);
  }

  /**
   * 既に削除されたグループは重複して削除されないことをテスト
   */
  public function test_already_deleted_groups_are_not_deleted_again()
  {
    // スタンダードプランのユーザーを作成
    $user = User::factory()->create([
      'plan' => 'standard',
      'name' => 'Test User',
    ]);

    // 既に削除されたグループ
    $deletedGroup = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Already Deleted Group',
      'max_members' => 50,
      'deleted_at' => now()->subDays(1),
      'deleted_reason' => '以前の削除',
    ]);

    // アクティブなグループ
    $activeGroup = Group::factory()->create([
      'owner_user_id' => $user->id,
      'name' => 'Active Group',
      'max_members' => 50,
    ]);

    // 管理者としてログイン
    $this->actingAs($this->admin, 'admin');

    // プランをfreeに変更
    $response = $this->put(route('admin.users.update', $user->id), [
      'name' => $user->name,
      'email' => $user->email,
      'is_verified' => true,
      'friend_id' => $user->friend_id,
      'plan' => 'free',
      'allow_re_registration' => true,
    ]);

    $response->assertRedirect(route('admin.users.show', $user->id));

    // セッションメッセージには1個のグループ削除のみが表示される
    $this->assertStringContainsString('1個のグループが削除されました', session('success'));

    // 既に削除されたグループの削除日時と理由が変更されていないことを確認
    $deletedGroup->refresh();
    $this->assertEquals('以前の削除', $deletedGroup->deleted_reason);

    // アクティブだったグループが削除されていることを確認
    $activeGroup->refresh();
    $this->assertNotNull($activeGroup->deleted_at);
    $this->assertEquals('管理者によるプラン変更（Freeプラン）に伴うグループ削除', $activeGroup->deleted_reason);
  }

  /**
   * グループを持たないユーザーのプラン変更ではエラーが発生しないことをテスト
   */
  public function test_changing_plan_to_free_for_user_without_groups()
  {
    // グループを持たないスタンダードプランのユーザーを作成
    $user = User::factory()->create([
      'plan' => 'standard',
      'name' => 'User Without Groups',
    ]);

    // 管理者としてログイン
    $this->actingAs($this->admin, 'admin');

    // プランをfreeに変更
    $response = $this->put(route('admin.users.update', $user->id), [
      'name' => $user->name,
      'email' => $user->email,
      'is_verified' => true,
      'friend_id' => $user->friend_id,
      'plan' => 'free',
      'allow_re_registration' => true,
    ]);

    $response->assertRedirect(route('admin.users.show', $user->id));
    $response->assertSessionHas('success', 'ユーザー情報を更新しました。');

    // ユーザーのプランが変更されていることを確認
    $user->refresh();
    $this->assertEquals('free', $user->plan);
  }
}