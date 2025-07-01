<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Conversation;

class AnalyzeDataForPhase2 extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'conversations:analyze-phase2';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Analyze current data structure for Phase 2 table splitting';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $this->info('🔍 Analyzing current data structure for Phase 2 migration');
    $this->newLine();

    // 1. 基本統計
    $this->showBasicStats();

    // 2. グループタイプの分析
    $this->analyzeGroupTypes();

    // 3. リレーションの分析
    $this->analyzeRelations();

    // 4. 移行計画の提案
    $this->proposeMigrationPlan();

    $this->newLine();
    $this->info('✅ Analysis completed');
  }

  private function showBasicStats()
  {
    $this->info('📊 Basic Statistics:');

    $stats = DB::table('conversations')
      ->select([
        'type',
        DB::raw('COUNT(*) as count'),
        DB::raw('MIN(created_at) as first_created'),
        DB::raw('MAX(created_at) as last_created')
      ])
      ->groupBy('type')
      ->get();

    $headers = ['Type', 'Count', 'First Created', 'Last Created'];
    $rows = $stats->map(function ($stat) {
      return [
        $stat->type,
        $stat->count,
        $stat->first_created,
        $stat->last_created
      ];
    })->toArray();

    $this->table($headers, $rows);
    $this->newLine();
  }

  private function analyzeGroupTypes()
  {
    $this->info('🏗️ Group Type Analysis:');

    // グループタイプの詳細分析
    $groupData = DB::table('conversations')
      ->where('type', 'group')
      ->select([
        'id',
        'name',
        'chat_styles',
        'owner_user_id',
        'max_members',
        DB::raw('(SELECT COUNT(*) FROM participants WHERE conversation_id = conversations.id) as member_count')
      ])
      ->get();

    if ($groupData->count() > 0) {
      $headers = ['ID', 'Name', 'Chat Styles', 'Owner', 'Max Members', 'Current Members'];
      $rows = $groupData->map(function ($group) {
        return [
          $group->id,
          $group->name,
          $group->chat_styles,
          $group->owner_user_id,
          $group->max_members,
          $group->member_count
        ];
      })->toArray();

      $this->table($headers, $rows);
    } else {
      $this->line('No group type records found.');
    }

    $this->newLine();
  }

  private function analyzeRelations()
  {
    $this->info('🔗 Relationship Analysis:');

    // group_memberタイプのリレーション分析
    $memberChats = DB::table('conversations')
      ->where('type', 'group_member')
      ->select([
        'id',
        'name',
        'group_conversation_id',
        DB::raw('(SELECT COUNT(*) FROM participants WHERE conversation_id = conversations.id) as participant_count')
      ])
      ->get();

    if ($memberChats->count() > 0) {
      $headers = ['Member Chat ID', 'Name', 'Parent Group ID', 'Participants'];
      $rows = $memberChats->map(function ($chat) {
        return [
          $chat->id,
          $chat->name,
          $chat->group_conversation_id,
          $chat->participant_count
        ];
      })->toArray();

      $this->table($headers, $rows);
    } else {
      $this->line('No group_member type records found.');
    }

    // Participants統計
    $participantStats = DB::table('participants')
      ->join('conversations', 'participants.conversation_id', '=', 'conversations.id')
      ->select([
        'conversations.type',
        DB::raw('COUNT(*) as total_participants'),
        DB::raw('COUNT(DISTINCT participants.user_id) as unique_users')
      ])
      ->groupBy('conversations.type')
      ->get();

    $this->newLine();
    $this->info('👥 Participant Statistics:');
    $headers = ['Conversation Type', 'Total Participants', 'Unique Users'];
    $rows = $participantStats->map(function ($stat) {
      return [
        $stat->type,
        $stat->total_participants,
        $stat->unique_users
      ];
    })->toArray();

    $this->table($headers, $rows);
    $this->newLine();
  }

  private function proposeMigrationPlan()
  {
    $this->info('📋 Proposed Migration Plan:');

    $groupCount = DB::table('conversations')->where('type', 'group')->count();
    $memberChatCount = DB::table('conversations')->where('type', 'group_member')->count();
    $totalParticipants = DB::table('participants')->count();
    $totalMessages = DB::table('messages')->count();

    $this->line("1. Create 'groups' table: {$groupCount} records to migrate");
    $this->line("2. Create 'chat_rooms' table: " . ($groupCount + $memberChatCount) . " records to migrate");
    $this->line("3. Update 'participants' table: {$totalParticipants} records to update");
    $this->line("4. Update 'messages' table: {$totalMessages} records to update");

    $this->newLine();
    $this->warn('⚠️  This is a major structural change. Please ensure:');
    $this->line('- Full database backup is available');
    $this->line('- Application is in maintenance mode');
    $this->line('- Sufficient time for migration execution');
    $this->line('- Rollback plan is prepared');
  }
}
