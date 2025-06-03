<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class ProcessQueueJobs extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'queue:process-jobs';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'キューに溜まったジョブを処理します';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    Log::info('キュー処理コマンドを開始しました');

    try {
      // キューに溜まったジョブを一定数処理
      $exitCode = Artisan::call('queue:work', [
        '--once' => true,
        '--tries' => 3,
        '--timeout' => 60,
        '--memory' => 256,
        '--queue' => 'default'
      ]);

      if ($exitCode === 0) {
        $this->info('✅ キュー処理が正常に完了しました');
        Log::info('キュー処理が正常に完了しました');
      } else {
        $this->warn('⚠️ キュー処理で問題が発生しました (Exit code: ' . $exitCode . ')');
        Log::warning('キュー処理で問題が発生しました', ['exit_code' => $exitCode]);
      }
    } catch (\Exception $e) {
      $this->error('❌ キュー処理でエラーが発生しました: ' . $e->getMessage());
      Log::error('キュー処理でエラーが発生しました', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
    }

    return 0;
  }
}
