<?php

namespace Database\Factories;

use App\Models\GroupMember;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupMemberFactory extends Factory
{
    protected $model = GroupMember::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'nickname' => $this->faker->firstName(),
            'joined_at' => now(),
            'is_active' => true,
        ];
    }
}
