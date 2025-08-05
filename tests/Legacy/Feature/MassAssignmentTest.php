<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_update_does_not_allow_role_escalation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-name', [
            'name' => 'NewName',
            'role' => 'super_admin',
            'is_admin' => true,
        ]);
        $response->assertOk();

        $this->assertDatabaseCount('admins', 0);
        $this->assertSame('NewName', $user->fresh()->name);
    }

    public function test_user_update_cannot_modify_protected_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-name', [
            'name' => 'Safe',
            'is_banned' => true,
            'deleted_at' => now()->toISOString(),
        ]);
        $response->assertOk();

        $user->refresh();
        $this->assertFalse($user->is_banned);
        $this->assertNull($user->deleted_at);
    }

    public function test_message_sender_id_cannot_be_tampered(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        $conversation = Conversation::create(['type' => 'direct']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);

        $user->sendFriendRequest($friend->id);
        $friend->acceptFriendRequest($user->id);

        Sanctum::actingAs($user);
        $url = "/api/conversations/room/{$conversation->room_token}/messages";

        $response = $this->postJson($url, [
            'text_content' => 'hello',
            'sender_id' => $friend->id,
        ]);
        $response->assertCreated();
        $messageId = $response->json('id');

        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'sender_id' => $user->id,
        ]);
    }

    public function test_friend_request_fields_cannot_be_modified(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $friend->id,
            'friend_id' => 999,
            'status' => Friendship::STATUS_ACCEPTED,
            'deleted_at' => now()->toISOString(),
        ]);
        $response->assertOk();

        $this->assertDatabaseHas('friendships', [
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => Friendship::STATUS_PENDING,
            'deleted_at' => null,
        ]);
    }

    public function test_conversation_creation_ignores_admin_fields(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        $user->sendFriendRequest($friend->id);
        $friend->acceptFriendRequest($user->id);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/conversations', [
            'recipient_id' => $friend->id,
            'deleted_at' => now()->toISOString(),
            'deleted_by' => 1,
            'room_token' => 'FAKE',
        ]);
        $response->assertCreated();
        $conversation = Conversation::find($response->json('id'));

        $this->assertNull($conversation->deleted_at);
        $this->assertNull($conversation->deleted_by);
        $this->assertNotEquals('FAKE', $conversation->room_token);
    }

    public function test_unfillable_fields_are_not_mass_assigned(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $originalCreated = $user->created_at;

        $response = $this->putJson('/api/user/update-name', [
            'name' => 'Change',
            'created_at' => now()->subYears(5)->toISOString(),
        ]);
        $response->assertOk();

        $this->assertEquals($originalCreated->format('Y-m-d H:i:s'), $user->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_bulk_message_array_payload_is_rejected(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);

        $user->sendFriendRequest($friend->id);
        $friend->acceptFriendRequest($user->id);

        Sanctum::actingAs($user);
        $url = "/api/conversations/room/{$conversation->room_token}/messages";

        $response = $this->postJson($url, [
            ['text_content' => 'hacked'],
            ['text_content' => 'hacked2'],
        ]);
        $response->assertStatus(500);
        $this->assertDatabaseCount('messages', 0);
    }
}
