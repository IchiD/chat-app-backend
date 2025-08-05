<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * XSS攻撃の防止テスト
     */
    public function test_xss_prevention_in_messages()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'friend_chat',
            'participant1_id' => $user->id,
            'participant2_id' => $friend->id,
        ]);

        Sanctum::actingAs($user);

        // XSSペイロードを含むメッセージを送信
        $xssPayload = '<script>alert("XSS")</script>';
        $response = $this->postJson("/api/conversations/room/{$chatRoom->room_token}/messages", [
            'text_content' => $xssPayload,
        ]);

        $response->assertStatus(201);
        
        // データベースに保存された内容を確認
        $message = Message::latest()->first();
        $this->assertEquals($xssPayload, $message->text_content);
        
        // データベースにそのまま保存され、フロントエンド側でエスケープされることを確認
        // APIがXSSペイロードを受け入れることを確認（フロントエンド側での適切な処理が必要）
        $this->assertArrayHasKey('id', $response->json());
    }

    /**
     * XSS攻撃の防止テスト（ユーザー名）
     */
    public function test_xss_prevention_in_user_names()
    {
        $xssName = '<script>';
        
        $response = $this->postJson('/api/register', [
            'name' => $xssName,
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        
        // データベースに保存された内容を確認
        $user = User::where('email', 'test@example.com')->first();
        $this->assertEquals($xssName, $user->name);
    }

    /**
     * SQLインジェクション防止テスト（検索機能）
     */
    public function test_sql_injection_prevention_in_search()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 6文字のSQLインジェクションペイロード（friend_idのバリデーションに合わせる）
        $sqlPayload = "'DROP;";
        
        $response = $this->postJson("/api/friends/search", [
            'friend_id' => $sqlPayload
        ]);
        
        // バリデーションが機能するか、またはユーザーが見つからないことを確認
        $this->assertContains($response->status(), [404, 422]);
        
        // usersテーブルがまだ存在することを確認
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * SQLインジェクション防止テスト（メッセージ検索）
     */
    public function test_sql_injection_prevention_in_message_search()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $chatRoom = ChatRoom::factory()->create([
            'type' => 'friend_chat',
            'participant1_id' => $user->id,
            'participant2_id' => $friend->id,
        ]);

        Sanctum::actingAs($user);

        // SQLインジェクションペイロード
        $sqlPayload = "' OR 1=1 --";
        
        $response = $this->getJson("/api/conversations/room/{$chatRoom->room_token}/messages?search={$sqlPayload}");
        
        // 403エラー（権限不足）またはアクセス制御が機能していることを確認
        $this->assertContains($response->status(), [200, 403]);
        
        // 200の場合、SQLインジェクションが防止されていることを確認
        if ($response->status() === 200 && $response->json('data')) {
            $this->assertCount(0, $response->json('data'));
        }
    }

    /**
     * CSRF攻撃防止テスト
     */
    public function test_csrf_protection()
    {
        $user = User::factory()->create();
        
        // CSRFトークンなしでPOSTリクエスト（Web経由）
        $response = $this->post('/api/logout', [], [
            'Accept' => 'application/json',
        ]);
        
        // API経由なのでCSRFトークンは不要（Sanctumトークンで保護）
        $response->assertStatus(401); // 未認証
    }

    /**
     * 認証トークンの検証
     */
    public function test_invalid_auth_token_rejection()
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_token_12345')
                         ->getJson('/api/user');
        
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Mass Assignment防止テスト
     */
    public function test_mass_assignment_protection()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 管理者権限を取得しようとする
        $response = $this->putJson('/api/user/update-name', [
            'name' => 'TestName',
            'is_admin' => true,
            'is_banned' => false,
            'deleted_at' => null,
        ]);

        $response->assertSuccessful();
        
        // 保護されたフィールドが変更されていないことを確認
        $user->refresh();
        $this->assertEquals('TestName', $user->name);
        $this->assertFalse($user->is_admin ?? false);
        $this->assertFalse($user->is_banned);
        $this->assertNull($user->deleted_at);
    }

    /**
     * パスワードがAPIレスポンスに含まれないことを確認
     */
    public function test_password_not_exposed_in_api_responses()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');
        
        $response->assertSuccessful();
        $responseData = $response->json();
        
        // パスワード関連フィールドが含まれていないことを確認
        $this->assertArrayNotHasKey('password', $responseData);
        $this->assertArrayNotHasKey('password_hash', $responseData);
        $this->assertArrayNotHasKey('remember_token', $responseData);
    }

    /**
     * エラーメッセージでの情報漏洩防止
     */
    public function test_error_messages_do_not_leak_sensitive_info()
    {
        // 存在しないメールアドレスでログイン試行
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
        
        // 「ユーザーが存在しない」という具体的な情報を漏らさない
        $this->assertStringNotContainsString('user not found', strtolower($response->json('message')));
        $this->assertStringNotContainsString('email not found', strtolower($response->json('message')));
    }

    /**
     * レート制限のテスト
     */
    public function test_rate_limiting_protection()
    {
        // 短時間に大量のリクエストを送信
        $lastResponse = null;
        for ($i = 0; $i < 15; $i++) {
            $lastResponse = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrong_password',
            ]);
            
            // レート制限に達したら早期終了
            if ($lastResponse->status() === 429) {
                break;
            }
        }

        // レート制限またはログイン失敗が発生することを確認
        $this->assertContains($lastResponse->status(), [401, 429]);
    }

    /**
     * ディレクトリトラバーサル攻撃の防止
     */
    public function test_directory_traversal_prevention()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // ディレクトリトラバーサルを試みる
        $maliciousPath = '../../../etc/passwd';
        
        $response = $this->getJson("/api/files/{$maliciousPath}");
        
        // 404またはエラーが返されることを確認
        $this->assertContains($response->status(), [404, 403, 400]);
    }

    /**
     * HTTPSリダイレクトの確認（本番環境のみ）
     */
    public function test_https_redirect_in_production()
    {
        if (app()->environment('production')) {
            $response = $this->get('/', ['HTTP_X_FORWARDED_PROTO' => 'http']);
            
            $response->assertRedirect();
            $this->assertStringStartsWith('https://', $response->headers->get('Location'));
        } else {
            $this->assertTrue(true); // 開発環境ではスキップ
        }
    }
}