<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Admin;
use App\Models\Message;
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

  public function test_deleted_user_cannot_access_api(): void
  {
    $user = User::factory()->create([
      'deleted_at' => now(),
      'deleted_by' => Admin::factory()->create()->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/friends');
    $response->assertStatus(403);
    $response->assertJson(['error_type' => 'account_deleted']);
  }

  public function test_banned_user_cannot_access_api(): void
  {
    $user = User::factory()->create(['is_banned' => true]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/friends');
    $response->assertStatus(403);
    $response->assertJson(['error_type' => 'account_banned']);
  }

  public function test_user_cannot_send_message_to_unfriended_conversation(): void
  {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // 友達関係を作る
    $userA->sendFriendRequest($userB->id);
    $userB->acceptFriendRequest($userA->id);

    // 会話を作成
    $conversation = Conversation::create(['type' => 'direct']);
    $conversation->conversationParticipants()->createMany([
      ['user_id' => $userA->id],
      ['user_id' => $userB->id],
    ]);

    // 友達関係を解除
    $userA->unfriend($userB->id);

    Sanctum::actingAs($userA);

    $response = $this->postJson("/api/conversations/room/{$conversation->room_token}/messages", [
      'text_content' => 'This should fail',
    ]);

    $response->assertStatus(403);
    $response->assertJsonFragment(['friendship_status' => 'unfriended']);
  }

  public function test_user_cannot_access_messages_from_unfriended_conversation(): void
  {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // 友達関係を作る
    $userA->sendFriendRequest($userB->id);
    $userB->acceptFriendRequest($userA->id);

    // 会話を作成
    $conversation = Conversation::create(['type' => 'direct']);
    $conversation->conversationParticipants()->createMany([
      ['user_id' => $userA->id],
      ['user_id' => $userB->id],
    ]);

    // 友達関係を解除
    $userA->unfriend($userB->id);

    Sanctum::actingAs($userA);

    $response = $this->getJson("/api/conversations/room/{$conversation->room_token}/messages");
    $response->assertStatus(403);
    $response->assertJsonFragment(['friendship_status' => 'unfriended']);
  }

  public function test_user_cannot_send_friend_request_to_deleted_user(): void
  {
    $sender = User::factory()->create();
    $deletedUser = User::factory()->create([
      'deleted_at' => now(),
      'deleted_by' => Admin::factory()->create()->id,
    ]);

    Sanctum::actingAs($sender);

    $response = $this->postJson('/api/friends/requests', [
      'user_id' => $deletedUser->id,
    ]);

    $response->assertStatus(422);
  }

  public function test_user_cannot_send_friend_request_to_banned_user(): void
  {
    $sender = User::factory()->create();
    $bannedUser = User::factory()->create(['is_banned' => true]);

    Sanctum::actingAs($sender);

    $response = $this->postJson('/api/friends/requests', [
      'user_id' => $bannedUser->id,
    ]);

    $response->assertStatus(422);
  }

  public function test_user_cannot_search_deleted_or_banned_users_by_friend_id(): void
  {
    $searcher = User::factory()->create();
    $deletedUser = User::factory()->create([
      'friend_id' => 'DEL001',
      'deleted_at' => now(),
      'deleted_by' => Admin::factory()->create()->id,
    ]);
    $bannedUser = User::factory()->create([
      'friend_id' => 'BAN001',
      'is_banned' => true,
    ]);

    Sanctum::actingAs($searcher);

    // 削除されたユーザーを検索
    $response = $this->postJson('/api/friends/search', [
      'friend_id' => 'DEL001',
    ]);
    $response->assertStatus(404);

    // バンされたユーザーを検索
    $response = $this->postJson('/api/friends/search', [
      'friend_id' => 'BAN001',
    ]);
    $response->assertStatus(404);
  }

  public function test_user_cannot_access_deleted_conversation(): void
  {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conversation = Conversation::create([
      'type' => 'direct',
      'deleted_at' => now(),
      'deleted_by' => Admin::factory()->create()->id,
    ]);
    $conversation->conversationParticipants()->createMany([
      ['user_id' => $userA->id],
      ['user_id' => $userB->id],
    ]);

    Sanctum::actingAs($userA);

    $response = $this->getJson('/api/conversations/token/' . $conversation->room_token);
    $response->assertStatus(403);
    $response->assertJsonFragment(['message' => 'この会話は削除されています。']);
  }

  public function test_user_cannot_send_message_to_deleted_conversation(): void
  {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conversation = Conversation::create([
      'type' => 'direct',
      'deleted_at' => now(),
      'deleted_by' => Admin::factory()->create()->id,
    ]);
    $conversation->conversationParticipants()->createMany([
      ['user_id' => $userA->id],
      ['user_id' => $userB->id],
    ]);

    Sanctum::actingAs($userA);

    $response = $this->postJson("/api/conversations/room/{$conversation->room_token}/messages", [
      'text_content' => 'This should fail',
    ]);

    $response->assertStatus(403);
    $response->assertJsonFragment(['message' => 'この会話は削除されています。']);
  }

  public function test_token_revocation_for_deleted_user(): void
  {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');

    // ユーザーを削除
    $user->update([
      'deleted_at' => now(),
      'deleted_by' => Admin::factory()->create()->id,
    ]);

    // 削除されたユーザーがAPIにアクセス
    $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
      ->getJson('/api/friends');

    $response->assertStatus(403);
    $response->assertJson(['error_type' => 'account_deleted']);

    // トークンが削除されていることを確認
    $this->assertDatabaseMissing('personal_access_tokens', [
      'tokenable_id' => $user->id,
    ]);
  }

  public function test_token_revocation_for_banned_user(): void
  {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');

    // ユーザーをバン
    $user->update(['is_banned' => true]);

    // バンされたユーザーがAPIにアクセス
    $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
      ->getJson('/api/friends');

    $response->assertStatus(403);
    $response->assertJson(['error_type' => 'account_banned']);

    // トークンが削除されていることを確認
    $this->assertDatabaseMissing('personal_access_tokens', [
      'tokenable_id' => $user->id,
    ]);
  }
}
