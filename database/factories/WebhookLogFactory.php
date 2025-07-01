<?php

namespace Database\Factories;

use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookLogFactory extends Factory
{
  protected $model = WebhookLog::class;

  public function definition(): array
  {
    return [
      'stripe_event_id' => 'evt_' . $this->faker->unique()->randomNumber(8),
      'event_type' => $this->faker->randomElement([
        'checkout.session.completed',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.payment_succeeded',
        'invoice.payment_failed'
      ]),
      'payload' => [
        'id' => 'evt_' . $this->faker->randomNumber(8),
        'type' => 'checkout.session.completed',
        'data' => [
          'object' => [
            'id' => 'cs_' . $this->faker->randomNumber(8),
            'amount_total' => $this->faker->numberBetween(1000, 10000),
            'currency' => 'jpy'
          ]
        ]
      ],
      'status' => $this->faker->randomElement(['pending', 'processed', 'failed']),
      'error_message' => null,
      'processed_at' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
    ];
  }

  public function processed(): static
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'processed',
      'processed_at' => now(),
      'error_message' => null,
    ]);
  }

  public function failed(): static
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'failed',
      'processed_at' => null,
      'error_message' => $this->faker->sentence(),
    ]);
  }
}
