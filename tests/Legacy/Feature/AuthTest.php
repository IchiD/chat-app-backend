<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
  use RefreshDatabase;

  public function test_user_can_register(): void
  {
    $response = $this->postJson('/api/register', [
      'email' => 'test@example.com',
      'name' => 'Test User',
      'password' => 'password',
      'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
      ->assertJson(['status' => 'success']);

    $this->assertDatabaseHas('users', [
      'email' => 'test@example.com',
      'is_verified' => false,
    ]);
  }

  public function test_email_verification(): void
  {
    $user = User::factory()->create([
      'email' => 'verify@example.com',
      'is_verified' => false,
    ]);

    $this->assertNotNull($user->email_verification_token);

    $response = $this->getJson('/api/verify?token=' . $user->email_verification_token);

    $response->assertOk()->assertJson(['status' => 'success']);

    $updatedUser = $user->fresh();
    $this->assertTrue((bool)$updatedUser->is_verified);
  }

  public function test_user_can_login(): void
  {
    $user = User::factory()->create([
      'password' => bcrypt('password'),
      'is_verified' => true,
    ]);

    $response = $this->postJson('/api/login', [
      'email' => $user->email,
      'password' => 'password',
    ]);

    $response->assertOk()
      ->assertJson(['status' => 'success'])
      ->assertJsonStructure(['access_token']);
  }

  public function test_login_fails_with_invalid_credentials(): void
  {
    $user = User::factory()->create([
      'password' => bcrypt('password'),
      'is_verified' => true,
    ]);

    $response = $this->postJson('/api/login', [
      'email' => $user->email,
      'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
      ->assertJson(['error_type' => 'invalid_credentials']);
  }

  public function test_user_can_logout(): void
  {
    $user = User::factory()->create([
      'password' => bcrypt('password'),
      'is_verified' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/logout');

    $response->assertOk();
    $this->assertDatabaseCount('personal_access_tokens', 0);
  }

  public function test_password_reset(): void
  {
    $user = User::factory()->create([
      'password' => bcrypt('oldpass'),
      'is_verified' => true,
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/password/reset', [
      'email' => $user->email,
      'token' => $token,
      'password' => 'newpassword',
      'password_confirmation' => 'newpassword',
    ]);

    $response->assertOk()->assertJson(['status' => 'success']);

    $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
  }

  public function test_unverified_user_cannot_login(): void
  {
    $user = User::factory()->create([
      'password' => bcrypt('password'),
      'is_verified' => false,
    ]);

    $response = $this->postJson('/api/login', [
      'email' => $user->email,
      'password' => 'password',
    ]);

    $response->assertStatus(401)
      ->assertJson(['error_type' => 'not_verified']);
  }

  public function test_invalid_verification_token(): void
  {
    $response = $this->getJson('/api/verify?token=invalid_token');

    $response->assertStatus(400)
      ->assertJson(['error_type' => 'token_invalid']);
  }
}
