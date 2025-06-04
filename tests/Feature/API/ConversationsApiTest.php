<?php

namespace Tests\Feature\API;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createFriendConversation(User $user, User $friend): Conversation
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

    public function test_index_returns_conversations(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $conversation = $this->createFriendConversation($user, $friend);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/conversations');

        $response->assertOk()->assertJsonFragment(['id' => $conversation->id]);
    }

    public function test_store_creates_conversation(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $user->sendFriendRequest($friend->id);
        $friend->acceptFriendRequest($user->id);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/conversations', [
            'recipient_id' => $friend->id,
        ]);

        $response->assertCreated()->assertJsonFragment(['type' => 'direct']);
    }

    public function test_show_by_token_returns_conversation(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $conversation = $this->createFriendConversation($user, $friend);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/conversations/token/'.$conversation->room_token);

        $response->assertOk()->assertJsonFragment(['id' => $conversation->id]);
    }

    public function test_mark_as_read(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $conversation = $this->createFriendConversation($user, $friend);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/conversations/'.$conversation->id.'/read');

        $response->assertOk();
    }

    public function test_support_conversation_flow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/support/conversation');
        $create->assertCreated();

        $get = $this->getJson('/api/support/conversation');
        $get->assertOk()->assertJsonFragment(['id' => $create->json('id')]);
    }
}
