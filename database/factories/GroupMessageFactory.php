<?php

namespace Database\Factories;

use App\Models\GroupMessage;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupMessageFactory extends Factory
{
    protected $model = GroupMessage::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'sender_user_id' => User::factory(),
            'message' => $this->faker->sentence(),
            'target_type' => 'all',
            'target_ids' => null,
            'created_at' => now(),
        ];
    }
}
