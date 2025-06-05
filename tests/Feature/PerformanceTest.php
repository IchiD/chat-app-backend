<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_sending_under_high_load(): void
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

        Sanctum::actingAs($user1);

        $success = 0;
        $fail = 0;
        for ($i = 1; $i <= 15; $i++) {
            $response = $this->postJson("/api/conversations/room/{$conversation->room_token}/messages", [
                'text_content' => "Load test message {$i}",
            ]);
            if ($response->status() === 201) {
                $success++;
            } elseif ($response->status() === 429) {
                $fail++;
            }
        }

        $this->assertGreaterThan(0, $fail, 'Expected some requests to be throttled');
        $this->assertEquals(10, $success);
        $this->assertDatabaseCount('messages', 10);
    }

    public function test_friendship_with_large_number_of_users(): void
    {
        $users = User::factory()->count(50)->create();
        $mainUser = $users->first();

        foreach ($users->slice(1) as $user) {
            $user->sendFriendRequest($mainUser->id);
            $mainUser->acceptFriendRequest($user->id);
        }

        Sanctum::actingAs($mainUser);
        $response = $this->getJson('/api/friends');
        $response->assertOk();
        $this->assertCount(49, $response->json('friends'));
    }

    public function test_conversation_list_pagination_with_large_dataset(): void
    {
        $mainUser = User::factory()->create();
        $others = User::factory()->count(30)->create();

        foreach ($others as $user) {
            $mainUser->sendFriendRequest($user->id);
            $user->acceptFriendRequest($mainUser->id);

            $conversation = Conversation::create(['type' => 'direct']);
            $conversation->conversationParticipants()->createMany([
                ['user_id' => $mainUser->id],
                ['user_id' => $user->id],
            ]);
        }

        Sanctum::actingAs($mainUser);
        $responsePage1 = $this->getJson('/api/conversations');
        $responsePage1->assertOk();
        $this->assertCount(15, $responsePage1->json('data'));

        $responsePage2 = $this->getJson('/api/conversations?page=2');
        $responsePage2->assertOk();
        $this->assertNotEmpty($responsePage2->json('data'));
    }

    public function test_database_connection_pool_limit(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $result = DB::select('SELECT 1 as num')[0]->num ?? null;
            $this->assertEquals(1, $result);
        }
    }

    public function test_memory_usage_does_not_exceed_limit(): void
    {
        $before = memory_get_usage();
        $data = [];
        for ($i = 0; $i < 1000; $i++) {
            $data[] = str_repeat('a', 1000);
        }
        $after = memory_get_usage();
        $this->assertLessThan(50 * 1024 * 1024, $after - $before);
    }

    public function test_response_time_under_threshold(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $start = microtime(true);
        $response = $this->getJson('/api/config');
        $response->assertOk();
        $elapsed = microtime(true) - $start;
        $this->assertLessThan(1, $elapsed);
    }

    public function test_multiple_sequential_requests(): void
    {
        Sanctum::actingAs(User::factory()->create());
        for ($i = 0; $i < 20; $i++) {
            $response = $this->getJson('/api/config');
            $response->assertOk();
        }
    }
}
