<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_current_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users/me');

        $response->assertOk()->assertJsonFragment(['email' => $user->email]);
    }

    public function test_update_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-name', ['name' => 'NewName']);

        $response->assertOk()->assertJsonFragment(['name' => 'NewName']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'NewName']);
    }

    public function test_update_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpass')]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-password', [
            'current_password' => 'oldpass',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
    }

    public function test_request_email_change(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/user/update-email', ['email' => 'new@example.com']);

        $response->assertOk()->assertJsonFragment(['status' => 'success']);
    }
}
