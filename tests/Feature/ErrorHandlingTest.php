<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Exception;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_found_returns_404(): void
    {
        $response = $this->get('/api/non-existent');
        $response->assertStatus(404);
    }

    public function test_forbidden_returns_403(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $outsider = User::factory()->create();

        $conversation = Conversation::create(['type' => 'direct']);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $userA->id]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $userB->id]);

        Sanctum::actingAs($outsider);

        $response = $this->getJson('/api/conversations/room/' . $conversation->room_token . '/messages');
        $response->assertStatus(403);
    }

    public function test_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/register', []);
        $response->assertStatus(422);
    }

    public function test_server_error_returns_500(): void
    {
        Route::get('/force-error', function () {
            throw new Exception('test');
        });

        $response = $this->get('/force-error');
        $response->assertStatus(500);
    }
}
