<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendshipApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_sent_requests(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $friendship = $sender->sendFriendRequest($receiver->id);

        Sanctum::actingAs($sender);

        $response = $this->getJson('/api/friends/requests/sent');

        $response->assertOk()->assertJsonFragment(['id' => $friendship->id]);
    }

    public function test_get_received_requests(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $friendship = $sender->sendFriendRequest($receiver->id);

        Sanctum::actingAs($receiver);

        $response = $this->getJson('/api/friends/requests/received');

        $response->assertOk()->assertJsonFragment(['id' => $friendship->id]);
    }

    public function test_search_by_friend_id(): void
    {
        $current = User::factory()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($current);

        $response = $this->postJson('/api/friends/search', [
            'friend_id' => $target->friend_id,
        ]);

        $response->assertOk()->assertJsonFragment(['friend_id' => $target->friend_id]);
    }
}
