<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_injection_attempt_is_blocked(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/search', [
            'friend_id' => "' OR 1=1 --",
        ]);

        $response->assertStatus(422);
    }

    public function test_xss_payload_is_escaped_in_response(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $sender->sendFriendRequest($receiver->id);
        $receiver->acceptFriendRequest($sender->id);

        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->conversationParticipants()->createMany([
            ['user_id' => $sender->id],
            ['user_id' => $receiver->id],
        ]);

        Sanctum::actingAs($sender);

        $payload = '<script>alert("x")</script>';
        $url = "/api/conversations/room/{$conversation->room_token}/messages";
        $response = $this->postJson($url, ['text_content' => $payload]);
        $response->assertCreated();

        $this->assertStringContainsString('<\\/script>', $response->getContent());
    }

    public function test_csrf_protection_rejects_requests_without_token(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create(['deleted_at' => now()]);

        $this->actingAs($admin, 'admin')->withSession(['_token' => 'valid']);

        $response = $this->post("/admin/users/{$user->id}/restore", ['_token' => 'invalid']);
        $response->assertStatus(302);
    }

    public function test_message_send_rate_limit(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $conversation = Conversation::create(['type' => 'direct']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user1->id]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user2->id]);

        Sanctum::actingAs($user1);
        $url = "/api/conversations/room/{$conversation->room_token}/messages";

        RateLimiter::clear("send-message:{$user1->id}");

        for ($i = 0; $i < 10; $i++) {
            $res = $this->postJson($url, ['text_content' => 'test']);
            $res->assertCreated();
        }

        $res = $this->postJson($url, ['text_content' => 'test']);
        $res->assertStatus(429);
    }

    public function test_session_id_regenerates_on_admin_login(): void
    {
        $admin = Admin::factory()->create(['password' => bcrypt('password')]);

        $this->get('/admin/login');
        $oldId = session()->getId();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $newId = session()->getId();
        $this->assertNotSame($oldId, $newId);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalidtoken')->getJson('/api/friends');
        $response->assertStatus(401);
    }

    public function test_regular_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->get('/admin/dashboard');
        $response->assertStatus(302);
    }
}
