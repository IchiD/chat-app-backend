<?php

namespace Tests\Unit;

use App\Models\OperationLog;
use App\Services\OperationLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationLogServiceTest extends TestCase
{
  use RefreshDatabase;

  private function createLogs(string $category, int $count): void
  {
    for ($i = 1; $i <= $count; $i++) {
      OperationLogService::log($category, "action{$i}");
    }
  }

  private function seedLogs(string $category, int $count): void
  {
    OperationLog::factory()->count($count)->sequence(fn($sequence) => [
      'category' => $category,
      'action' => 'action' . ($sequence->index + 1),
      'created_at' => now()->subSeconds($count - $sequence->index),
    ])->create();
  }

  public function test_create_log_with_all_parameters(): void
  {
    OperationLogService::log('frontend', 'login', '管理画面ログイン');

    $this->assertDatabaseHas('operation_logs', [
      'category' => 'frontend',
      'action' => 'login',
      'description' => '管理画面ログイン',
    ]);
  }

  public function test_create_log_with_required_parameters_only(): void
  {
    OperationLogService::log('frontend', 'logout');

    $this->assertDatabaseHas('operation_logs', [
      'category' => 'frontend',
      'action' => 'logout',
      'description' => null,
    ]);
  }

  public function test_create_log_with_null_description(): void
  {
    OperationLogService::log('backend', 'deploy', null);

    $this->assertDatabaseHas('operation_logs', [
      'category' => 'backend',
      'action' => 'deploy',
      'description' => null,
    ]);
  }

  public function test_separate_log_management_by_category(): void
  {
    OperationLogService::log('frontend', 'a1');
    OperationLogService::log('backend', 'b1');
    OperationLogService::log('frontend', 'a2');

    $this->assertEquals(2, OperationLog::where('category', 'frontend')->count());
    $this->assertEquals(1, OperationLog::where('category', 'backend')->count());
  }

  public function test_trim_logs_keeps_exactly_3000_records(): void
  {
    $this->createLogs('frontend', 3100);

    $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
  }

  public function test_trim_logs_works_independently_per_category(): void
  {
    $this->createLogs('frontend', 3100);
    $this->createLogs('backend', 3100);

    $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
    $this->assertEquals(3000, OperationLog::where('category', 'backend')->count());
  }

  public function test_newest_logs_are_preserved_after_trimming(): void
  {
    $this->createLogs('frontend', 3001);

    $newestId = OperationLog::where('category', 'frontend')->max('id');
    $this->assertDatabaseHas('operation_logs', ['id' => $newestId]);
  }

  public function test_oldest_logs_are_deleted_first(): void
  {
    $this->seedLogs('frontend', 3000);
    $oldestId = OperationLog::where('category', 'frontend')->orderBy('created_at')->first()->id;
    OperationLogService::log('frontend', 'latest');

    $this->assertDatabaseMissing('operation_logs', ['id' => $oldestId]);
  }

  public function test_no_deletion_when_logs_under_3000_records(): void
  {
    $this->createLogs('frontend', 2999);

    $this->assertEquals(2999, OperationLog::where('category', 'frontend')->count());
  }

  public function test_no_deletion_when_exactly_3000_records(): void
  {
    $this->createLogs('frontend', 3000);

    $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
  }

  public function test_oldest_log_deleted_when_3001st_record_added(): void
  {
    $this->seedLogs('frontend', 3000);
    OperationLogService::log('frontend', 'new');

    $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
    $this->assertDatabaseMissing('operation_logs', ['action' => 'action1']);
  }

  public function test_create_first_log_from_empty_state(): void
  {
    OperationLogService::log('frontend', 'first');

    $this->assertEquals(1, OperationLog::where('category', 'frontend')->count());
  }

  public function test_bulk_logs_deletion_performance(): void
  {
    $this->seedLogs('frontend', 10000);

    $start = microtime(true);
    OperationLogService::log('frontend', 'bulk');
    $elapsed = microtime(true) - $start;

    $this->assertLessThan(1, $elapsed);
    $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
  }

  public function test_frontend_backend_independent_deletion(): void
  {
    $this->seedLogs('frontend', 2500);
    $this->seedLogs('backend', 3500);
    OperationLogService::log('frontend', 'a');
    OperationLogService::log('backend', 'b');

    $this->assertEquals(2501, OperationLog::where('category', 'frontend')->count());
    $this->assertEquals(3000, OperationLog::where('category', 'backend')->count());
  }

  public function test_deletion_accuracy_by_creation_date_order(): void
  {
    $this->seedLogs('frontend', 3050);
    $deletedId = OperationLog::where('category', 'frontend')->orderBy('created_at')->first()->id;
    $expectedOldestId = OperationLog::where('category', 'frontend')->orderBy('created_at')->skip(51)->first()->id;
    OperationLogService::log('frontend', 'latest');

    $this->assertDatabaseMissing('operation_logs', ['id' => $deletedId]);
    $this->assertDatabaseHas('operation_logs', ['id' => $expectedOldestId]);
  }

  public function test_execution_with_invalid_category_name(): void
  {
    OperationLogService::log('invalid', 'action');

    $this->assertEquals(1, OperationLog::where('category', 'invalid')->count());
  }
}
