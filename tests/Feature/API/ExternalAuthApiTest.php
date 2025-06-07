<?php

namespace Tests\Feature\API;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use App\Models\ExternalApiToken;

class ExternalAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_token_success(): void
    {
        $response = $this->postJson('/api/auth/external/token', [
            'client_id' => 'external-client',
            'client_secret' => 'external-secret',
        ]);

        $response->assertOk()->assertJsonStructure(['access_token']);
        $this->assertDatabaseCount('external_api_tokens', 1);
    }

    public function test_issue_token_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/external/token', [
            'client_id' => 'bad',
            'client_secret' => 'bad',
        ]);

        $response->assertStatus(401);
    }

    public function test_verify_token(): void
    {
        $token = ExternalApiToken::create([
            'token' => 'abcd',
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer abcd'])
            ->postJson('/api/auth/external/verify');

        $response->assertOk()->assertJson(['message' => 'valid']);
        $this->assertDatabaseHas('external_api_tokens', [
            'id' => $token->id,
            'usage_count' => 1,
        ]);
    }

    public function test_verify_token_invalid(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer wrong'])
            ->postJson('/api/auth/external/verify');

        $response->assertStatus(401);
    }
}
