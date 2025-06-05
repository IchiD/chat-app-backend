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
        OperationLog::factory()->count($count)->sequence(fn ($sequence) => [
            'category' => $category,
            'action' => 'action' . ($sequence->index + 1),
            'created_at' => now()->subSeconds($count - $sequence->index),
        ])->create();
    }

    public function test_ログ作成_正常ケース(): void
    {
        OperationLogService::log('frontend', 'login', '管理画面ログイン');

        $this->assertDatabaseHas('operation_logs', [
            'category' => 'frontend',
            'action' => 'login',
            'description' => '管理画面ログイン',
        ]);
    }

    public function test_ログ作成_必須項目のみ(): void
    {
        OperationLogService::log('frontend', 'logout');

        $this->assertDatabaseHas('operation_logs', [
            'category' => 'frontend',
            'action' => 'logout',
            'description' => null,
        ]);
    }

    public function test_ログ作成_説明なし(): void
    {
        OperationLogService::log('backend', 'deploy', null);

        $this->assertDatabaseHas('operation_logs', [
            'category' => 'backend',
            'action' => 'deploy',
            'description' => null,
        ]);
    }

    public function test_カテゴリ別ログ管理(): void
    {
        OperationLogService::log('frontend', 'a1');
        OperationLogService::log('backend', 'b1');
        OperationLogService::log('frontend', 'a2');

        $this->assertEquals(2, OperationLog::where('category', 'frontend')->count());
        $this->assertEquals(1, OperationLog::where('category', 'backend')->count());
    }

    public function test_古いログ削除_正確に3000件保持(): void
    {
        $this->createLogs('frontend', 3100);

        $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
    }

    public function test_古いログ削除_カテゴリ別独立削除(): void
    {
        $this->createLogs('frontend', 3100);
        $this->createLogs('backend', 3100);

        $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
        $this->assertEquals(3000, OperationLog::where('category', 'backend')->count());
    }

    public function test_古いログ削除_新しいログは保持される(): void
    {
        $this->createLogs('frontend', 3001);

        $newestId = OperationLog::where('category', 'frontend')->max('id');
        $this->assertDatabaseHas('operation_logs', ['id' => $newestId]);
    }

    public function test_古いログ削除_最古のログから削除される(): void
    {
        $this->seedLogs('frontend', 3000);
        $oldestId = OperationLog::where('category', 'frontend')->orderBy('created_at')->first()->id;
        OperationLogService::log('frontend', 'latest');

        $this->assertDatabaseMissing('operation_logs', ['id' => $oldestId]);
    }

    public function test_ログ件数が3000件未満の場合削除されない(): void
    {
        $this->createLogs('frontend', 2999);

        $this->assertEquals(2999, OperationLog::where('category', 'frontend')->count());
    }

    public function test_ちょうど3000件の場合(): void
    {
        $this->createLogs('frontend', 3000);

        $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
    }

    public function test_3001件目で最古が削除される(): void
    {
        $this->seedLogs('frontend', 3000);
        OperationLogService::log('frontend', 'new');

        $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
        $this->assertDatabaseMissing('operation_logs', ['action' => 'action1']);
    }

    public function test_0件から1件目の作成(): void
    {
        OperationLogService::log('frontend', 'first');

        $this->assertEquals(1, OperationLog::where('category', 'frontend')->count());
    }

    public function test_大量ログでの削除性能(): void
    {
        $this->seedLogs('frontend', 10000);

        $start = microtime(true);
        OperationLogService::log('frontend', 'bulk');
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(1, $elapsed);
        $this->assertEquals(3000, OperationLog::where('category', 'frontend')->count());
    }

    public function test_frontend_backend_独立削除(): void
    {
        $this->seedLogs('frontend', 2500);
        $this->seedLogs('backend', 3500);
        OperationLogService::log('frontend', 'a');
        OperationLogService::log('backend', 'b');

        $this->assertEquals(2501, OperationLog::where('category', 'frontend')->count());
        $this->assertEquals(3000, OperationLog::where('category', 'backend')->count());
    }

    public function test_削除対象の正確性_作成日時順(): void
    {
        $this->seedLogs('frontend', 3050);
        $deletedId = OperationLog::where('category', 'frontend')->orderBy('created_at')->first()->id;
        $expectedOldestId = OperationLog::where('category', 'frontend')->orderBy('created_at')->skip(51)->first()->id;
        OperationLogService::log('frontend', 'latest');

        $this->assertDatabaseMissing('operation_logs', ['id' => $deletedId]);
        $this->assertDatabaseHas('operation_logs', ['id' => $expectedOldestId]);
    }

    public function test_不正なカテゴリ名での実行(): void
    {
        OperationLogService::log('invalid', 'action');

        $this->assertEquals(1, OperationLog::where('category', 'invalid')->count());
    }
}
