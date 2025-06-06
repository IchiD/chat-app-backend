<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'max_members' => 50,
        ];
    }
}
