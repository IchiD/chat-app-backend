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

class GroupSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $owner;
    private $member;
    private $nonMember;
    private $bannedMember;
    private $group;

    protected function setUp(): void
    {
        parent::setUp();
        
        // テストユーザー作成
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->nonMember = User::factory()->create();
        $this->bannedMember = User::factory()->create();

        // テストグループ作成
        $this->group = Group::factory()->create([
            'owner_user_id' => $this->owner->id,
            'max_members' => 10,
            'chat_styles' => ['group', 'group_member'],
        ]);

        // グループメンバー設定
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'role' => 'member',
        ]);
        GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->bannedMember->id,
            'role' => 'member',
            'left_at' => now(),
            'removal_type' => GroupMember::REMOVAL_TYPE_KICKED_BY_OWNER,
            'can_rejoin' => false,
        ]);
    }

    /**
     * 非認証ユーザーはグループAPIにアクセスできないテスト
     */
    public function test_unauthenticated_user_cannot_access_group_api()
    {
        $response = $this->getJson('/api/conversations/groups');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/conversations/groups', [
            'name' => 'テストグループ',
        ]);
        $response->assertUnauthorized();

        $response = $this->getJson("/api/conversations/groups/{$this->group->id}");
        $response->assertUnauthorized();
    }

    /**
     * 非メンバーはグループ詳細にアクセスできないテスト
     */
    public function test_non_member_cannot_access_group_details()
    {
        Sanctum::actingAs($this->nonMember);

        $response = $this->getJson("/api/conversations/groups/{$this->group->id}");
        $response->assertForbidden()
                 ->assertJson(['message' => 'このグループのメンバーではありません']);
    }

    /**
     * 非メンバーはグループメンバー一覧を取得できないテスト
     */
    public function test_non_member_cannot_get_group_members()
    {
        Sanctum::actingAs($this->nonMember);

        $response = $this->getJson("/api/conversations/groups/{$this->group->id}/members");
        $response->assertForbidden();
    }

    /**
     * 非オーナーはグループ情報を更新できないテスト
     */
    public function test_non_owner_cannot_update_group_info()
    {
        Sanctum::actingAs($this->member);

        $response = $this->putJson("/api/conversations/groups/{$this->group->id}", [
            'name' => '不正な更新',
            'description' => '権限なし',
        ]);

        $response->assertForbidden()
                 ->assertJson(['message' => 'この操作を実行する権限がありません']);
    }

    /**
     * 非オーナーはメンバーを追加できないテスト
     */
    public function test_non_owner_cannot_add_members()
    {
        Sanctum::actingAs($this->member);

        $newUser = User::factory()->create();

        $response = $this->postJson("/api/conversations/groups/{$this->group->id}/members", [
            'user_id' => $newUser->id,
        ]);

        $response->assertForbidden()
                 ->assertJson(['message' => 'メンバーを追加する権限がありません']);
    }

    /**
     * 非オーナーは他のメンバーを削除できないテスト
     */
    public function test_non_owner_cannot_remove_other_members()
    {
        Sanctum::actingAs($this->member);

        $otherMember = User::factory()->create();
        $otherMemberRecord = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $otherMember->id,
            'role' => 'member',
        ]);

        $response = $this->deleteJson("/api/conversations/groups/{$this->group->id}/members/{$otherMemberRecord->id}");

        $response->assertForbidden()
                 ->assertJson(['message' => 'メンバーを削除する権限がありません']);
    }

    /**
     * 非オーナーはグループを削除できないテスト
     */
    public function test_non_owner_cannot_delete_group()
    {
        Sanctum::actingAs($this->member);

        $response = $this->deleteJson("/api/conversations/groups/{$this->group->id}");

        $response->assertForbidden()
                 ->assertJson(['message' => 'グループを削除する権限がありません']);
    }

    /**
     * 非オーナーはQRコードを再生成できないテスト
     */
    public function test_non_owner_cannot_regenerate_qr_code()
    {
        Sanctum::actingAs($this->member);

        $response = $this->postJson("/api/conversations/groups/{$this->group->id}/qr-code/regenerate");

        $response->assertForbidden()
                 ->assertJson(['message' => 'QRコードを再生成する権限がありません']);
    }

    /**
     * 再参加禁止メンバーは再参加できないテスト
     */
    public function test_banned_member_cannot_rejoin_group()
    {
        Sanctum::actingAs($this->bannedMember);

        $response = $this->postJson("/api/conversations/groups/join/{$this->group->qr_code_token}");

        $response->assertForbidden()
                 ->assertJson(['message' => 'このグループへの再参加は許可されていません']);
    }

    /**
     * 削除されたグループにはアクセスできないテスト
     */
    public function test_cannot_access_deleted_group()
    {
        Sanctum::actingAs($this->member);

        $this->group->delete();

        $response = $this->getJson("/api/conversations/groups/{$this->group->id}");
        $response->assertNotFound();

        $response = $this->putJson("/api/conversations/groups/{$this->group->id}", [
            'name' => '更新不可',
        ]);
        $response->assertNotFound();
    }

    /**
     * 非メンバーはグループチャットに送信できないテスト
     */
    public function test_non_member_cannot_send_message_to_group_chat()
    {
        Sanctum::actingAs($this->nonMember);

        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $this->group->id,
        ]);

        $response = $this->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
            'content' => '不正なメッセージ',
        ]);

        $response->assertForbidden()
                 ->assertJson(['message' => 'このチャットルームへのアクセス権限がありません']);
    }

    /**
     * 退会済みメンバーはグループチャットに送信できないテスト
     */
    public function test_removed_member_cannot_send_message_to_group_chat()
    {
        Sanctum::actingAs($this->bannedMember);

        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $this->group->id,
        ]);

        $response = $this->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
            'content' => '送信できないメッセージ',
        ]);

        $response->assertForbidden();
    }

    /**
     * SQLインジェクション対策テスト（グループ名）
     */
    public function test_sql_injection_prevention_in_group_name()
    {
        Sanctum::actingAs($this->owner);

        $maliciousName = "'; DROP TABLE groups; --";

        $response = $this->postJson('/api/conversations/groups', [
            'name' => $maliciousName,
            'max_members' => 10,
        ]);

        $response->assertCreated();
        
        // テーブルが削除されていないことを確認
        $this->assertDatabaseHas('groups', [
            'name' => $maliciousName,
        ]);
    }

    /**
     * XSS対策テスト（グループ名と説明）
     */
    public function test_xss_prevention_in_group_data()
    {
        Sanctum::actingAs($this->owner);

        $xssName = '<script>alert("XSS")</script>';
        $xssDescription = '<img src=x onerror=alert("XSS")>';

        $response = $this->postJson('/api/conversations/groups', [
            'name' => $xssName,
            'description' => $xssDescription,
            'max_members' => 10,
        ]);

        $response->assertCreated();

        // データがエスケープされずに保存されることを確認（エスケープは表示時に行う）
        $this->assertDatabaseHas('groups', [
            'name' => $xssName,
            'description' => $xssDescription,
        ]);
    }

    /**
     * 大量メンバー追加によるDoS攻撃防止テスト
     */
    public function test_cannot_exceed_max_members_limit()
    {
        Sanctum::actingAs($this->owner);

        // 最大メンバー数を2に設定（オーナー含む）
        $this->group->update(['max_members' => 2]);

        // すでに2人いるので追加できない
        $newUser = User::factory()->create();

        $response = $this->postJson("/api/conversations/groups/{$this->group->id}/members", [
            'user_id' => $newUser->id,
        ]);

        $response->assertStatus(422)
                 ->assertJson(['message' => 'グループの最大メンバー数に達しています']);
    }

    /**
     * 存在しないユーザーIDでのメンバー追加防止テスト
     */
    public function test_cannot_add_non_existent_user_as_member()
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/conversations/groups/{$this->group->id}/members", [
            'user_id' => 999999, // 存在しないID
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['user_id']);
    }

    /**
     * 自分自身を再度追加できないテスト
     */
    public function test_cannot_add_existing_member_again()
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/conversations/groups/{$this->group->id}/members", [
            'user_id' => $this->member->id, // すでにメンバー
        ]);

        $response->assertStatus(422)
                 ->assertJson(['message' => 'すでにグループのメンバーです']);
    }

    /**
     * 無効なロールでメンバー追加できないテスト
     */
    public function test_cannot_add_member_with_invalid_role()
    {
        Sanctum::actingAs($this->owner);

        $newUser = User::factory()->create();

        $response = $this->postJson("/api/conversations/groups/{$this->group->id}/members", [
            'user_id' => $newUser->id,
            'role' => 'superadmin', // 無効なロール
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['role']);
    }

    /**
     * オーナーは自分自身を削除できないテスト
     */
    public function test_owner_cannot_remove_themselves()
    {
        Sanctum::actingAs($this->owner);

        $ownerMember = GroupMember::where('group_id', $this->group->id)
                                  ->where('user_id', $this->owner->id)
                                  ->first();

        $response = $this->deleteJson("/api/conversations/groups/{$this->group->id}/members/{$ownerMember->id}");

        $response->assertStatus(422)
                 ->assertJson(['message' => 'オーナーは自分自身を削除できません']);
    }

    /**
     * 一斉メッセージ送信の権限チェックテスト
     */
    public function test_only_owner_can_send_bulk_messages()
    {
        Sanctum::actingAs($this->member);

        $response = $this->postJson("/api/conversations/groups/{$this->group->id}/messages/bulk", [
            'content' => '権限のない一斉送信',
        ]);

        $response->assertForbidden()
                 ->assertJson(['message' => '一斉メッセージを送信する権限がありません']);
    }

    /**
     * メンバー復活の権限チェックテスト
     */
    public function test_only_owner_can_restore_members()
    {
        Sanctum::actingAs($this->member);

        $removedMember = GroupMember::where('group_id', $this->group->id)
                                    ->where('user_id', $this->bannedMember->id)
                                    ->first();

        $response = $this->postJson("/api/conversations/groups/{$this->group->id}/members/{$removedMember->id}/restore");

        $response->assertForbidden()
                 ->assertJson(['message' => 'メンバーを復活させる権限がありません']);
    }

    /**
     * メンバーニックネーム変更の権限チェックテスト
     */
    public function test_only_owner_can_update_member_nickname()
    {
        Sanctum::actingAs($this->member);

        $targetMember = GroupMember::where('group_id', $this->group->id)
                                   ->where('user_id', $this->member->id)
                                   ->first();

        $response = $this->patchJson("/api/conversations/groups/{$this->group->id}/members/{$targetMember->id}/nickname", [
            'nickname' => '権限のない変更',
        ]);

        $response->assertForbidden()
                 ->assertJson(['message' => 'ニックネームを変更する権限がありません']);
    }

    /**
     * 削除済みユーザーのグループアクセステスト
     */
    public function test_deleted_user_cannot_access_group()
    {
        $this->member->delete();
        Sanctum::actingAs($this->member);

        $response = $this->getJson("/api/conversations/groups/{$this->group->id}");
        $response->assertForbidden()
                 ->assertJson(['message' => 'アカウントが削除されています']);
    }

    /**
     * レート制限テスト（グループ作成）
     */
    public function test_rate_limiting_on_group_creation()
    {
        Sanctum::actingAs($this->owner);

        // 連続してグループを作成
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/conversations/groups', [
                'name' => "テストグループ{$i}",
                'max_members' => 10,
            ]);
            
            if ($i < 5) {
                $response->assertCreated();
            }
        }

        // レート制限に達した後のリクエスト
        $response = $this->postJson('/api/conversations/groups', [
            'name' => 'レート制限テスト',
            'max_members' => 10,
        ]);

        $response->assertStatus(429); // Too Many Requests
    }
}