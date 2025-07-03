<?php

namespace Tests\Unit\Services\Billing;

use Tests\TestCase;
use App\Services\Billing\PaymentService;
use App\Models\User;
use Stripe\StripeClient;
use Mockery;

class PaymentServiceTest extends TestCase
{
    private PaymentService $service;
    private $stripeClientMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stripeClientMock = Mockery::mock(StripeClient::class);
        $this->service = new PaymentService($this->stripeClientMock);
    }

    public function test_create_checkout_session_success()
    {
        $user = User::factory()->create(['plan' => 'free']);

        $checkoutMock = Mockery::mock('checkout');
        $sessionsMock = Mockery::mock('sessions');

        $sessionsMock->shouldReceive('create')->andReturn((object)[
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/test'
        ]);

        $checkoutMock->sessions = $sessionsMock;
        $this->stripeClientMock->checkout = $checkoutMock;

        $result = $this->service->createCheckoutSession($user, 'standard');

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('url', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
