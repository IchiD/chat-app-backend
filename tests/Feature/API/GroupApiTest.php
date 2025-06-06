<?php

namespace Tests\Feature\API;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_group(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/groups', [
            'name' => 'My Group',
            'description' => 'test',
        ]);

        $response->assertCreated()->assertJsonFragment(['name' => 'My Group']);
        $this->assertDatabaseHas('groups', [
            'name' => 'My Group',
            'owner_user_id' => $user->id,
        ]);
    }

    public function test_add_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/groups/'.$group->id.'/members', [
            'user_id' => $member->id,
            'nickname' => 'Nick',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_send_message(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::factory()->for($owner, 'owner')->create();
        $group->members()->create([
            'user_id' => $member->id,
            'nickname' => 'Nick',
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($member);
        $response = $this->postJson('/api/groups/'.$group->id.'/messages', [
            'message' => 'hello',
        ]);

        $response->assertCreated()->assertJsonFragment(['message' => 'hello']);
        $this->assertDatabaseHas('group_messages', [
            'group_id' => $group->id,
            'message' => 'hello',
        ]);
    }

    public function test_non_member_cannot_send_message(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $group = Group::factory()->for($owner, 'owner')->create();

        Sanctum::actingAs($other);
        $response = $this->postJson('/api/groups/'.$group->id.'/messages', [
            'message' => 'fail',
        ]);

        $response->assertStatus(403);
    }

    public function test_max_members_validation_by_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/groups', [
            'name' => 'Over Limit',
            'max_members' => 100,
        ]);

        $response->assertStatus(422);
    }
}
