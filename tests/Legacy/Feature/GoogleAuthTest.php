<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;
use Mockery;
use Exception;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_google_auth_redirect(): void
    {
        $redirectUrl = 'https://accounts.google.com/o/oauth2/auth?client_id=test';

        Socialite::shouldReceive('driver')->once()->with('google')->andReturnSelf();
        Socialite::shouldReceive('redirectUrl')->once()->with(config('services.google.redirect'))->andReturnSelf();
        Socialite::shouldReceive('redirect')->once()->andReturn(new RedirectResponse($redirectUrl));

        $response = $this->get('/api/auth/google/redirect');

        $response->assertRedirect($redirectUrl);
    }

    public function test_google_auth_callback_creates_new_user(): void
    {
        $googleUser = (new SocialiteUser())->map([
            'id' => 'gid123',
            'email' => 'new@example.com',
            'name' => 'Google User',
            'avatar' => 'avatar.png',
        ]);

        Socialite::shouldReceive('driver')->once()->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->once()->andReturn($googleUser);

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'google_id' => 'gid123',
            'social_type' => 'google',
        ]);
    }

    public function test_google_auth_callback_existing_user(): void
    {
        $user = User::factory()->create([
            'google_id' => 'gid123',
            'email' => 'exist@example.com',
            'is_verified' => false,
            'social_type' => 'google',
        ]);

        $googleUser = (new SocialiteUser())->map([
            'id' => 'gid123',
            'email' => 'exist@example.com',
            'name' => 'Google User',
            'avatar' => 'avatar.png',
        ]);

        Socialite::shouldReceive('driver')->once()->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->once()->andReturn($googleUser);

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $this->assertTrue((bool) $user->fresh()->is_verified);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_auth_error_handling(): void
    {
        Socialite::shouldReceive('driver')->once()->with('google')->andThrow(new Exception('fail'));

        $response = $this->get('/api/auth/google/redirect');

        $response->assertStatus(500)->assertJson(['status' => 'error']);
    }

    public function test_google_user_cannot_change_password(): void
    {
        $user = User::factory()->create([
            'social_type' => 'google',
            'password' => Hash::make('oldpass'),
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-password', [
            'current_password' => 'oldpass',
            'password' => 'newpass',
            'password_confirmation' => 'newpass',
        ]);

        $response->assertStatus(403);
    }

    public function test_google_user_cannot_change_email(): void
    {
        $user = User::factory()->create([
            'social_type' => 'google',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-email', [
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_google_token_returns_error(): void
    {
        Socialite::shouldReceive('driver')->once()->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->once()->andThrow(new Exception('invalid'));

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirectContains('/auth/login');
    }
}
