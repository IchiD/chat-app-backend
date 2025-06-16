<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use App\Models\Subscription;
use App\Models\PaymentTransaction;
use App\Models\WebhookLog;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class BillingControllerTest extends TestCase
{
  use RefreshDatabase;

  protected $admin;

  protected function setUp(): void
  {
    parent::setUp();
    $this->admin = Admin::factory()->create();
  }

  /** @test */
  public function admin_can_view_billing_dashboard()
  {
    $this->actingAs($this->admin, 'admin')
      ->get(route('admin.billing.dashboard'))
      ->assertOk()
      ->assertViewIs('admin.billing.dashboard');
  }

  /** @test */
  public function admin_can_view_subscriptions_list()
  {
    $user = User::factory()->create();
    Subscription::factory()->create(['user_id' => $user->id]);

    $this->actingAs($this->admin, 'admin')
      ->get(route('admin.billing.subscriptions.index'))
      ->assertOk()
      ->assertViewIs('admin.billing.subscriptions.index');
  }

  /** @test */
  public function admin_can_view_subscription_details()
  {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create(['user_id' => $user->id]);

    $this->actingAs($this->admin, 'admin')
      ->get(route('admin.billing.subscriptions.show', $subscription->id))
      ->assertOk()
      ->assertViewIs('admin.billing.subscriptions.show');
  }

  /** @test */
  public function admin_can_cancel_subscription()
  {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create([
      'user_id' => $user->id,
      'status' => 'active',
      'stripe_subscription_id' => 'sub_test123'
    ]);

    // Mock StripeService
    $mockStripeService = Mockery::mock(StripeService::class);
    $mockStripeService->shouldReceive('cancelSubscriptionAdmin')
      ->once()
      ->with('sub_test123')
      ->andReturn(true);

    $this->app->instance(StripeService::class, $mockStripeService);

    $this->actingAs($this->admin, 'admin')
      ->post(route('admin.billing.subscriptions.cancel', $subscription->id))
      ->assertRedirect()
      ->assertSessionHas('success');

    $this->assertDatabaseHas('subscriptions', [
      'id' => $subscription->id,
      'status' => 'canceled'
    ]);
  }

  /** @test */
  public function admin_can_view_payments_list()
  {
    $user = User::factory()->create();
    PaymentTransaction::factory()->create(['user_id' => $user->id]);

    $this->actingAs($this->admin, 'admin')
      ->get(route('admin.billing.payments.index'))
      ->assertOk()
      ->assertViewIs('admin.billing.payments.index');
  }

  /** @test */
  public function admin_can_view_webhook_logs()
  {
    WebhookLog::factory()->create([
      'stripe_event_id' => 'evt_test123',
      'event_type' => 'checkout.session.completed'
    ]);

    $this->actingAs($this->admin, 'admin')
      ->get(route('admin.billing.webhooks.index'))
      ->assertOk()
      ->assertViewIs('admin.billing.webhooks.index');
  }

  /** @test */
  public function admin_can_view_analytics()
  {
    $this->actingAs($this->admin, 'admin')
      ->get(route('admin.billing.analytics.index'))
      ->assertOk()
      ->assertViewIs('admin.billing.analytics.index');
  }

  /** @test */
  public function guest_cannot_access_billing_pages()
  {
    $this->get(route('admin.billing.dashboard'))
      ->assertRedirect(route('admin.login'));
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }
}
