<?php

namespace Database\Factories;

use App\Models\ChatRoom;
use App\Models\User;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatRoomFactory extends Factory
{
    protected $model = ChatRoom::class;

    public function definition(): array
    {
        return [
            'type' => 'friend_chat',
            'participant1_id' => User::factory(),
            'participant2_id' => User::factory(),
            'group_id' => null,
        ];
    }

    public function friendChat(User $user1, User $user2): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'friend_chat',
            'participant1_id' => $user1->id,
            'participant2_id' => $user2->id,
            'group_id' => null,
        ]);
    }

    public function groupChat(Group $group): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'group_chat',
            'group_id' => $group->id,
            'participant1_id' => null,
            'participant2_id' => null,
        ]);
    }

    public function supportChat(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'support_chat',
            'participant1_id' => $user->id,
            'participant2_id' => null,
            'group_id' => null,
        ]);
    }
}