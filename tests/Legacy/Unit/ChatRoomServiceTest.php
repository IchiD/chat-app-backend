<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ChatRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatRoomServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatRoomService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ChatRoomService::class);
    }

    public function test_create_friend_chat_room_and_list(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $result = $this->service->createFriendChatRoom($user1, $user2->id);
        $this->assertEquals(ChatRoomService::STATUS_SUCCESS, $result['status']);
        $this->assertEquals(201, $result['http_status']);

        $list = $this->service->getUserChatRoomsList($user1);
        $this->assertCount(1, $list['data']);
        $this->assertEquals($result['data']['id'], $list['data'][0]['id']);
    }

    public function test_create_friend_chat_room_returns_existing(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->sendFriendRequest($user2->id);
        $user2->acceptFriendRequest($user1->id);

        $first = $this->service->createFriendChatRoom($user1, $user2->id);
        $second = $this->service->createFriendChatRoom($user1, $user2->id);

        $this->assertEquals(ChatRoomService::STATUS_SUCCESS, $second['status']);
        $this->assertEquals(200, $second['http_status']);
        $this->assertEquals($first['data']['id'], $second['data']['id']);
    }

    public function test_create_friend_chat_room_with_deleted_user_fails(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create(['deleted_at' => now()]);

        $result = $this->service->createFriendChatRoom($user1, $user2->id);

        $this->assertEquals(ChatRoomService::STATUS_ERROR, $result['status']);
        $this->assertEquals('deleted_user', $result['error_type']);
        $this->assertEquals(403, $result['http_status']);
    }

    public function test_create_friend_chat_room_with_non_friend_fails(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $result = $this->service->createFriendChatRoom($user1, $user2->id);

        $this->assertEquals(ChatRoomService::STATUS_ERROR, $result['status']);
        $this->assertEquals('not_friend', $result['error_type']);
        $this->assertEquals(403, $result['http_status']);
    }

    public function test_get_user_chat_rooms_list_filters_and_paginates(): void
    {
        $user = User::factory()->create();
        $friends = User::factory()->count(4)->create();

        foreach ($friends as $friend) {
            $user->sendFriendRequest($friend->id);
            $friend->acceptFriendRequest($user->id);
            $this->service->createFriendChatRoom($user, $friend->id);
        }

        // one friend is deleted -> should not appear
        $friends[0]->update(['deleted_at' => now()]);

        $page1 = $this->service->getUserChatRoomsList($user, 1, 2);
        $this->assertCount(2, $page1['data']);
        $this->assertEquals(3, $page1['total']);
        $this->assertEquals(2, $page1['last_page']);

        $page2 = $this->service->getUserChatRoomsList($user, 2, 2);
        $this->assertCount(1, $page2['data']);
    }
}
