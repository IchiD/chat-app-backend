<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_group_and_add_member_and_send_message(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        Sanctum::actingAs($owner);

        $create = $this->postJson('/api/groups', [
            'name' => 'Test Group',
        ]);
        $create->assertCreated();
        $groupId = $create->json('id');

        $add = $this->postJson("/api/groups/{$groupId}/members", [
            'user_id' => $member->id,
            'nickname' => 'member1',
        ]);
        $add->assertCreated();

        $msg = $this->postJson("/api/groups/{$groupId}/messages", [
            'message' => 'hello',
        ]);
        $msg->assertCreated()->assertJsonFragment(['message' => 'hello']);

        $this->assertDatabaseHas('group_messages', [
            'group_id' => $groupId,
            'sender_user_id' => $owner->id,
            'message' => 'hello',
        ]);
    }
}
