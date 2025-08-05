<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Friendship;
use App\Models\Conversation;
use App\Models\Participant;
use App\Models\Message;

class UserRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_friendship_relationships(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $friendship = Friendship::create([
            'user_id' => $sender->id,
            'friend_id' => $receiver->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        $this->assertTrue($sender->sentFriendships->contains($friendship));
        $this->assertTrue($receiver->receivedFriendships->contains($friendship));
    }

    public function test_user_and_conversation_relationship(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        Participant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($user->conversations->contains($conversation));
    }

    public function test_user_and_message_relationship(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['type' => 'direct']);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'content_type' => 'text',
            'text_content' => 'hello',
        ]);

        $this->assertTrue($user->messages->contains($message));
    }

    public function test_friends_method_returns_accepted_friends(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $other = User::factory()->create();

        Friendship::create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => Friendship::STATUS_ACCEPTED,
        ]);

        Friendship::create([
            'user_id' => $other->id,
            'friend_id' => $user->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        $friends = $user->friends();

        $this->assertTrue($friends->contains('id', $friend->id));
        $this->assertFalse($friends->contains('id', $other->id));
    }

    public function test_friend_requests_method_returns_pending_received_requests(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $another = User::factory()->create();

        Friendship::create([
            'user_id' => $sender->id,
            'friend_id' => $user->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        Friendship::create([
            'user_id' => $another->id,
            'friend_id' => $user->id,
            'status' => Friendship::STATUS_ACCEPTED,
        ]);

        $requests = $user->friendRequests();

        $this->assertTrue($requests->contains('user_id', $sender->id));
        $this->assertFalse($requests->contains('user_id', $another->id));
    }
}
