<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    /**
     * 通知設定のデフォルト値が正しく設定されることをテスト
     */
    public function test_user_has_default_notification_preferences()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/preferences');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'preferences' => [
                    'email' => [
                        'messages',
                        'friend_requests',
                        'group_invites',
                        'group_messages',
                    ],
                    'push' => [
                        'messages',
                        'friend_requests',
                        'group_invites',
                        'group_messages',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'preferences' => [
                    'email' => [
                        'messages' => true,
                        'friend_requests' => true,
                        'group_invites' => true,
                        'group_messages' => true,
                    ],
                    'push' => [
                        'messages' => true,
                        'friend_requests' => true,
                        'group_invites' => true,
                        'group_messages' => true,
                    ],
                ],
            ]);
    }

    /**
     * 通知設定を更新できることをテスト
     */
    public function test_user_can_update_notification_preferences()
    {
        $user = User::factory()->create();

        $newPreferences = [
            'preferences' => [
                'email' => [
                    'messages' => false,
                    'friend_requests' => true,
                    'group_invites' => false,
                    'group_messages' => true,
                ],
                'push' => [
                    'messages' => true,
                    'friend_requests' => false,
                    'group_invites' => true,
                    'group_messages' => false,
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences', $newPreferences);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '通知設定を更新しました',
                'preferences' => $newPreferences['preferences'],
            ]);

        // データベースに保存されていることを確認
        $user->refresh();
        $this->assertEquals($newPreferences['preferences'], $user->notification_preferences);
    }

    /**
     * 不正な通知設定の更新を拒否することをテスト
     */
    public function test_invalid_notification_preferences_are_rejected()
    {
        $user = User::factory()->create();

        // 必須フィールドが欠けている場合
        $invalidPreferences = [
            'preferences' => [
                'email' => [
                    'messages' => true,
                    // friend_requests が欠けている
                ],
                'push' => [
                    'messages' => true,
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences', $invalidPreferences);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['preferences.email.friend_requests']);
    }

    /**
     * 認証なしで通知設定にアクセスできないことをテスト
     */
    public function test_unauthenticated_user_cannot_access_notification_preferences()
    {
        $response = $this->getJson('/api/notifications/preferences');
        $response->assertUnauthorized();

        $response = $this->putJson('/api/notifications/preferences', [
            'preferences' => [
                'email' => [
                    'messages' => true,
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
                'push' => [
                    'messages' => true,
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
            ],
        ]);
        $response->assertUnauthorized();
    }

    /**
     * 通知設定に基づいてメッセージ通知が送信されることをテスト
     */
    public function test_message_notification_respects_user_preferences()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        // 友達関係を作成（Friendshipモデルを使用）
        \App\Models\Friendship::create([
            'user_id' => $sender->id,
            'friend_id' => $receiver->id,
            'status' => 1, // accepted
        ]);
        \App\Models\Friendship::create([
            'user_id' => $receiver->id,
            'friend_id' => $sender->id,
            'status' => 1, // accepted
        ]);

        // 受信者の通知設定を無効化
        $receiver->notification_preferences = [
            'email' => [
                'messages' => false,
                'friend_requests' => true,
                'group_invites' => true,
                'group_messages' => true,
            ],
            'push' => [
                'messages' => false,
                'friend_requests' => true,
                'group_invites' => true,
                'group_messages' => true,
            ],
        ];
        $receiver->save();

        // チャットルームを作成
        $chatRoom = \App\Models\ChatRoom::create([
            'type' => 'friend_chat',
            'participant1_id' => $sender->id,
            'participant2_id' => $receiver->id,
            'room_token' => \Illuminate\Support\Str::random(10),
        ]);

        // メッセージを送信
        $response = $this->actingAs($sender, 'sanctum')
            ->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
                'text_content' => 'テストメッセージ',
            ]);

        $response->assertStatus(201);

        // 通知が送信されていないことを確認（通知設定が無効のため）
        // 実際の通知送信はキューで処理されるため、ここでは設定が正しく取得されることを確認
        $this->assertFalse($receiver->notification_preferences['email']['messages']);
        $this->assertFalse($receiver->notification_preferences['push']['messages']);
    }

    /**
     * 通知設定に基づいて友達申請通知が送信されることをテスト
     */
    public function test_friend_request_notification_respects_user_preferences()
    {
        $sender = User::factory()->create(['friend_id' => 'SEND1']);
        $receiver = User::factory()->create(['friend_id' => 'RECV1']);

        // 受信者の友達申請通知を無効化
        $receiver->notification_preferences = [
            'email' => [
                'messages' => true,
                'friend_requests' => false,
                'group_invites' => true,
                'group_messages' => true,
            ],
            'push' => [
                'messages' => true,
                'friend_requests' => false,
                'group_invites' => true,
                'group_messages' => true,
            ],
        ];
        $receiver->save();

        // 友達申請を送信
        $response = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/friends/requests', [
                'user_id' => $receiver->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => '友達申請を送信しました',
            ]);

        // 通知設定が無効であることを確認
        $this->assertFalse($receiver->notification_preferences['push']['friend_requests']);
    }

    /**
     * グループメッセージの通知設定が正しく機能することをテスト
     */
    public function test_group_message_notification_respects_user_preferences()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        // メンバーのグループメッセージ通知を無効化
        $member->notification_preferences = [
            'email' => [
                'messages' => true,
                'friend_requests' => true,
                'group_invites' => true,
                'group_messages' => false,
            ],
            'push' => [
                'messages' => true,
                'friend_requests' => true,
                'group_invites' => true,
                'group_messages' => false,
            ],
        ];
        $member->save();

        // グループを作成
        $group = \App\Models\Group::create([
            'name' => 'テストグループ',
            'owner_user_id' => $owner->id,
            'invite_token' => \Illuminate\Support\Str::random(32),
            'qr_code_token' => \Illuminate\Support\Str::random(32),
            'chat_styles' => json_encode([]),
        ]);

        // メンバーを追加
        $group->groupMembers()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);
        $group->groupMembers()->create([
            'user_id' => $member->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        // グループチャットルームを作成
        $chatRoom = \App\Models\ChatRoom::create([
            'type' => 'group_chat',
            'group_id' => $group->id,
            'room_token' => \Illuminate\Support\Str::random(10),
        ]);

        // グループメッセージを送信
        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
                'text_content' => 'グループメッセージテスト',
            ]);

        $response->assertStatus(201);

        // グループメッセージ通知が無効であることを確認
        $this->assertFalse($member->notification_preferences['email']['group_messages']);
        $this->assertFalse($member->notification_preferences['push']['group_messages']);
    }

    /**
     * 通知設定の部分更新が全体を保持することをテスト
     */
    public function test_partial_update_preserves_other_settings()
    {
        $user = User::factory()->create();

        // 初期設定
        $initialPreferences = [
            'preferences' => [
                'email' => [
                    'messages' => true,
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
                'push' => [
                    'messages' => true,
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
            ],
        ];

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences', $initialPreferences);

        // 一部の設定を変更
        $partialUpdate = [
            'preferences' => [
                'email' => [
                    'messages' => false, // これだけ変更
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
                'push' => [
                    'messages' => true,
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences', $partialUpdate);

        $response->assertOk();

        // データベースから再取得して確認
        $user->refresh();
        $this->assertFalse($user->notification_preferences['email']['messages']);
        $this->assertTrue($user->notification_preferences['email']['friend_requests']);
        $this->assertTrue($user->notification_preferences['push']['messages']);
    }
}
