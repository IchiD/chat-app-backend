<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Admin;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;
use App\Notifications\PushNotification;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_user_flow(): void
    {
        $user1 = User::factory()->create(['password' => bcrypt('password1'), 'is_verified' => true]);
        $user2 = User::factory()->create(['password' => bcrypt('password2'), 'is_verified' => true]);

        // login user1
        $login1 = $this->postJson('/api/login', [
            'email' => $user1->email,
            'password' => 'password1',
        ]);
        $token1 = $login1->json('access_token');

        // user1 sends friend request to user2
        $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/friends/requests', ['user_id' => $user2->id])
            ->assertOk();

        // login user2 and accept
        $login2 = $this->postJson('/api/login', [
            'email' => $user2->email,
            'password' => 'password2',
        ]);
        $token2 = $login2->json('access_token');

        $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson('/api/friends/requests/accept', ['user_id' => $user1->id])
            ->assertOk();

        // create conversation
        $conversation = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/conversations', ['recipient_id' => $user2->id])
            ->assertCreated()
            ->json();

        $roomToken = $conversation['room_token'];

        // send message
        $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson("/api/conversations/room/{$roomToken}/messages", ['text_content' => 'hello'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'text_content' => 'hello',
            'sender_id' => $user1->id,
        ]);
    }

    public function test_google_to_password_login(): void
    {
        $googleUser = (new SocialiteUser())->map([
            'id' => 'gid1234',
            'email' => 'google@example.com',
            'name' => 'Google User',
            'avatar' => 'avatar.png',
        ]);

        Socialite::shouldReceive('driver')->once()->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->once()->andReturn($googleUser);

        $this->get('/api/auth/google/callback');

        $user = User::where('email', 'google@example.com')->first();
        $this->assertNotNull($user);

        // switch to normal auth
        $user->update([
            'google_id' => null,
            'social_type' => null,
            'password' => bcrypt('newpass'),
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'newpass',
        ])->assertOk();
    }

    public function test_email_verify_reset_login_flow(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpass'),
            'is_verified' => false,
        ]);

        // verify email
        $this->getJson('/api/verify?token=' . $user->email_verification_token)
            ->assertOk();

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpass',
            'password_confirmation' => 'newpass',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'newpass',
        ])->assertOk();
    }

    public function test_push_notification_on_message(): void
    {
        Notification::fake();

        $user1 = User::factory()->create(['is_verified' => true]);
        $user2 = User::factory()->create(['is_verified' => true]);

        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->conversationParticipants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);

        Sanctum::actingAs($user1);
        $this->postJson("/api/conversations/room/{$conversation->room_token}/messages", [
            'text_content' => 'notify',
        ])->assertCreated();

        Notification::assertSentTo($user2, PushNotification::class);
    }

    public function test_admin_delete_user_blocks_login(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'password' => bcrypt('secret'),
            'is_verified' => true,
        ]);

        $this->actingAs($admin, 'admin');
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        $this->delete('/admin/users/' . $user->id, ['reason' => 'test'])->assertStatus(302);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret',
        ])->assertStatus(401)->assertJson(['error_type' => 'account_deleted']);
    }
}
