<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class FriendshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_friend_request()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $friend->id,
            'message' => 'Hi, let\'s be friends!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => '友達申請を送信しました'
        ]);
        
        $this->assertDatabaseHas('friendships', [
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => Friendship::STATUS_PENDING,
        ]);
    }

    public function test_user_can_accept_friend_request()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        
        // Friend sends request to user
        $friendship = Friendship::factory()->create([
            'user_id' => $friend->id,
            'friend_id' => $user->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/friends/requests/accept", [
            'user_id' => $friend->id
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => '友達申請を承認しました'
        ]);
        
        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => Friendship::STATUS_ACCEPTED,
        ]);
    }

    public function test_user_can_reject_friend_request()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        
        $friendship = Friendship::factory()->create([
            'user_id' => $friend->id,
            'friend_id' => $user->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/friends/requests/reject", [
            'user_id' => $friend->id
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => '友達申請を拒否しました'
        ]);
        
        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => Friendship::STATUS_REJECTED,
        ]);
    }

    public function test_user_can_get_sent_friend_requests()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        
        Friendship::factory()->create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/friends/requests/sent');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'sent_requests' => [
                '*' => ['id', 'user', 'friend', 'message', 'status']
            ]
        ]);
    }

    public function test_user_can_get_received_friend_requests()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        
        Friendship::factory()->create([
            'user_id' => $friend->id,
            'friend_id' => $user->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/friends/requests/received');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'received_requests' => [
                '*' => ['id', 'user', 'friend', 'message', 'status']
            ]
        ]);
    }

    public function test_user_cannot_send_friend_request_to_themselves()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => '自分自身に友達申請はできません'
        ]);
    }

    public function test_user_cannot_send_duplicate_friend_request()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        
        Friendship::factory()->create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => $friend->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => '既に友達申請を送信済みです'
        ]);
    }
}