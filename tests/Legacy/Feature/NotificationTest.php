<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createConversation(User $user1, User $user2): Conversation
    {
        $conversation = Conversation::create(['type' => 'direct']);
        $conversation->conversationParticipants()->createMany([
            ['user_id' => $user1->id],
            ['user_id' => $user2->id],
        ]);
        return $conversation;
    }

    public function test_notification_subscribe(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'subscription' => [
                'endpoint' => 'https://example.com/test-endpoint',
                'keys' => [
                    'p256dh' => 'p256dh_key',
                    'auth' => 'auth_key',
                ],
            ],
        ];

        $response = $this->postJson('/api/notifications/subscribe', $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => 'https://example.com/test-endpoint',
            'subscribable_id' => $user->id,
        ]);
    }

    public function test_notification_unsubscribe(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://example.com/test-endpoint', 'p', 'a');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notifications/unsubscribe', [
            'endpoint' => 'https://example.com/test-endpoint',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://example.com/test-endpoint',
        ]);
    }

    public function test_send_test_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notifications/test');

        $response->assertOk()->assertJson(['success' => true]);

        Notification::assertSentTo(
            $user,
            PushNotification::class,
            function (PushNotification $notification) use ($user) {
                $message = $notification->toWebPush($user);
                $data = $message->toArray();
                $this->assertSame('テスト通知', $data['title']);
                $this->assertSame('これはテスト通知です。', $data['body']);

                $this->assertEquals('test-notification', $data['tag']);
                $this->assertTrue($data['requireInteraction']);
                return true;
            }
        );
    }

    public function test_auto_notification_on_message_receive(): void
    {
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $sender->sendFriendRequest($recipient->id);
        $recipient->acceptFriendRequest($sender->id);

        $conversation = $this->createConversation($sender, $recipient);

        Sanctum::actingAs($sender);

        $response = $this->postJson("/api/conversations/room/{$conversation->room_token}/messages", [
            'text_content' => 'Hello there',
        ]);

        $response->assertCreated();

        Notification::assertSentTo(
            $recipient,
            PushNotification::class,
            function (PushNotification $notification) use ($recipient, $sender, $conversation) {
                $message = $notification->toWebPush($recipient);
                $data = $message->toArray();
                $this->assertEquals($sender->name . 'からのメッセージ', $data['title']);
                $this->assertEquals('new_message', $data['data']['type']);
                $this->assertEquals($conversation->id, $data['data']['room_id']);
                $this->assertEquals($conversation->room_token, $data['data']['room_token']);
                return true;
            }
        );
    }

    public function test_vapid_keys_are_settable(): void
    {
        config(['webpush.vapid.public_key' => 'publickey']);
        config(['webpush.vapid.private_key' => 'privatekey']);

        $this->assertSame('publickey', config('webpush.vapid.public_key'));
        $this->assertSame('privatekey', config('webpush.vapid.private_key'));
    }

    public function test_invalid_subscription_unsubscribe_is_handled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notifications/unsubscribe', [
            'endpoint' => 'https://invalid-endpoint',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_notification_payload_structure(): void
    {
        $user = User::factory()->make();

        $notification = new PushNotification(
            'Title',
            'Body',
            ['foo' => 'bar'],
            ['tag' => 'tag-example']
        );

        $message = $notification->toWebPush($user);

        $data = $message->toArray();
        $this->assertEquals('Title', $data['title']);
        $this->assertEquals('Body', $data['body']);
        $this->assertEquals(['foo' => 'bar'], $data['data']);
        $this->assertEquals('tag-example', $data['tag']);
    }
}
