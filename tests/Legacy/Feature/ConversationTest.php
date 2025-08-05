<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
  use RefreshDatabase;

  public function test_create_conversation(): void
  {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->sendFriendRequest($user2->id);
    $user2->acceptFriendRequest($user1->id);

    Sanctum::actingAs($user1);

    $response = $this->postJson('/api/conversations', [
      'recipient_id' => $user2->id,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('conversations', [
      'id' => $response->json('id'),
    ]);
  }

  public function test_create_conversation_between_friends_returns_existing_conversation(): void
  {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->sendFriendRequest($user2->id);
    $user2->acceptFriendRequest($user1->id);

    Sanctum::actingAs($user1);

    $first = $this->postJson('/api/conversations', [
      'recipient_id' => $user2->id,
    ])->json('id');

    $response = $this->postJson('/api/conversations', [
      'recipient_id' => $user2->id,
    ]);

    $response->assertOk();
    $this->assertEquals($first, $response->json('id'));
    $this->assertDatabaseCount('conversations', 1);
  }

  public function test_cannot_create_conversation_with_non_friend(): void
  {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Sanctum::actingAs($user1);

    $response = $this->postJson('/api/conversations', [
      'recipient_id' => $user2->id,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('conversations', 0);
  }

  public function test_get_conversation_list(): void
  {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->sendFriendRequest($user2->id);
    $user2->acceptFriendRequest($user1->id);

    Sanctum::actingAs($user1);

    $conversationId = $this->postJson('/api/conversations', [
      'recipient_id' => $user2->id,
    ])->json('id');

    $response = $this->getJson('/api/conversations');
    $response->assertOk()->assertJsonFragment(['id' => $conversationId]);
  }

  public function test_admin_can_delete_conversation(): void
  {
    $admin = Admin::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->sendFriendRequest($user2->id);
    $user2->acceptFriendRequest($user1->id);

    Sanctum::actingAs($user1);
    $conversationId = $this->postJson('/api/conversations', [
      'recipient_id' => $user2->id,
    ])->json('id');

    $conversation = Conversation::findOrFail($conversationId);

    $this->actingAs($admin, 'admin');
    $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
    $response = $this->delete('/admin/users/' . $user1->id . '/conversations/' . $conversation->id);

    $response->assertStatus(302);
    $this->assertNotNull($conversation->fresh()->deleted_at);
  }
}
