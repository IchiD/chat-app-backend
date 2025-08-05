<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_generates_friend_id()
    {
        $user = User::factory()->create();
        
        $this->assertNotNull($user->friend_id);
        $this->assertIsString($user->friend_id);
        $this->assertEquals(6, strlen($user->friend_id));
    }

    public function test_friend_id_is_unique()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $this->assertNotEquals($user1->friend_id, $user2->friend_id);
    }

    public function test_user_has_required_attributes()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    public function test_user_soft_delete()
    {
        $user = User::factory()->create();
        $userId = $user->id;
        
        $user->delete();
        
        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->assertNull(User::find($userId));
        $this->assertNotNull(User::withTrashed()->find($userId));
    }
}