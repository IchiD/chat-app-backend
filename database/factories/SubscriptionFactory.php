<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan' => $this->faker->randomElement(['standard', 'premium']),
            'status' => 'active',
            'stripe_subscription_id' => 'sub_' . $this->faker->unique()->regexify('[A-Za-z0-9]{24}'),
            'stripe_customer_id' => 'cus_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'current_period_end' => now()->addMonth(),
        ];
    }

    /**
     * Indicate that the subscription is canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Indicate that the subscription will cancel at period end.
     */
    public function willCancel(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'will_cancel',
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Indicate that the subscription is on trial.
     */
    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}