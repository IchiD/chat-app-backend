<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Message;
use App\Models\ChatRoom;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MessageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_message_creation()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create(['type' => 'friend_chat']);
        
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'sender_id' => $user->id,
            'content_type' => 'text',
            'text_content' => 'Hello, world!',
        ]);
        
        $this->assertEquals($chatRoom->id, $message->chat_room_id);
        $this->assertEquals($user->id, $message->sender_id);
        $this->assertNull($message->admin_sender_id);
        $this->assertEquals('text', $message->content_type);
        $this->assertEquals('Hello, world!', $message->text_content);
        $this->assertNotNull($message->sent_at);
    }

    public function test_admin_message_creation()
    {
        $admin = Admin::factory()->create();
        $chatRoom = ChatRoom::factory()->create(['type' => 'support_chat']);
        
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
            'sender_id' => null,
            'admin_sender_id' => $admin->id,
            'content_type' => 'text',
            'text_content' => 'Admin response',
        ]);
        
        $this->assertEquals($chatRoom->id, $message->chat_room_id);
        $this->assertNull($message->sender_id);
        $this->assertEquals($admin->id, $message->admin_sender_id);
        $this->assertEquals('text', $message->content_type);
        $this->assertEquals('Admin response', $message->text_content);
    }

    public function test_message_belongs_to_chat_room()
    {
        $chatRoom = ChatRoom::factory()->create(['type' => 'friend_chat']);
        $message = Message::factory()->create([
            'chat_room_id' => $chatRoom->id,
        ]);
        
        $this->assertInstanceOf(ChatRoom::class, $message->chatRoom);
        $this->assertEquals($chatRoom->id, $message->chatRoom->id);
    }

    public function test_message_belongs_to_user_sender()
    {
        $user = User::factory()->create();
        $message = Message::factory()->create([
            'sender_id' => $user->id,
        ]);
        
        $this->assertInstanceOf(User::class, $message->sender);
        $this->assertEquals($user->id, $message->sender->id);
    }

    public function test_message_belongs_to_admin_sender()
    {
        $admin = Admin::factory()->create();
        $message = Message::factory()->create([
            'admin_sender_id' => $admin->id,
        ]);
        
        $this->assertInstanceOf(Admin::class, $message->adminSender);
        $this->assertEquals($admin->id, $message->adminSender->id);
    }

    public function test_message_soft_delete_by_user()
    {
        $message = Message::factory()->create();
        
        $message->update(['deleted_at' => now()]);
        
        $this->assertNotNull($message->deleted_at);
        $this->assertNull($message->admin_deleted_at);
    }

    public function test_message_deletion_by_admin()
    {
        $admin = Admin::factory()->create();
        $message = Message::factory()->create();
        
        $message->update([
            'admin_deleted_at' => now(),
            'admin_deleted_by' => $admin->id,
            'admin_deleted_reason' => 'Inappropriate content',
        ]);
        
        $this->assertNotNull($message->admin_deleted_at);
        $this->assertEquals($admin->id, $message->admin_deleted_by);
        $this->assertEquals('Inappropriate content', $message->admin_deleted_reason);
    }
}