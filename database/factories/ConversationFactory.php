<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['direct', 'group']),
            'room_token' => null,
        ];
    }

    public function deleted(): static
    {
        return $this->state(function (): array {
            return [
                'deleted_at' => now(),
                'deleted_by' => 1,
                'deleted_reason' => 'テスト削除',
            ];
        });
    }
}
