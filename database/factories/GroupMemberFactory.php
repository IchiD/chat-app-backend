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
            'role' => 'member',
            'joined_at' => now(),
            'owner_nickname' => null,
            'left_at' => null,
            'removal_type' => null,
            'removed_by_user_id' => null,
            'removed_by_admin_id' => null,
            'can_rejoin' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'owner',
        ]);
    }

    public function left(): static
    {
        return $this->state(fn(array $attributes) => [
            'left_at' => now(),
            'removal_type' => 'self_leave',
            'can_rejoin' => true,
        ]);
    }

    public function kicked(): static
    {
        return $this->state(fn(array $attributes) => [
            'left_at' => now(),
            'removal_type' => 'kicked_by_owner',
            'can_rejoin' => false,
        ]);
    }
}