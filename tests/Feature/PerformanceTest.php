<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 大量メッセージのページネーションテスト
     */
    public function test_can_handle_large_message_pagination()
    {
        $user = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create(['type' => 'friend_chat']);
        
        // 1000件のメッセージを作成
        $startTime = microtime(true);
        Message::factory()->count(1000)->create([
            'chat_room_id' => $chatRoom->id,
            'sender_id' => $user->id,
        ]);
        $createTime = microtime(true) - $startTime;
        
        // 作成時間が妥当であることを確認（10秒以内）
        $this->assertLessThan(10, $createTime);

        Sanctum::actingAs($user);

        // ページネーション付きでメッセージを取得
        $startTime = microtime(true);
        $response = $this->getJson("/api/conversations/room/{$chatRoom->room_token}/messages?page=1&per_page=20");
        $responseTime = microtime(true) - $startTime;

        $response->assertStatus(200);
        $response->assertJsonCount(20, 'data');
        
        // レスポンス時間が1秒以内であることを確認
        $this->assertLessThan(1, $responseTime);
    }

    /**
     * 大量友達リストの処理テスト
     */
    public function test_can_handle_large_friends_list()
    {
        $user = User::factory()->create();
        $friends = User::factory()->count(100)->create();

        // 100人の友達関係を作成
        $startTime = microtime(true);
        foreach ($friends as $friend) {
            Friendship::factory()->create([
                'user_id' => $user->id,
                'friend_id' => $friend->id,
                'status' => Friendship::STATUS_ACCEPTED,
            ]);
        }
        $createTime = microtime(true) - $startTime;
        
        // 作成時間が妥当であることを確認（5秒以内）
        $this->assertLessThan(5, $createTime);

        Sanctum::actingAs($user);

        // 友達リストを取得
        $startTime = microtime(true);
        $response = $this->getJson('/api/friends');
        $responseTime = microtime(true) - $startTime;

        $response->assertStatus(200);
        
        // レスポンス時間が1秒以内であることを確認
        $this->assertLessThan(1, $responseTime);
    }

    /**
     * 同時メッセージ送信テスト
     */
    public function test_concurrent_message_sending()
    {
        $users = User::factory()->count(10)->create();
        $chatRoom = ChatRoom::factory()->create(['type' => 'group_chat']);
        
        // 全ユーザーをグループメンバーに追加
        foreach ($users as $user) {
            GroupMember::create([
                'group_id' => 1,
                'user_id' => $user->id,
                'role' => 'member',
            ]);
        }

        $startTime = microtime(true);
        $responses = [];

        // 10人が同時にメッセージを送信
        foreach ($users as $index => $user) {
            Sanctum::actingAs($user);
            $response = $this->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
                'text_content' => "Message from user {$index}",
            ]);
            $responses[] = $response;
        }

        $totalTime = microtime(true) - $startTime;

        // すべてのリクエストが成功したことを確認
        foreach ($responses as $response) {
            $response->assertStatus(201);
        }

        // 合計時間が3秒以内であることを確認
        $this->assertLessThan(3, $totalTime);

        // すべてのメッセージが保存されたことを確認
        $this->assertEquals(10, Message::where('chat_room_id', $chatRoom->id)->count());
    }

    /**
     * データベースクエリ最適化テスト（N+1問題の検出）
     */
    public function test_n_plus_one_query_prevention()
    {
        $user = User::factory()->create();
        
        // 20個のチャットルームを作成
        $chatRooms = ChatRoom::factory()->count(20)->create(['type' => 'friend_chat']);
        
        // 各チャットルームに5つのメッセージを作成
        foreach ($chatRooms as $chatRoom) {
            Message::factory()->count(5)->create(['chat_room_id' => $chatRoom->id]);
        }

        Sanctum::actingAs($user);

        // クエリログを有効化
        DB::connection()->enableQueryLog();

        // チャットルーム一覧を取得（メッセージ数付き）
        $response = $this->getJson('/api/conversations');

        $queries = DB::getQueryLog();
        DB::connection()->disableQueryLog();

        $response->assertStatus(200);

        // クエリ数が適切であることを確認（N+1が発生していない）
        // 期待値: 認証関連 + チャットルーム取得 + カウント取得で10クエリ以内
        $this->assertLessThan(10, count($queries), 'Too many queries detected. Possible N+1 problem.');
    }

    /**
     * 大量データでのメモリ使用量テスト
     */
    public function test_memory_usage_with_large_data()
    {
        $user = User::factory()->create();
        
        // メモリ使用量を記録
        $initialMemory = memory_get_usage();

        // 5000件のメッセージを作成
        Message::factory()->count(5000)->create([
            'text_content' => str_repeat('a', 100), // 100文字のメッセージ
        ]);

        Sanctum::actingAs($user);

        // 大量データを処理
        $response = $this->getJson('/api/messages/all'); // 仮のエンドポイント

        $finalMemory = memory_get_usage();
        $memoryUsed = ($finalMemory - $initialMemory) / 1024 / 1024; // MB

        // メモリ使用量が50MB以内であることを確認
        $this->assertLessThan(50, $memoryUsed, "Memory usage exceeded 50MB: {$memoryUsed}MB");
    }

    /**
     * レート制限のパフォーマンステスト
     */
    public function test_rate_limiting_performance()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $startTime = microtime(true);
        $responses = [];

        // レート制限内でリクエストを送信（10回）
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/test-rate-limit');
            $responses[] = $response->status();
        }

        // 11回目は制限される
        $response = $this->postJson('/api/test-rate-limit');
        $limitedStatus = $response->status();

        $totalTime = microtime(true) - $startTime;

        // 最初の10回は成功
        foreach (array_slice($responses, 0, 10) as $status) {
            $this->assertNotEquals(429, $status);
        }

        // 11回目は制限される
        $this->assertEquals(429, $limitedStatus);

        // 処理時間が2秒以内であることを確認
        $this->assertLessThan(2, $totalTime);
    }

    /**
     * 大規模グループのメンバー管理パフォーマンステスト
     */
    public function test_large_group_member_management()
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
            'max_members' => 200,
        ]);

        // 200人のメンバーを追加
        $startTime = microtime(true);
        $members = User::factory()->count(199)->create();
        
        $memberData = [];
        foreach ($members as $member) {
            $memberData[] = [
                'group_id' => $group->id,
                'user_id' => $member->id,
                'role' => 'member',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // バルクインサートで高速化
        GroupMember::insert($memberData);
        
        $createTime = microtime(true) - $startTime;

        // 作成時間が5秒以内であることを確認
        $this->assertLessThan(5, $createTime);

        Sanctum::actingAs($owner);

        // メンバー一覧を取得
        $startTime = microtime(true);
        $response = $this->getJson("/api/groups/{$group->id}/members");
        $responseTime = microtime(true) - $startTime;

        $response->assertStatus(200);
        
        // レスポンス時間が1秒以内であることを確認
        $this->assertLessThan(1, $responseTime);
    }

    /**
     * キャッシュ効果のテスト
     */
    public function test_cache_effectiveness()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 初回リクエスト（キャッシュなし）
        $startTime = microtime(true);
        $response1 = $this->getJson('/api/app/config');
        $firstRequestTime = microtime(true) - $startTime;

        $response1->assertStatus(200);

        // 2回目のリクエスト（キャッシュあり）
        $startTime = microtime(true);
        $response2 = $this->getJson('/api/app/config');
        $secondRequestTime = microtime(true) - $startTime;

        $response2->assertStatus(200);

        // キャッシュにより2回目の方が高速であることを確認
        $this->assertLessThan($firstRequestTime, $secondRequestTime);
        
        // 2回目は0.1秒以内であることを確認
        $this->assertLessThan(0.1, $secondRequestTime);
    }
}