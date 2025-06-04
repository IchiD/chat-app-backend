<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Participant;
use App\Models\Message;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(User $user1, User $user2): Conversation
    {
        $conversation = Conversation::create(['type' => 'direct']);
        Participant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
        ]);
        Participant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user2->id,
        ]);
        return $conversation;
    }

    public function test_send_message(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // make them friends
        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $conversation = $this->createConversation($user1, $user2);

        Sanctum::actingAs($user1);

        $response = $this->postJson("/api/conversations/room/{$conversation->room_token}/messages", [
            'text_content' => 'Hello World',
        ]);

        $response->assertCreated()->assertJsonFragment([
            'text_content' => 'Hello World',
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'text_content' => 'Hello World',
        ]);
    }

    public function test_get_messages(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $conversation = $this->createConversation($user1, $user2);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'text_content' => 'Test Message',
            'content_type' => 'text',
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($user1);

        $response = $this->getJson("/api/conversations/room/{$conversation->room_token}/messages");

        $response->assertOk()->assertJsonFragment([
            'text_content' => 'Test Message',
        ]);
    }

    public function test_cannot_send_message_to_unauthorized_conversation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $conversation = $this->createConversation($user1, $user2);

        Sanctum::actingAs($user3);

        $response = $this->postJson("/api/conversations/room/{$conversation->room_token}/messages", [
            'text_content' => 'Unauthorized',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_message(): void
    {
        $admin = Admin::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $conversation = $this->createConversation($user1, $user2);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'text_content' => 'Delete Me',
            'content_type' => 'text',
            'sent_at' => now(),
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->delete("/admin/users/{$user1->id}/conversations/{$conversation->id}/messages/{$message->id}", [
            'reason' => 'spam',
        ]);

        $response->assertStatus(302);

        $this->assertNotNull($message->fresh()->admin_deleted_at);
    }
}
