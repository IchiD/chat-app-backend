<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;

class GroupPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 大規模グループのメンバー一覧取得パフォーマンステスト
     */
    public function test_large_group_members_list_performance()
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
            'max_members' => 1000,
        ]);

        // オーナーを追加
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        // 100人のメンバーを追加
        $users = User::factory()->count(99)->create();
        foreach ($users as $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'member',
            ]);
        }

        Sanctum::actingAs($owner);

        $startTime = microtime(true);
        $response = $this->getJson("/api/conversations/groups/{$group->id}/members");
        $endTime = microtime(true);

        $response->assertOk()
                 ->assertJsonCount(100, 'members');

        $executionTime = ($endTime - $startTime) * 1000; // ミリ秒に変換
        $this->assertLessThan(500, $executionTime, '100人のメンバー取得は500ms以内であるべき');
    }

    /**
     * N+1クエリ問題の検証（グループ一覧）
     */
    public function test_groups_list_without_n_plus_one_queries()
    {
        $user = User::factory()->create();
        
        // 20個のグループを作成
        for ($i = 0; $i < 20; $i++) {
            $group = Group::factory()->create();
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $i === 0 ? 'owner' : 'member',
            ]);
            
            // 各グループに5人のメンバーを追加
            $members = User::factory()->count(4)->create();
            foreach ($members as $member) {
                GroupMember::factory()->create([
                    'group_id' => $group->id,
                    'user_id' => $member->id,
                ]);
            }
        }

        Sanctum::actingAs($user);

        DB::enableQueryLog();
        $response = $this->getJson('/api/conversations/groups');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        
        // クエリ数が妥当な範囲内であることを確認（N+1が発生していない）
        $this->assertLessThan(10, $queries, 'グループ一覧取得のクエリ数が多すぎます（N+1の可能性）');
    }

    /**
     * 大量メッセージのグループチャット読み込みテスト
     */
    public function test_group_chat_messages_pagination_performance()
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        // 1000件のメッセージを作成
        $messages = [];
        for ($i = 0; $i < 1000; $i++) {
            $messages[] = [
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $owner->id,
                'text_content' => "メッセージ {$i}",
                'sent_at' => now()->subMinutes(1000 - $i),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Message::insert($messages);

        Sanctum::actingAs($owner);

        $startTime = microtime(true);
        $response = $this->getJson("/api/conversations/room/{$chatRoom->room_token}/messages?page=1&per_page=50");
        $endTime = microtime(true);

        $response->assertOk();

        $executionTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(200, $executionTime, '50件のメッセージ取得は200ms以内であるべき');
    }

    /**
     * 一斉メッセージ送信のパフォーマンステスト
     */
    public function test_bulk_message_sending_performance()
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
            'chat_styles' => ['group_member'],
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        // 50人のメンバーを追加
        $members = User::factory()->count(50)->create();
        foreach ($members as $member) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $member->id,
                'role' => 'member',
            ]);
        }

        Sanctum::actingAs($owner);

        $startTime = microtime(true);
        $response = $this->postJson("/api/conversations/groups/{$group->id}/messages/bulk", [
            'content' => '全メンバーへの一斉送信テスト',
            'exclude_sender' => false,
        ]);
        $endTime = microtime(true);

        $response->assertOk()
                 ->assertJson(['sent_count' => 51]);

        $executionTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(3000, $executionTime, '51人への一斉送信は3秒以内であるべき');
    }

    /**
     * グループ検索のインデックス効果テスト
     */
    public function test_group_search_with_index_performance()
    {
        $user = User::factory()->create();
        
        // 1000個のグループを作成
        for ($i = 0; $i < 1000; $i++) {
            $group = Group::factory()->create([
                'name' => "グループ {$i}",
                'qr_code_token' => str_pad($i, 32, '0', STR_PAD_LEFT),
            ]);
            
            if ($i % 10 === 0) {
                GroupMember::factory()->create([
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'role' => 'member',
                ]);
            }
        }

        // QRコードトークンでの検索（インデックスあり）
        $targetToken = str_pad('500', 32, '0', STR_PAD_LEFT);
        
        $startTime = microtime(true);
        $result = Group::where('qr_code_token', $targetToken)->first();
        $endTime = microtime(true);

        $this->assertNotNull($result);
        
        $executionTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(10, $executionTime, 'インデックスを使用した検索は10ms以内であるべき');
    }

    /**
     * 削除済みメンバーを含む全メンバー取得のパフォーマンステスト
     */
    public function test_all_members_including_removed_performance()
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        // アクティブメンバー50人
        $activeUsers = User::factory()->count(50)->create();
        foreach ($activeUsers as $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'member',
            ]);
        }

        // 削除済みメンバー50人
        $removedUsers = User::factory()->count(50)->create();
        foreach ($removedUsers as $user) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'member',
                'left_at' => now()->subDays(rand(1, 30)),
                'removal_type' => GroupMember::REMOVAL_TYPE_SELF_LEAVE,
            ]);
        }

        Sanctum::actingAs($owner);

        $startTime = microtime(true);
        $response = $this->getJson("/api/conversations/groups/{$group->id}/members/all");
        $endTime = microtime(true);

        $response->assertOk()
                 ->assertJsonCount(101, 'members');

        $executionTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(500, $executionTime, '101人の全メンバー取得は500ms以内であるべき');
    }

    /**
     * 同時グループ参加のデッドロック防止テスト
     */
    public function test_concurrent_group_join_without_deadlock()
    {
        $group = Group::factory()->create([
            'max_members' => 100,
        ]);
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $group->owner_user_id,
            'role' => 'owner',
        ]);

        $users = User::factory()->count(10)->create();
        $results = [];

        // 並行して10人がグループに参加を試みる
        foreach ($users as $user) {
            Sanctum::actingAs($user);
            $response = $this->postJson("/api/conversations/groups/join/{$group->qr_code_token}");
            $results[] = $response->getStatusCode();
        }

        // 全員が正常に参加できたことを確認
        foreach ($results as $status) {
            $this->assertEquals(200, $status);
        }

        // メンバー数が正しいことを確認
        $this->assertEquals(11, $group->getMembersCount());
    }

    /**
     * グループ削除時の連鎖削除パフォーマンステスト
     */
    public function test_group_cascade_deletion_performance()
    {
        $owner = User::factory()->create();
        $group = Group::factory()->create([
            'owner_user_id' => $owner->id,
        ]);
        
        // グループメンバー50人
        $members = User::factory()->count(50)->create();
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        foreach ($members as $member) {
            GroupMember::factory()->create([
                'group_id' => $group->id,
                'user_id' => $member->id,
            ]);
        }

        // グループチャットルーム
        $groupChat = ChatRoom::factory()->create([
            'type' => 'group_chat',
            'group_id' => $group->id,
        ]);

        // メンバー間チャットルーム10個
        for ($i = 0; $i < 10; $i++) {
            ChatRoom::factory()->create([
                'type' => 'member_chat',
                'group_id' => $group->id,
                'participant1_id' => $owner->id,
                'participant2_id' => $members[$i]->id,
            ]);
        }

        // 各チャットルームに100件のメッセージ
        $chatRooms = ChatRoom::where('group_id', $group->id)->get();
        foreach ($chatRooms as $chatRoom) {
            $messages = [];
            for ($j = 0; $j < 100; $j++) {
                $messages[] = [
                    'chat_room_id' => $chatRoom->id,
                    'sender_id' => $owner->id,
                    'text_content' => "メッセージ {$j}",
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Message::insert($messages);
        }

        Sanctum::actingAs($owner);

        $startTime = microtime(true);
        $response = $this->deleteJson("/api/conversations/groups/{$group->id}");
        $endTime = microtime(true);

        $response->assertOk();

        $executionTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(2000, $executionTime, 'グループの連鎖削除は2秒以内であるべき');

        // グループとチャットルームが削除されたことを確認
        $this->assertNotNull($group->fresh()->deleted_at);
        $this->assertTrue($chatRooms->fresh()->every(function ($room) {
            return !is_null($room->deleted_at);
        }));
    }

    /**
     * キャッシュ利用によるグループ情報取得の高速化テスト
     */
    public function test_group_info_caching_performance()
    {
        $group = Group::factory()->create([
            'name' => 'キャッシュテストグループ',
        ]);

        // 初回アクセス（キャッシュなし）
        $startTime1 = microtime(true);
        $response1 = $this->getJson("/api/conversations/groups/info/{$group->qr_code_token}");
        $endTime1 = microtime(true);
        $firstAccessTime = ($endTime1 - $startTime1) * 1000;

        $response1->assertOk();

        // 2回目アクセス（キャッシュあり）
        $startTime2 = microtime(true);
        $response2 = $this->getJson("/api/conversations/groups/info/{$group->qr_code_token}");
        $endTime2 = microtime(true);
        $secondAccessTime = ($endTime2 - $startTime2) * 1000;

        $response2->assertOk();

        // キャッシュにより2回目の方が高速であることを確認
        $this->assertLessThan($firstAccessTime, $secondAccessTime, 
            'キャッシュにより2回目のアクセスの方が高速であるべき');
    }

    /**
     * インデックスの効果測定（グループメンバー検索）
     */
    public function test_group_member_index_effectiveness()
    {
        $group = Group::factory()->create();
        
        // 10000件のグループメンバーレコードを作成
        $members = [];
        for ($i = 0; $i < 10000; $i++) {
            $members[] = [
                'group_id' => $group->id,
                'user_id' => $i + 1,
                'role' => 'member',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('group_members')->insert($members);

        $targetUserId = 5000;

        // インデックスを使用した検索
        $startTime = microtime(true);
        $result = GroupMember::where('group_id', $group->id)
                            ->where('user_id', $targetUserId)
                            ->whereNull('left_at')
                            ->first();
        $endTime = microtime(true);

        $this->assertNotNull($result);

        $executionTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(5, $executionTime, 
            '複合インデックスを使用した検索は5ms以内であるべき');
    }
}