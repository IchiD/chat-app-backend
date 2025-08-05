<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_user_cannot_create_support_conversation(): void
    {
        $user = User::factory()->create([
            'deleted_at' => now(),
            'deleted_by' => Admin::factory()->create()->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/support/conversation');

        $response->assertStatus(403);
    }

    public function test_banned_user_cannot_create_support_conversation(): void
    {
        $user = User::factory()->create(['is_banned' => true]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/support/conversation');

        $response->assertStatus(403);
    }

    public function test_user_cannot_access_others_support_conversation(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $conversationId = $this->postJson('/api/support/conversation')->json('id');
        $conversation = Conversation::findOrFail($conversationId);

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $response = $this->postJson('/api/conversations/room/'.$conversation->room_token.'/messages', [
            'text_content' => 'test',
        ]);

        $response->assertStatus(403);
    }

    public function test_support_message_send_and_receive(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $conversationId = $this->postJson('/api/support/conversation')->json('id');
        $conversation = Conversation::findOrFail($conversationId);

        $send = $this->postJson('/api/conversations/room/'.$conversation->room_token.'/messages', [
            'text_content' => 'hi support',
        ]);
        $send->assertCreated();

        $get = $this->getJson('/api/conversations/room/'.$conversation->room_token.'/messages');
        $get->assertOk()->assertJsonFragment(['text_content' => 'hi support']);
    }

    public function test_admin_can_manage_support_conversation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $conversationId = $this->postJson('/api/support/conversation')->json('id');
        $conversation = Conversation::findOrFail($conversationId);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $list = $this->get('/admin/support');
        $list->assertStatus(200);

        $detail = $this->get('/admin/support/'.$conversation->id);
        $detail->assertStatus(200);

        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        $reply = $this->post('/admin/support/'.$conversation->id.'/reply', [
            'message' => 'response',
        ]);
        $reply->assertStatus(302);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'admin_sender_id' => $admin->id,
            'text_content' => 'response',
        ]);
    }

    public function test_admin_can_delete_support_conversation_and_user_cannot_post_after(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $conversationId = $this->postJson('/api/support/conversation')->json('id');
        $conversation = Conversation::findOrFail($conversationId);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        $delete = $this->delete('/admin/users/'.$user->id.'/conversations/'.$conversation->id, [
            'reason' => 'closed',
        ]);
        $delete->assertStatus(302);
        $this->assertNotNull($conversation->fresh()->deleted_at);

        Sanctum::actingAs($user);
        $after = $this->postJson('/api/conversations/room/'.$conversation->room_token.'/messages', [
            'text_content' => 'after delete',
        ]);
        $after->assertStatus(403);
    }

    public function test_user_cannot_create_multiple_support_conversations(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $first = $this->postJson('/api/support/conversation');
        $first->assertStatus(201);

        $second = $this->postJson('/api/support/conversation');
        $second->assertStatus(200);

        $this->assertDatabaseCount('conversations', 1);
    }
}
