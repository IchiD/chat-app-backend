<?php

namespace Tests\Feature;

use App\Mail\EmailChangeVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-email', ['email' => 'new@example.com']);

        $response->assertOk()->assertJsonFragment(['status' => 'success']);
        $updated = $user->fresh();
        $this->assertEquals('new@example.com', $updated->new_email);
        $this->assertNotNull($updated->email_change_token);
    }

    public function test_email_change_confirmation(): void
    {
        $user = User::factory()->create([
            'new_email' => 'new@example.com',
            'email_change_token' => 'token123',
            'token_expires_at' => now()->addHour(),
        ]);

        $response = $this->getJson('/api/verify-email-change?token=token123');

        $response->assertOk()->assertJsonFragment([
            'status' => 'success',
            'email' => 'new@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'new_email' => null,
            'email_change_token' => null,
        ]);
    }

    public function test_google_user_cannot_change_email(): void
    {
        $user = User::factory()->create([
            'social_type' => 'google',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-email', ['email' => 'other@example.com']);

        $response->assertStatus(403);
    }

    public function test_invalid_email_change_token(): void
    {
        $response = $this->getJson('/api/verify-email-change?token=invalid');

        $response->assertStatus(400)->assertJson([
            'error_type' => 'token_invalid',
        ]);
    }

    public function test_email_change_duplicate_email_error(): void
    {
        $user = User::factory()->create();
        $existing = User::factory()->create(['email' => 'exist@example.com']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-email', ['email' => $existing->email]);

        $response->assertStatus(422);
    }

    public function test_email_change_sends_notification(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/user/update-email', ['email' => 'notify@example.com']);

        Mail::assertSent(EmailChangeVerification::class, function ($mail) {
            return $mail->hasTo('notify@example.com');
        });
    }
}
