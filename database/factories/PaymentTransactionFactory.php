<?php

namespace Database\Factories;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentTransactionFactory extends Factory
{
  protected $model = PaymentTransaction::class;

  public function definition(): array
  {
    return [
      'user_id' => User::factory(),
      'subscription_id' => Subscription::factory(),
      'stripe_payment_intent_id' => 'pi_' . $this->faker->unique()->randomNumber(8),
      'stripe_charge_id' => 'ch_' . $this->faker->unique()->randomNumber(8),
      'amount' => $this->faker->randomFloat(2, 1000, 10000),
      'currency' => 'jpy',
      'status' => $this->faker->randomElement(['pending', 'succeeded', 'failed', 'canceled']),
      'type' => $this->faker->randomElement(['subscription', 'one_time']),
      'refund_amount' => 0,
      'metadata' => [],
      'paid_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
    ];
  }

  public function succeeded(): static
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'succeeded',
      'paid_at' => now(),
    ]);
  }

  public function failed(): static
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'failed',
      'paid_at' => null,
    ]);
  }
}
