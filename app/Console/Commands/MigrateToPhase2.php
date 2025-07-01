<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Conversation;

class MigrateToPhase2 extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'conversations:migrate-phase2 {--dry-run : Show what would be migrated without executing} {--step= : Execute specific step only}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Migrate to Phase 2 table structure';

  private $steps = [
    1 => 'Create new table structures',
    2 => 'Migrate group data',
    3 => 'Migrate chat room data',
    4 => 'Update participants data',
    5 => 'Update messages data',
    6 => 'Verify migration'
  ];

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $dryRun = $this->option('dry-run');
    $specificStep = $this->option('step');

    $this->info('🚀 Starting Phase 2 Migration');
    $this->newLine();

    if ($dryRun) {
      $this->warn('DRY RUN MODE - No changes will be made');
      $this->showDryRunSummary();
      return;
    }

    if (!$this->preFlightChecks()) {
      $this->error('Pre-flight checks failed. Aborting migration.');
      return;
    }

    if ($specificStep) {
      $this->executeStep((int)$specificStep);
    } else {
      $this->executeFullMigration();
    }
  }

  private function preFlightChecks(): bool
  {
    $this->info('🔍 Running pre-flight checks...');

    // 1. バックアップの確認
    if (!$this->confirm('Have you created a full database backup?')) {
      return false;
    }

    // 2. 新しいテーブルが存在するかチェック
    if (!Schema::hasTable('groups') || !Schema::hasTable('chat_rooms')) {
      $this->error('New tables do not exist. Please run migrations first.');
      return false;
    }

    // 3. データ整合性チェック
    $orphanedMembers = DB::table('conversations')
      ->where('type', 'group_member')
      ->whereNotIn('group_conversation_id', function ($query) {
        $query->select('id')->from('conversations')->where('type', 'group');
      })
      ->count();

    if ($orphanedMembers > 0) {
      $this->warn("Found {$orphanedMembers} orphaned member chats. Continue anyway?");
      if (!$this->confirm('Continue?')) {
        return false;
      }
    }

    $this->info('✅ Pre-flight checks passed');
    return true;
  }

  private function executeFullMigration()
  {
    $this->info('📋 Migration Steps:');
    foreach ($this->steps as $num => $description) {
      $this->line("  {$num}. {$description}");
    }
    $this->newLine();

    if (!$this->confirm('Proceed with full migration?')) {
      $this->info('Migration cancelled.');
      return;
    }

    foreach ($this->steps as $step => $description) {
      $this->executeStep($step);
    }

    $this->info('🎉 Phase 2 migration completed successfully!');
  }

  private function executeStep(int $step)
  {
    if (!isset($this->steps[$step])) {
      $this->error("Invalid step: {$step}");
      return;
    }

    $this->info("Step {$step}: {$this->steps[$step]}");

    try {
      DB::beginTransaction();

      switch ($step) {
        case 1:
          $this->createTableStructures();
          break;
        case 2:
          $this->migrateGroupData();
          break;
        case 3:
          $this->migrateChatRoomData();
          break;
        case 4:
          $this->updateParticipantsData();
          break;
        case 5:
          $this->updateMessagesData();
          break;
        case 6:
          $this->verifyMigration();
          break;
      }

      DB::commit();
      $this->info("✅ Step {$step} completed");
    } catch (\Exception $e) {
      DB::rollBack();
      $this->error("❌ Step {$step} failed: " . $e->getMessage());
      throw $e;
    }
  }

  private function createTableStructures()
  {
    // マイグレーションで既に作成済みの前提
    $this->line('Table structures already created via migrations');
  }

  private function migrateGroupData()
  {
    $this->line('Migrating group data...');

    $groups = DB::table('conversations')
      ->where('type', 'group')
      ->get();

    foreach ($groups as $group) {
      DB::table('groups')->insert([
        'id' => $group->id,
        'name' => $group->name,
        'description' => $group->description,
        'max_members' => $group->max_members ?? 50,
        'chat_styles' => $group->chat_styles ?? '["group"]',
        'owner_user_id' => $group->owner_user_id,
        'qr_code_token' => $group->qr_code_token,
        'created_at' => $group->created_at,
        'updated_at' => $group->updated_at,
      ]);
    }

    $this->line("Migrated {$groups->count()} groups");
  }

  private function migrateChatRoomData()
  {
    $this->line('Migrating chat room data...');

    // Group chat rooms
    $groupConversations = DB::table('conversations')
      ->where('type', 'group')
      ->get();

    foreach ($groupConversations as $conv) {
      DB::table('chat_rooms')->insert([
        'type' => 'group_chat',
        'group_id' => $conv->id,
        'participant1_id' => null,
        'participant2_id' => null,
        'room_token' => $conv->room_token,
        'created_at' => $conv->created_at,
        'updated_at' => $conv->updated_at,
      ]);
    }

    // Member chat rooms
    $memberConversations = DB::table('conversations')
      ->where('type', 'group_member')
      ->get();

    foreach ($memberConversations as $conv) {
      // 参加者を取得
      $participants = DB::table('participants')
        ->where('conversation_id', $conv->id)
        ->orderBy('user_id')
        ->pluck('user_id')
        ->toArray();

      DB::table('chat_rooms')->insert([
        'type' => 'member_chat',
        'group_id' => $conv->group_conversation_id,
        'participant1_id' => $participants[0] ?? null,
        'participant2_id' => $participants[1] ?? null,
        'room_token' => $conv->room_token,
        'created_at' => $conv->created_at,
        'updated_at' => $conv->updated_at,
      ]);
    }

    $this->line("Migrated " . ($groupConversations->count() + $memberConversations->count()) . " chat rooms");
  }

  private function updateParticipantsData()
  {
    $this->line('Updating participants data...');

    $participants = DB::table('participants')->get();

    foreach ($participants as $participant) {
      $chatRoom = DB::table('chat_rooms')
        ->join('conversations', function ($join) {
          $join->on('chat_rooms.room_token', '=', 'conversations.room_token');
        })
        ->where('conversations.id', $participant->conversation_id)
        ->select('chat_rooms.id')
        ->first();

      if ($chatRoom) {
        DB::table('participants')
          ->where('id', $participant->id)
          ->update(['chat_room_id' => $chatRoom->id]);
      }
    }

    $this->line("Updated {$participants->count()} participant records");
  }

  private function updateMessagesData()
  {
    $this->line('Updating messages data...');

    $messages = DB::table('messages')->get();

    foreach ($messages as $message) {
      $chatRoom = DB::table('chat_rooms')
        ->join('conversations', function ($join) {
          $join->on('chat_rooms.room_token', '=', 'conversations.room_token');
        })
        ->where('conversations.id', $message->conversation_id)
        ->select('chat_rooms.id')
        ->first();

      if ($chatRoom) {
        DB::table('messages')
          ->where('id', $message->id)
          ->update(['chat_room_id' => $chatRoom->id]);
      }
    }

    $this->line("Updated {$messages->count()} message records");
  }

  private function verifyMigration()
  {
    $this->line('Verifying migration...');

    $groupsCount = DB::table('groups')->count();
    $chatRoomsCount = DB::table('chat_rooms')->count();
    $participantsWithChatRoom = DB::table('participants')->whereNotNull('chat_room_id')->count();
    $messagesWithChatRoom = DB::table('messages')->whereNotNull('chat_room_id')->count();

    $this->table(['Table', 'Count'], [
      ['Groups', $groupsCount],
      ['Chat Rooms', $chatRoomsCount],
      ['Participants with chat_room_id', $participantsWithChatRoom],
      ['Messages with chat_room_id', $messagesWithChatRoom],
    ]);

    // 整合性チェック
    $issues = [];

    if ($participantsWithChatRoom < DB::table('participants')->count()) {
      $issues[] = 'Some participants missing chat_room_id';
    }

    if ($messagesWithChatRoom < DB::table('messages')->count()) {
      $issues[] = 'Some messages missing chat_room_id';
    }

    if (empty($issues)) {
      $this->info('✅ Migration verification passed');
    } else {
      $this->warn('⚠️  Migration issues found:');
      foreach ($issues as $issue) {
        $this->line("  - {$issue}");
      }
    }
  }

  private function showDryRunSummary()
  {
    $groupCount = DB::table('conversations')->where('type', 'group')->count();
    $memberChatCount = DB::table('conversations')->where('type', 'group_member')->count();
    $participantCount = DB::table('participants')->count();
    $messageCount = DB::table('messages')->count();

    $this->info('📊 Migration Summary (Dry Run):');
    $this->table(['Action', 'Count'], [
      ['Groups to migrate', $groupCount],
      ['Group chat rooms to create', $groupCount],
      ['Member chat rooms to create', $memberChatCount],
      ['Participant records to update', $participantCount],
      ['Message records to update', $messageCount],
    ]);
  }
}
