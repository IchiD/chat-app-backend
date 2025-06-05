<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Friendship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataLeakageTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_hash_not_exposed_in_api_response(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret')]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json());
    }

    public function test_error_message_does_not_leak_db_schema_info(): void
    {
        config(['app.debug' => false]);

        Route::get('/db-error-test', function () {
            return DB::select('SELECT * FROM non_existing_table');
        });

        $response = $this->get('/db-error-test');

        $response->assertStatus(500);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_logs_do_not_contain_plain_password(): void
    {
        $path = storage_path('logs/laravel.log');
        if (file_exists($path)) {
            unlink($path);
        }

        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertFileExists($path);
        $log = file_get_contents($path);
        $this->assertStringNotContainsString('password123', $log);
    }

    public function test_debug_mode_does_not_return_detailed_error(): void
    {
        config(['app.debug' => false]);

        Route::get('/debug-error', function () {
            throw new \Exception('Sensitive info');
        });

        $response = $this->get('/debug-error');
        $response->assertStatus(500);
        $this->assertStringNotContainsString('Sensitive info', $response->getContent());
    }

    public function test_query_logs_do_not_expose_sensitive_information(): void
    {
        DB::enableQueryLog();

        $user = User::factory()->create();

        $user->friends();

        foreach (DB::getQueryLog() as $query) {
            $this->assertStringNotContainsString('password', $query['query']);
        }
    }

    public function test_session_cookie_is_encrypted(): void
    {
        $response = $this->get('/admin/login');
        $cookie = collect($response->headers->getCookies())->firstWhere('Name', config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue(strlen($cookie->getValue()) > 20);
    }

    public function test_permanent_deletion_removes_record(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $friendship = Friendship::create([
            'user_id' => $user1->id,
            'friend_id' => $user2->id,
            'status' => Friendship::STATUS_ACCEPTED,
        ]);

        $friendship->forceDelete();

        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
    }
}
