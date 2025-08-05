<?php

namespace Database\Factories;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FriendshipFactory extends Factory
{
    protected $model = Friendship::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'friend_id' => User::factory(),
            'status' => Friendship::STATUS_PENDING,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Friendship::STATUS_ACCEPTED,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Friendship::STATUS_REJECTED,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Friendship::STATUS_PENDING,
        ]);
    }
}