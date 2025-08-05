<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HorizontalPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(User $a, User $b): Conversation
    {
        $a->sendFriendRequest($b->id);
        $b->acceptFriendRequest($a->id);

        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->conversationParticipants()->createMany([
            ['user_id' => $a->id],
            ['user_id' => $b->id],
        ]);
        return $conversation;
    }

    public function test_cannot_view_other_users_message_history(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = $this->createConversation($userA, $userB);

        Sanctum::actingAs($outsider);

        $response = $this->getJson('/api/conversations/room/' . $conversation->room_token . '/messages');
        $response->assertStatus(403);
    }

    public function test_cannot_view_conversation_detail_without_participation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = $this->createConversation($userA, $userB);

        Sanctum::actingAs($outsider);

        $response = $this->getJson('/api/conversations/token/' . $conversation->room_token);
        $response->assertStatus(403);
    }

    public function test_cannot_get_other_users_friend_list(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userA->sendFriendRequest($userB->id);
        $userB->acceptFriendRequest($userA->id);

        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider);

        $response = $this->getJson('/api/friends?user_id=' . $userA->id);
        $response->assertOk();
        $this->assertEmpty($response->json('friends'));
    }

    public function test_cannot_get_other_users_conversation_list(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $conversation = $this->createConversation($userA, $userB);

        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider);

        $response = $this->getJson('/api/conversations?user_id=' . $userA->id);
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_cannot_change_other_users_profile(): void
    {
        $userA = User::factory()->create(['name' => 'Original']);
        $userB = User::factory()->create();

        Sanctum::actingAs($userB);

        $this->putJson('/api/user/update-name', [
            'user_id' => $userA->id,
            'name' => 'Hacked',
        ])->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $userA->id,
            'name' => 'Hacked',
        ]);
    }

    public function test_url_parameter_tampering_is_blocked(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = $this->createConversation($userA, $userB);

        Sanctum::actingAs($outsider);

        $response = $this->postJson('/api/conversations/' . $conversation->id . '/read');
        $response->assertStatus(403);
    }

    public function test_invalid_room_token_cannot_access_conversation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/conversations/token/invalidtoken')->assertStatus(404);
        $this->getJson('/api/conversations/room/invalidtoken/messages')->assertStatus(404);
    }
}
