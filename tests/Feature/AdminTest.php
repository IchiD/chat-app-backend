<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_authentication_and_login(): void
    {
        $admin = Admin::factory()->create([
            'password' => Hash::make('secret'),
        ]);

        Log::spy();
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        Log::shouldHaveReceived('info')->with('Admin login successful', \Mockery::on(fn ($context) => $context['admin_id'] === $admin->id));
    }

    public function test_admin_can_view_user_list_for_bulk_management(): void
    {
        $admin = Admin::factory()->create();
        $users = User::factory()->count(5)->create();

        $this->actingAs($admin, 'admin');

        $response = $this->get('/admin/users');
        $response->assertOk();
        $response->assertSee($users->first()->email);
    }

    public function test_admin_can_ban_and_delete_user(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'admin');
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

        $response = $this->delete("/admin/users/{$user->id}", [
            'reason' => 'violation',
        ]);

        $response->assertStatus(302);

        $user->refresh();
        $this->assertNotNull($user->deleted_at);
        $this->assertTrue($user->is_banned);
    }

    public function test_super_admin_can_create_new_admin(): void
    {
        $superAdmin = Admin::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($superAdmin, 'admin');

        $response = $this->post('/admin/admins', [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'newpassword',
            'role' => 'admin',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('admins', ['email' => 'newadmin@example.com']);
    }

    public function test_admin_logs_actions_for_audit(): void
    {
        $admin = Admin::factory()->create([
            'password' => Hash::make('secret'),
        ]);

        Log::spy();
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'secret',
        ]);

        Log::shouldHaveReceived('info')->with('Admin login successful', \Mockery::on(fn ($context) => $context['admin_id'] === $admin->id));
    }

    public function test_admin_handles_large_user_dataset(): void
    {
        $admin = Admin::factory()->create();
        User::factory()->count(100)->create();

        $this->actingAs($admin, 'admin');
        $response = $this->get('/admin/users');
        $response->assertOk();

        $users = $response->viewData('users');
        $this->assertEquals(100, $users->total());
        $this->assertEquals(20, $users->count());
    }
}

