<?php

namespace Tests\Unit\Admin;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationManagementUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_excludes_support_conversations()
    {
        Conversation::factory()->create(['type' => 'direct']);
        Conversation::factory()->create(['type' => 'support']);

        $query = Conversation::where('type', '!=', 'support');
        $this->assertCount(1, $query->get());
    }

    public function test_withCount_returns_correct_message_count()
    {
        $conv = Conversation::factory()->create(['type' => 'direct']);
        $user = User::factory()->create();
        Participant::create(['conversation_id' => $conv->id, 'user_id' => $user->id]);
        Message::factory()->create([
            'conversation_id' => $conv->id,
            'sender_id' => $user->id,
            'text_content' => 'a',
        ]);

        $counted = Conversation::withCount('messages')->find($conv->id);
        $this->assertEquals(1, $counted->messages_count);
    }

    public function test_search_query_builder_works_correctly()
    {
        $conv = Conversation::factory()->create(['type' => 'direct']);
        $user = User::factory()->create(['name' => 'TestUser']);
        Participant::create(['conversation_id' => $conv->id, 'user_id' => $user->id]);

        $query = Conversation::where('type', '!=', 'support');
        $search = 'TestUser';
        $query->where(function ($q) use ($search) {
            $q->orWhereHas('participants', function ($u) use ($search) {
                $u->where('name', 'LIKE', "%$search%");
            });
        });

        $this->assertCount(1, $query->get());
    }
}
