<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_protected_api(): void
    {
        $response = $this->getJson('/api/friends');
        $response->assertStatus(401);
    }

    public function test_user_cannot_accept_friend_request_of_others(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $attacker = User::factory()->create();

        $sender->sendFriendRequest($receiver->id);

        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/friends/requests/accept', [
            'user_id' => $sender->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_user_cannot_access_conversation_they_do_not_participate_in(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->conversationParticipants()->createMany([
            ['user_id' => $userA->id],
            ['user_id' => $userB->id],
        ]);

        Sanctum::actingAs($userC);

        $response = $this->getJson('/api/conversations/token/' . $conversation->room_token);
        $response->assertStatus(403);
    }

    public function test_user_cannot_delete_message_of_other_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->conversationParticipants()->createMany([
            ['user_id' => $userA->id],
            ['user_id' => $userB->id],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $userA->id,
            'text_content' => 'hello',
            'content_type' => 'text',
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($userB);

        $response = $this->delete('/admin/users/' . $userA->id . '/conversations/' . $conversation->id . '/messages/' . $message->id);
        $response->assertStatus(302);
    }
}
