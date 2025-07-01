<?php

namespace Tests\Feature\API;

use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Mockery;

class StripeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_checkout_session_requires_auth(): void
    {
        $this->app->instance(StripeService::class, Mockery::mock(StripeService::class));
        $response = $this->postJson('/api/stripe/create-checkout-session', ['plan' => 'standard']);
        $response->assertStatus(401);
    }

    public function test_create_checkout_session_returns_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $mock = Mockery::mock(StripeService::class);
        $mock->shouldReceive('createCheckoutSession')->once()->with($user, 'standard')
            ->andReturn(['status' => 'success', 'message' => 'session_created', 'url' => 'https://stripe.test']);
        $this->app->instance(StripeService::class, $mock);

        $response = $this->postJson('/api/stripe/create-checkout-session', ['plan' => 'standard']);
        $response->assertOk()->assertJsonFragment(['url' => 'https://stripe.test']);
    }

    public function test_webhook_processes_event(): void
    {
        $payload = ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'evt']]];
        $secret = 'whsec_test';
        $timestamp = time();
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $timestamp.'.'.$json, $secret);
        $header = "t={$timestamp},v1={$sig}";

        $mock = Mockery::mock(StripeService::class);
        $mock->shouldReceive('handleWebhook')->once()->with($payload);
        $this->app->instance(StripeService::class, $mock);

        config(['services.stripe.webhook_secret' => $secret]);

        $response = $this->withHeaders(['Stripe-Signature' => $header])
            ->postJson('/api/stripe/webhook', $payload);

        $response->assertOk();
    }
}
