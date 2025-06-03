<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DebugEmail extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'email:debug';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'メール設定をデバッグします';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $this->info('=== メール設定デバッグ情報 ===');

    // 基本設定
    $this->info('📧 基本設定:');
    $this->line("Driver: " . config('mail.default'));
    $this->line("Host: " . config('mail.mailers.smtp.host'));
    $this->line("Port: " . config('mail.mailers.smtp.port'));
    $this->line("Username: " . config('mail.mailers.smtp.username'));
    $this->line("Encryption: " . config('mail.mailers.smtp.encryption'));
    $this->line("From Address: " . config('mail.from.address'));
    $this->line("From Name: " . config('mail.from.name'));

    // 環境変数の確認
    $this->info('');
    $this->info('🔧 環境変数:');
    $this->line("MAIL_MAILER: " . env('MAIL_MAILER', 'not set'));
    $this->line("MAIL_HOST: " . env('MAIL_HOST', 'not set'));
    $this->line("MAIL_PORT: " . env('MAIL_PORT', 'not set'));
    $this->line("MAIL_USERNAME: " . (env('MAIL_USERNAME') ? 'SET' : 'NOT SET'));
    $this->line("MAIL_PASSWORD: " . (env('MAIL_PASSWORD') ? 'SET' : 'NOT SET'));
    $this->line("MAIL_ENCRYPTION: " . env('MAIL_ENCRYPTION', 'not set'));
    $this->line("MAIL_FROM_ADDRESS: " . env('MAIL_FROM_ADDRESS', 'not set'));

    // SMTP接続テスト
    $this->info('');
    $this->info('🔌 SMTP接続テスト:');

    if (config('mail.default') === 'smtp') {
      try {
        $transport = Mail::getSymfonyTransport();
        $this->info("✅ SMTP Transport作成成功");

        // 実際の接続テスト
        if (method_exists($transport, 'start')) {
          $transport->start();
          $this->info("✅ SMTP接続成功");
        } else {
          $this->info("ℹ️ SMTP接続テストは利用できません (このTransportタイプでは)");
        }
      } catch (\Exception $e) {
        $this->error("❌ SMTP接続エラー: " . $e->getMessage());
      }
    } else {
      $this->warn("⚠️ 現在のドライバーはSMTPではありません: " . config('mail.default'));
    }

    // Railwayかローカルかの判定
    $this->info('');
    $this->info('🌍 実行環境:');
    $this->line("APP_ENV: " . env('APP_ENV', 'not set'));
    $this->line("Railway環境: " . (env('RAILWAY_ENVIRONMENT') ? 'YES' : 'NO'));

    if (env('RAILWAY_ENVIRONMENT')) {
      $this->info("🚂 Railway環境で実行中");
      $this->line("Railway Environment: " . env('RAILWAY_ENVIRONMENT'));
    } else {
      $this->info("🏠 ローカル環境で実行中");
    }

    $this->info('');
    $this->info('=== デバッグ完了 ===');
  }
}
