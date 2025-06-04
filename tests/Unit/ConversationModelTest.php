<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Conversation;

class ConversationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_token_is_generated(): void
    {
        $conversation = Conversation::create(['type' => 'direct']);

        $this->assertNotEmpty($conversation->room_token);
        $this->assertEquals(16, strlen($conversation->room_token));
    }
}
