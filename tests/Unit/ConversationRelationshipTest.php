<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ConversationRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_user_relationship(): void
    {
        $conversation = Conversation::create(['type' => 'direct']);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user1->id]);
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user2->id]);

        $users = $conversation->participants;

        $this->assertCount(2, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
    }

    public function test_conversation_message_relationship(): void
    {
        $conversation = Conversation::create(['type' => 'direct']);
        $user = User::factory()->create();

        $first = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'text_content' => 'first',
            'sent_at' => Carbon::now()->subMinute(),
        ]);

        $second = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'text_content' => 'second',
            'sent_at' => Carbon::now(),
        ]);

        $messages = $conversation->messages;

        $this->assertCount(2, $messages);
        $this->assertEquals($second->id, $messages->first()->id);
        $this->assertEquals($first->id, $messages->last()->id);
    }

    public function test_participants_method_returns_all_users(): void
    {
        $conversation = Conversation::create(['type' => 'direct']);
        $user = User::factory()->create();
        Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

        $users = $conversation->participants()->get();

        $this->assertCount(1, $users);
        $this->assertTrue($users->first()->is($user));
    }

    public function test_latest_message_method_returns_latest_non_deleted_message(): void
    {
        $conversation = Conversation::create(['type' => 'direct']);
        $user = User::factory()->create();

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'text_content' => 'old',
            'sent_at' => Carbon::now()->subMinutes(2),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'text_content' => 'deleted',
            'sent_at' => Carbon::now()->subMinute(),
            'deleted_at' => Carbon::now()->subMinute(),
        ]);

        $latest = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'text_content' => 'latest',
            'sent_at' => Carbon::now(),
        ]);

        $this->assertTrue($conversation->latestMessage()->exists());
        $this->assertTrue($conversation->latestMessage->is($latest));
    }
}
