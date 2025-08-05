<?php

namespace Tests\Feature\API;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagesApiTest extends TestCase
{
    use RefreshDatabase;

    private function createConversationWithFriend(User $user, User $friend): Conversation
    {
        $user->sendFriendRequest($friend->id);
        $friend->acceptFriendRequest($user->id);
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->conversationParticipants()->createMany([
            ['user_id' => $user->id],
            ['user_id' => $friend->id],
        ]);
        return $conversation->fresh();
    }

    public function test_index_returns_messages(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $conversation = $this->createConversationWithFriend($user, $friend);
        $conversation->messages()->create([
            'sender_id' => $friend->id,
            'text_content' => 'hello',
            'content_type' => 'text',
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/conversations/room/'.$conversation->room_token.'/messages');

        $response->assertOk()->assertJsonFragment(['text_content' => 'hello']);
    }

    public function test_store_creates_message(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $conversation = $this->createConversationWithFriend($user, $friend);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/conversations/room/'.$conversation->room_token.'/messages', [
            'text_content' => 'hi there',
        ]);

        $response->assertCreated()->assertJsonFragment(['text_content' => 'hi there']);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'text_content' => 'hi there',
        ]);
    }
}
