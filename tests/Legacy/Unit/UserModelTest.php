<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_friend_id_is_generated_and_unique(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->assertEquals(6, strlen($user1->friend_id));
        $this->assertEquals(6, strlen($user2->friend_id));
        $this->assertNotEquals($user1->friend_id, $user2->friend_id);
    }
}
