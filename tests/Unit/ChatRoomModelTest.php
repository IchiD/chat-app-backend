<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ChatRoom;
use App\Models\User;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChatRoomModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_room_creation_generates_room_token()
    {
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'friend_chat'
        ]);
        
        $this->assertNotNull($chatRoom->room_token);
        $this->assertIsString($chatRoom->room_token);
        $this->assertEquals(16, strlen($chatRoom->room_token));
    }

    public function test_room_token_is_unique()
    {
        $chatRoom1 = ChatRoom::factory()->create(['type' => 'friend_chat']);
        $chatRoom2 = ChatRoom::factory()->create(['type' => 'friend_chat']);
        
        $this->assertNotEquals($chatRoom1->room_token, $chatRoom2->room_token);
    }

    public function test_friend_chat_room_creation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'friend_chat',
            'participant1_id' => $user1->id,
            'participant2_id' => $user2->id,
        ]);
        
        $this->assertEquals('friend_chat', $chatRoom->type);
        $this->assertEquals($user1->id, $chatRoom->participant1_id);
        $this->assertEquals($user2->id, $chatRoom->participant2_id);
        $this->assertNull($chatRoom->group_id);
    }

    public function test_group_chat_room_creation()
    {
        $group = Group::factory()->create();
        
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
            'participant1_id' => null,
            'participant2_id' => null,
        ]);
        
        $this->assertEquals('group_chat', $chatRoom->type);
        $this->assertEquals($group->id, $chatRoom->group_id);
        $this->assertNull($chatRoom->participant1_id);
        $this->assertNull($chatRoom->participant2_id);
    }

    public function test_support_chat_room_creation()
    {
        $user = User::factory()->create();
        
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'support_chat',
            'participant1_id' => $user->id,
            'participant2_id' => null,
            'group_id' => null,
        ]);
        
        $this->assertEquals('support_chat', $chatRoom->type);
        $this->assertEquals($user->id, $chatRoom->participant1_id);
        $this->assertNull($chatRoom->participant2_id);
        $this->assertNull($chatRoom->group_id);
    }

    public function test_chat_room_soft_delete()
    {
        $chatRoom = ChatRoom::factory()->create(['type' => 'friend_chat']);
        $chatRoomId = $chatRoom->id;
        
        $chatRoom->delete();
        
        $this->assertSoftDeleted('chat_rooms', ['id' => $chatRoomId]);
        $this->assertNull(ChatRoom::find($chatRoomId));
        $this->assertNotNull(ChatRoom::withTrashed()->find($chatRoomId));
    }
}