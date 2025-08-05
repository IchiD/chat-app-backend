<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use App\Models\ChatRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'chat_room_id' => ChatRoom::factory(),
            'sender_id' => User::factory(),
            'admin_sender_id' => null,
            'content_type' => 'text',
            'text_content' => $this->faker->sentence(),
            'sent_at' => now(),
        ];
    }

    public function adminMessage($adminId): static
    {
        return $this->state(fn(array $attributes) => [
            'sender_id' => null,
            'admin_sender_id' => $adminId,
        ]);
    }
}
