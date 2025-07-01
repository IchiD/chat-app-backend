<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\ComprehensiveSeeder;

class SeedComprehensiveData extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'seed:comprehensive {--fresh : データベースをリフレッシュしてからseederを実行}';

  /**
   * The console description of the console command.
   *
   * @var string
   */
  protected $description = '包括的なテストデータ（ユーザー、友達関係、チャット、メッセージ）を作成します';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    if ($this->option('fresh')) {
      $this->info('データベースをリフレッシュ中...');
      $this->call('migrate:fresh');
    }

    $this->info('包括的なテストデータの作成を開始します...');
    $this->newLine();

    // ComprehensiveSeederを実行
    $this->call('db:seed', ['--class' => ComprehensiveSeeder::class]);

    $this->newLine();
    $this->info('✅ 包括的なテストデータの作成が完了しました！');

    $this->displayUsageInstructions();

    return Command::SUCCESS;
  }

  /**
   * 使用方法の説明を表示
   */
  private function displayUsageInstructions(): void
  {
    $this->newLine();
    $this->line('🔧 使用方法:');
    $this->line('────────────────────────────────');
    $this->line('• 基本実行: php artisan seed:comprehensive');
    $this->line('• DBリフレッシュ付き: php artisan seed:comprehensive --fresh');
    $this->line('• 個別seeder実行: php artisan db:seed --class=UserSeeder');
    $this->newLine();

    $this->line('📝 作成されるテストデータ:');
    $this->line('────────────────────────────────');
    $this->line('• 25人の多様な状態のユーザー（アクティブ、未確認、バン済み等）');
    $this->line('• 約18組の友達関係（承認済み、申請中、拒否）');
    $this->line('• メンバーチャット、フレンドチャット、グループチャット、サポートチャット');
    $this->line('• 各チャットに5-30件のリアルなメッセージ');
    $this->line('• 管理者からのシステムメッセージ（30%確率）');
    $this->newLine();

    $this->line('🎯 テスト用アカウント:');
    $this->line('────────────────────────────────');
    $this->line('• active@example.com (田中太郎)');
    $this->line('• newuser@example.com (佐藤花子)');
    $this->line('• banned@example.com (鈴木一郎)');
    $this->line('• パスワード: password');
  }
}
