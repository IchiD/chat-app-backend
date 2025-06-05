<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationManagementTest extends TestCase
{
  use RefreshDatabase;

  private function createTestConversations()
  {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Normal conversation
    $conv1 = Conversation::factory()->create(['type' => 'direct']);
    Participant::create(['conversation_id' => $conv1->id, 'user_id' => $userA->id]);
    Participant::create(['conversation_id' => $conv1->id, 'user_id' => $userB->id]);
    Message::factory()->create([
      'conversation_id' => $conv1->id,
      'sender_id' => $userA->id,
      'text_content' => 'hello',
    ]);

    // Support conversation (excluded)
    $conv2 = Conversation::factory()->create(['type' => 'support']);
    Participant::create(['conversation_id' => $conv2->id, 'user_id' => $userA->id]);

    // Deleted conversation
    $conv3 = Conversation::factory()->create(['type' => 'direct']);
    Participant::create(['conversation_id' => $conv3->id, 'user_id' => $userA->id]);
    $conv3->deleteByAdmin(1, 'テスト削除');

    return [$conv1, $conv2, $conv3];
  }

  public function test_admin_can_access_conversations_list()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');
    $this->createTestConversations();

    $response = $this->get('/admin/conversations');

    $response->assertOk();
  }

  // 以下のテストは詳細な実装が必要ですが、ここでは未完了としてマークします。

  public function test_unauthenticated_user_cannot_access_conversations_list()
  {
    $response = $this->get('/admin/conversations');
    $response->assertRedirect('/admin/login');
  }

  public function test_non_admin_user_cannot_access_conversations_list()
  {
    $user = User::factory()->create();
    $this->actingAs($user); // auth as normal user
    $response = $this->get('/admin/conversations');
    $response->assertRedirect('/admin/login');
  }

  public function test_super_admin_can_access_conversations_list()
  {
    $admin = Admin::factory()->create(['role' => 'super_admin']);
    $this->actingAs($admin, 'admin');
    $response = $this->get('/admin/conversations');
    $response->assertOk();
  }

  public function test_conversations_list_shows_all_conversations_except_support()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');
    [$conv1, $conv2, $conv3] = $this->createTestConversations();

    $response = $this->get('/admin/conversations');

    // より具体的なアサーション
    $response->assertSee('#' . $conv1->id)  // direct会話は表示される
      ->assertDontSee('#' . $conv2->id)   // support会話は表示されない
      ->assertSee('#' . $conv3->id);      // 削除済み会話も表示される
  }

  // 以下のテストケースは今後実装予定
  public function test_conversations_list_excludes_support_type_conversations()
  {
    $this->markTestIncomplete();
  }
  public function test_conversations_list_shows_deleted_conversations()
  {
    $this->markTestIncomplete();
  }
  public function test_conversations_list_pagination_works_correctly()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    Conversation::factory()->count(21)->create(['type' => 'direct']);

    $response = $this->get('/admin/conversations');
    $response->assertOk()
      ->assertViewHas('conversations', function ($conversations) {
        return $conversations->count() === 20;
      });

    $response = $this->get('/admin/conversations?page=2');
    $response->assertOk()
      ->assertViewHas('conversations', function ($conversations) {
        return $conversations->count() === 1;
      });
  }
  public function test_conversations_list_shows_correct_message_count_using_withCount()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $conversation = Conversation::factory()->create(['type' => 'direct']);
    $user = User::factory()->create();
    Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    Message::factory()->count(3)->create([
      'conversation_id' => $conversation->id,
      'sender_id' => $user->id,
    ]);

    $response = $this->get('/admin/conversations');
    $response->assertOk()
      ->assertViewHas('conversations', function ($conversations) use ($conversation) {
        $conv = $conversations->firstWhere('id', $conversation->id);
        return $conv && isset($conv->messages_count) && $conv->messages_count === 3;
      });
  }

  public function test_search_by_conversation_id_exact_match()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $conversation1 = Conversation::factory()->create(['type' => 'direct']);
    $conversation2 = Conversation::factory()->create(['type' => 'direct']);

    $response = $this->get('/admin/conversations?search=' . $conversation1->id);

    $response->assertOk()
      ->assertSee('#' . $conversation1->id)      // 検索された会話は表示
      ->assertDontSee('#' . $conversation2->id); // 他の会話は表示されない
  }
  public function test_search_by_message_content_partial_match()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $conversation = Conversation::factory()->create(['type' => 'direct']);
    $user = User::factory()->create();
    Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
    Message::factory()->create([
      'conversation_id' => $conversation->id,
      'sender_id' => $user->id,
      'text_content' => 'これは検索対象のメッセージです',
    ]);
    Message::factory()->create([
      'conversation_id' => $conversation->id,
      'sender_id' => $user->id,
      'text_content' => '検索対象外の削除メッセージ',
      'deleted_at' => now(),
    ]);

    $response = $this->get('/admin/conversations?search=' . urlencode('検索対象'));

    $response->assertOk()
      ->assertSee((string)$conversation->id)
      ->assertSee('これは検索対象のメッセージです');
  }
  public function test_search_by_user_name_partial_match()
  {
    $this->markTestIncomplete();
  }
  public function test_search_excludes_deleted_messages()
  {
    $this->markTestIncomplete();
  }
  public function test_search_excludes_admin_deleted_messages()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $conversation = Conversation::factory()->create(['type' => 'direct']);
    $user = User::factory()->create();
    Participant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

    // 通常のメッセージ
    Message::factory()->create([
      'conversation_id' => $conversation->id,
      'sender_id' => $user->id,
      'text_content' => '通常のメッセージ',
    ]);

    // 管理者削除されたメッセージ
    Message::factory()->create([
      'conversation_id' => $conversation->id,
      'sender_id' => $user->id,
      'text_content' => '管理者削除されたメッセージ',
      'admin_deleted_at' => now(),
      'admin_deleted_by' => $admin->id,
    ]);

    // 削除されたメッセージ内容で検索しても結果に表示されないことを確認
    $response = $this->get('/admin/conversations?search=' . urlencode('管理者削除されたメッセージ'));
    $response->assertOk()->assertDontSee('#' . $conversation->id);

    // 通常メッセージ内容で検索すると結果に表示されることを確認
    $response = $this->get('/admin/conversations?search=' . urlencode('通常のメッセージ'));
    $response->assertOk()->assertSee('#' . $conversation->id);
  }
  public function test_search_returns_empty_when_no_match()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    Conversation::factory()->create(['type' => 'direct']);

    $response = $this->get('/admin/conversations?search=' . urlencode('存在しないキーワード'));

    $response->assertOk()->assertSee('トークルームがありません');
  }
  public function test_search_maintains_pagination_parameters()
  {
    $this->markTestIncomplete();
  }

  public function test_conversation_detail_shows_all_information()
  {
    $this->markTestIncomplete();
  }
  public function test_conversation_detail_shows_deleted_conversation_info()
  {
    $this->markTestIncomplete();
  }
  public function test_conversation_detail_shows_participants_correctly()
  {
    $this->markTestIncomplete();
  }
  public function test_conversation_detail_shows_messages_with_pagination()
  {
    $this->markTestIncomplete();
  }
  public function test_conversation_detail_handles_null_sender_gracefully()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');
    $conversation = Conversation::factory()->create(['type' => 'direct']);
    Participant::create(['conversation_id' => $conversation->id, 'user_id' => User::factory()->create()->id]);

    // メッセージの送信者が null (管理者メッセージ)
    Message::factory()->adminMessage($admin->id)->create([
      'conversation_id' => $conversation->id,
      'text_content' => 'from admin',
    ]);

    $response = $this->get('/admin/conversations/' . $conversation->id);
    $response->assertOk();
  }
  public function test_non_existent_conversation_returns_404()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $response = $this->get('/admin/conversations/999999');
    $response->assertNotFound();
  }

  public function test_admin_can_delete_active_conversation()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');
    $conversation = Conversation::factory()->create(['type' => 'direct']);

    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    $response = $this->delete('/admin/conversations/' . $conversation->id, [
      'reason' => 'テスト削除理由',
    ]);

    $response->assertRedirect('/admin/conversations');
    $this->assertDatabaseHas('conversations', [
      'id' => $conversation->id,
      'deleted_by' => $admin->id,
      'deleted_reason' => 'テスト削除理由',
    ]);
  }

  public function test_delete_logs_operation_correctly()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');
    $conversation = Conversation::factory()->create(['type' => 'direct']);

    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    $this->delete('/admin/conversations/' . $conversation->id);

    $this->assertDatabaseHas('operation_logs', [
      'category' => 'backend',
      'action' => 'delete_conversation_admin',
      'description' => 'admin:' . $admin->id . ' conversation:' . $conversation->id,
    ]);
  }

  public function test_admin_can_restore_deleted_conversation()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $conversation = Conversation::factory()->deleted()->create(['type' => 'direct']);
    $this->assertNotNull($conversation->deleted_at);

    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    $response = $this->post('/admin/conversations/' . $conversation->id . '/restore');

    $response->assertRedirect('/admin/conversations');
    $conversation->refresh();
    $this->assertNull($conversation->deleted_at);
    $this->assertNull($conversation->deleted_by);
    $this->assertNull($conversation->deleted_reason);
  }
  public function test_cannot_restore_active_conversation()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $conversation = Conversation::factory()->create(['type' => 'direct']);

    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    $response = $this->post('/admin/conversations/' . $conversation->id . '/restore');

    $response->assertRedirect()
      ->assertSessionHas('error', 'この会話は削除されていません。');
    $conversation->refresh();
    $this->assertNull($conversation->deleted_at);
  }

  public function test_restore_logs_operation_correctly()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $conversation = Conversation::factory()->deleted()->create(['type' => 'direct']);

    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    $this->post('/admin/conversations/' . $conversation->id . '/restore');

    $this->assertDatabaseHas('operation_logs', [
      'category' => 'backend',
      'action' => 'restore_conversation_admin',
      'description' => 'admin:' . $admin->id . ' conversation:' . $conversation->id,
    ]);
  }

  public function test_restore_clears_deletion_fields()
  {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $conversation = Conversation::factory()->deleted()->create(['type' => 'direct']);

    $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    $this->post('/admin/conversations/' . $conversation->id . '/restore');

    $conversation->refresh();
    $this->assertNull($conversation->deleted_at);
    $this->assertNull($conversation->deleted_by);
    $this->assertNull($conversation->deleted_reason);
  }
}
