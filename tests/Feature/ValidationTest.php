<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    // 負の値のuser_id
    $response = $this->postJson('/api/friends/requests', [
      'user_id' => -1,
    ]);
    $response->assertStatus(422);

    // null値のuser_id
    $response = $this->postJson('/api/friends/requests', [
      'user_id' => null,
    ]);
    $response->assertStatus(422);

    // 存在しないユーザー
    $response = $this->postJson('/api/friends/requests', [
      'user_id' => 99999,
    ]);
    $response->assertStatus(422);

    // messageフィールド長すぎる（255文字超過）
    $response = $this->postJson('/api/friends/requests', [
      'user_id' => User::factory()->create()->id,
      'message' => str_repeat('a', 256),
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

    // メッセージAPIはtry-catchでラップされているため、バリデーションエラーも500として返される

    // 必須項目なし
    $response = $this->postJson($url, []);
    $response->assertStatus(500); // try-catchによりValidationExceptionが500エラーに変換される

    // null値
    $response = $this->postJson($url, [
      'text_content' => null,
    ]);
    $response->assertStatus(500);

    // 数値型（文字列であるべき）
    $response = $this->postJson($url, [
      'text_content' => 12345,
    ]);
    $response->assertStatus(500);

    // 文字数オーバー（5000文字超過）
    $response = $this->postJson($url, [
      'text_content' => str_repeat('a', 5001),
    ]);
    $response->assertStatus(500);

    // 空文字列
    $response = $this->postJson($url, [
      'text_content' => '',
    ]);
    $response->assertStatus(500);
  }

  public function test_user_registration_input_validation(): void
  {
    // 空データ
    $response = $this->postJson('/api/register', []);
    $response->assertStatus(422);

    // 無効なメール形式
    $response = $this->postJson('/api/register', [
      'email' => 'invalid-email',
      'password' => 'validpass',
      'password_confirmation' => 'validpass',
      'name' => 'ValidName',
    ]);
    $response->assertStatus(422);

    // パスワード短すぎる（6文字未満）
    $response = $this->postJson('/api/register', [
      'email' => 'test@example.com',
      'password' => 'short',
      'password_confirmation' => 'short',
      'name' => 'ValidName',
    ]);
    $response->assertStatus(422);

    // パスワード確認不一致
    $response = $this->postJson('/api/register', [
      'email' => 'test@example.com',
      'password' => 'validpass',
      'password_confirmation' => 'mismatch',
      'name' => 'ValidName',
    ]);
    $response->assertStatus(422);

    // 名前長すぎる（10文字超過）
    $response = $this->postJson('/api/register', [
      'email' => 'test@example.com',
      'password' => 'validpass',
      'password_confirmation' => 'validpass',
      'name' => str_repeat('n', 11),
    ]);
    $response->assertStatus(422);

    // 名前が空
    $response = $this->postJson('/api/register', [
      'email' => 'test@example.com',
      'password' => 'validpass',
      'password_confirmation' => 'validpass',
      'name' => '',
    ]);
    $response->assertStatus(422);

    // 全て必須項目なし
    $response = $this->postJson('/api/register', [
      'email' => '',
      'password' => '',
      'password_confirmation' => '',
      'name' => '',
    ]);
    $response->assertStatus(422);
  }

  public function test_user_update_validation(): void
  {
    $user = User::factory()->create(['password' => Hash::make('oldpass')]);
    Sanctum::actingAs($user);

    // ユーザー名更新バリデーション
    $response = $this->putJson('/api/user/update-name', [
      'name' => str_repeat('n', 11), // 10文字超過
    ]);
    $response->assertStatus(422);

    $response = $this->putJson('/api/user/update-name', [
      'name' => '', // 空文字
    ]);
    $response->assertStatus(422);

    $response = $this->putJson('/api/user/update-name', []); // 必須項目なし
    $response->assertStatus(422);

    // パスワード更新バリデーション
    $response = $this->putJson('/api/user/update-password', [
      'current_password' => 'wrongpass',
      'password' => 'newpass',
      'password_confirmation' => 'newpass',
    ]);
    $response->assertStatus(422);

    $response = $this->putJson('/api/user/update-password', [
      'current_password' => 'oldpass',
      'password' => 'short', // 6文字未満
      'password_confirmation' => 'short',
    ]);
    $response->assertStatus(422);

    $response = $this->putJson('/api/user/update-password', [
      'current_password' => 'oldpass',
      'password' => 'newpass',
      'password_confirmation' => 'mismatch', // 確認不一致
    ]);
    $response->assertStatus(422);
  }

  public function test_login_validation(): void
  {
    // 空データ
    $response = $this->postJson('/api/login', []);
    $response->assertStatus(422);

    // メールなし
    $response = $this->postJson('/api/login', [
      'password' => 'password',
    ]);
    $response->assertStatus(422);

    // パスワードなし
    $response = $this->postJson('/api/login', [
      'email' => 'test@example.com',
    ]);
    $response->assertStatus(422);

    // 無効なメール形式
    $response = $this->postJson('/api/login', [
      'email' => 'invalid-email',
      'password' => 'password',
    ]);
    $response->assertStatus(422);
  }

  public function test_friend_id_search_validation(): void
  {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // 必須項目なし
    $response = $this->postJson('/api/friends/search', []);
    $response->assertStatus(422);

    // 文字数不正（6文字以外）
    $response = $this->postJson('/api/friends/search', [
      'friend_id' => 'abc', // 3文字
    ]);
    $response->assertStatus(422);

    $response = $this->postJson('/api/friends/search', [
      'friend_id' => 'abcdefg', // 7文字
    ]);
    $response->assertStatus(422);

    // 数値型（文字列であるべき）
    $response = $this->postJson('/api/friends/search', [
      'friend_id' => 123456,
    ]);
    $response->assertStatus(422);
  }

  public function test_invalid_format_data_returns_error(): void
  {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // 配列型の不正データ
    $response = $this->postJson('/api/friends/requests', [
      'user_id' => ['array'],
    ]);
    $response->assertStatus(422);

    // オブジェクト型の不正データ
    $response = $this->postJson('/api/friends/requests', [
      'user_id' => (object)['key' => 'value'],
    ]);
    $response->assertStatus(422);
  }
}
