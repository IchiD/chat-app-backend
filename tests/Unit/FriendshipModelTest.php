<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FriendshipModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_friendship_creation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $friendship = Friendship::factory()->create([
            'user_id' => $user1->id,
            'friend_id' => $user2->id,
            'status' => Friendship::STATUS_PENDING,
        ]);
        
        $this->assertEquals($user1->id, $friendship->user_id);
        $this->assertEquals($user2->id, $friendship->friend_id);
        $this->assertEquals(Friendship::STATUS_PENDING, $friendship->status);
    }

    public function test_friendship_acceptance()
    {
        $friendship = Friendship::factory()->create(['status' => Friendship::STATUS_PENDING]);
        
        $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);
        
        $this->assertEquals(Friendship::STATUS_ACCEPTED, $friendship->status);
    }

    public function test_friendship_rejection()
    {
        $friendship = Friendship::factory()->create(['status' => Friendship::STATUS_PENDING]);
        
        $friendship->update(['status' => Friendship::STATUS_REJECTED]);
        
        $this->assertEquals(Friendship::STATUS_REJECTED, $friendship->status);
    }

    public function test_friendship_belongs_to_user()
    {
        $user = User::factory()->create();
        $friendship = Friendship::factory()->create([
            'user_id' => $user->id,
        ]);
        
        $this->assertInstanceOf(User::class, $friendship->user);
        $this->assertEquals($user->id, $friendship->user->id);
    }

    public function test_friendship_belongs_to_friend()
    {
        $friend = User::factory()->create();
        $friendship = Friendship::factory()->create([
            'friend_id' => $friend->id,
        ]);
        
        $this->assertInstanceOf(User::class, $friendship->friend);
        $this->assertEquals($friend->id, $friendship->friend->id);
    }

    public function test_friendship_soft_delete()
    {
        $friendship = Friendship::factory()->create();
        $friendshipId = $friendship->id;
        
        $friendship->delete();
        
        $this->assertSoftDeleted('friendships', ['id' => $friendshipId]);
        $this->assertNull(Friendship::find($friendshipId));
        $this->assertNotNull(Friendship::withTrashed()->find($friendshipId));
    }
}