<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_send_friend_request_to_deleted_user(): void
    {
        $user = User::factory()->create();
        $deletedUser = User::factory()->create(['deleted_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $deletedUser->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_send_friend_request_to_banned_user(): void
    {
        $user = User::factory()->create();
        $bannedUser = User::factory()->create(['is_banned' => true]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $bannedUser->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_messages_pagination_with_large_dataset(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->conversationParticipants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        for ($i = 1; $i <= 25; $i++) {
            $conversation->messages()->create([
                'sender_id' => $user1->id,
                'text_content' => 'Message ' . $i,
                'content_type' => 'text',
                'sent_at' => now(),
            ]);
        }

        Sanctum::actingAs($user1);

        $responsePage1 = $this->getJson('/api/conversations/room/' . $conversation->room_token . '/messages');
        $responsePage1->assertOk();
        $this->assertCount(20, $responsePage1->json('data'));
        $this->assertEquals(1, $responsePage1->json('current_page'));

        $responsePage2 = $this->getJson('/api/conversations/room/' . $conversation->room_token . '/messages?page=2');
        $responsePage2->assertOk();
        $this->assertCount(5, $responsePage2->json('data'));
        $this->assertEquals(2, $responsePage2->json('current_page'));
    }

    public function test_concurrent_conversation_creation_results_in_single_conversation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        Sanctum::actingAs($user1);

        $first = $this->postJson('/api/conversations', [
            'recipient_id' => $user2->id,
        ]);
        $first->assertStatus(201);

        $second = $this->postJson('/api/conversations', [
            'recipient_id' => $user2->id,
        ]);
        $second->assertStatus(200);

        $this->assertDatabaseCount('conversations', 1);
    }
}
