<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Friendship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_friend_request(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $receiver->id,
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('friendships', [
            'user_id' => $sender->id,
            'friend_id' => $receiver->id,
            'status' => Friendship::STATUS_PENDING,
        ]);
    }

    public function test_accept_friend_request(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $friendship = $sender->sendFriendRequest($receiver->id);

        Sanctum::actingAs($receiver);

        $response = $this->postJson('/api/friends/requests/accept', [
            'user_id' => $sender->id,
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);

        $friendship->refresh();
        $this->assertEquals(Friendship::STATUS_ACCEPTED, $friendship->status);
        $this->assertTrue($sender->fresh()->friends()->contains('id', $receiver->id));
    }

    public function test_reject_friend_request(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $friendship = $sender->sendFriendRequest($receiver->id);

        Sanctum::actingAs($receiver);

        $response = $this->postJson('/api/friends/requests/reject', [
            'user_id' => $sender->id,
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);

        $friendship->refresh();
        $this->assertEquals(Friendship::STATUS_REJECTED, $friendship->status);
    }

    public function test_cancel_friend_request(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $friendship = $sender->sendFriendRequest($receiver->id);

        Sanctum::actingAs($sender);

        $response = $this->deleteJson('/api/friends/requests/cancel/' . $friendship->id);

        $response->assertOk()->assertJson(['status' => 'success']);

        $this->assertSoftDeleted('friendships', [
            'id' => $friendship->id,
        ]);
    }

    public function test_unfriend(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $friendship = $sender->sendFriendRequest($receiver->id);
        $receiver->acceptFriendRequest($sender->id);

        Sanctum::actingAs($sender);

        $response = $this->deleteJson('/api/friends/unfriend', [
            'user_id' => $receiver->id,
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);

        $this->assertSoftDeleted('friendships', [
            'id' => $friendship->id,
        ]);
    }

    public function test_get_friends_list(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $sender->sendFriendRequest($receiver->id);
        $receiver->acceptFriendRequest($sender->id);

        Sanctum::actingAs($sender);

        $response = $this->getJson('/api/friends');

        $response->assertOk()->assertJsonFragment(['id' => $receiver->id]);
    }

    public function test_prevent_duplicate_requests(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $sender->sendFriendRequest($receiver->id);

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $receiver->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_send_request_to_self(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_error_when_user_not_found(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => 999,
        ]);

        $response->assertStatus(422);
    }
}
