<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_friend_request_input_validation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 必須項目なし
        $response = $this->postJson('/api/friends/requests', []);
        $response->assertStatus(422);

        // 文字列ID
        $response = $this->postJson('/api/friends/requests', [
            'user_id' => 'abc',
        ]);
        $response->assertStatus(422);

        // 存在しないユーザー
        $response = $this->postJson('/api/friends/requests', [
            'user_id' => 9999,
        ]);
        $response->assertStatus(422);
    }

    public function test_message_send_input_validation(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();

        $conversation = Conversation::create(['type' => 'direct']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $friend->id]);

        // 友達関係を構築
        $user->sendFriendRequest($friend->id);
        $friend->acceptFriendRequest($user->id);

        Sanctum::actingAs($user);

        $url = "/api/conversations/room/{$conversation->room_token}/messages";

        // 必須項目なし
        $response = $this->postJson($url, []);
        $response->assertStatus(500);

        // 文字数オーバー
        $response = $this->postJson($url, [
            'text_content' => str_repeat('a', 6000),
        ]);
        $response->assertStatus(500);
    }

    public function test_user_registration_input_validation(): void
    {
        // 空データ
        $response = $this->postJson('/api/register', []);
        $response->assertStatus(422);

        // 無効な形式
        $response = $this->postJson('/api/register', [
            'email' => 'invalid',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'name' => str_repeat('n', 20),
        ]);
        $response->assertStatus(422);
    }

    public function test_invalid_format_data_returns_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/friends/requests', [
            'user_id' => ['array'],
        ]);
        $response->assertStatus(422);
    }
}
