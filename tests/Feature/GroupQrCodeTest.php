<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_regenerate_qr_code(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $group = Group::factory()->create(['owner_user_id' => $owner->id]);
        $oldToken = $group->qr_code_token;

        $response = $this->postJson("/api/groups/{$group->id}/qr-code/regenerate");
        $response->assertOk();
        $newToken = $response->json('qr_code_token');

        $this->assertNotEquals($oldToken, $newToken);
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'qr_code_token' => $newToken,
        ]);
    }

    public function test_guest_can_join_group_by_token(): void
    {
        $group = Group::factory()->create();

        $response = $this->postJson("/api/groups/join/{$group->qr_code_token}", [
            'nickname' => 'guest',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('group_members', 1);
        $member = GroupMember::first();
        $this->assertNull($member->user_id);
        $this->assertNotNull($member->guest_identifier);
    }

    public function test_authenticated_user_can_join_group_by_token(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::factory()->create(['owner_user_id' => $owner->id]);

        Sanctum::actingAs($member);

        $response = $this->postJson("/api/groups/join/{$group->qr_code_token}", [
            'nickname' => 'new member',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_cannot_join_when_group_is_full(): void
    {
        $group = Group::factory()->create(['max_members' => 1]);
        GroupMember::factory()->create(['group_id' => $group->id]);

        $response = $this->postJson("/api/groups/join/{$group->qr_code_token}", [
            'nickname' => 'guest',
        ]);

        $response->assertStatus(422);
    }
}
