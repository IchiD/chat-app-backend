<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmail extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'email:test {email : 送信先のメールアドレス}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'メール送信をテストします';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $email = $this->argument('email');

    try {
      $this->info('メール送信をテストしています...');

      Mail::raw('これはテストメールです。メール送信が正常に動作しています！', function ($message) use ($email) {
        $message->to($email)
          ->subject('メール送信テスト - ' . config('app.name'));
      });

      $this->info("✅ メールが正常に送信されました: {$email}");
    } catch (\Exception $e) {
      $this->error("❌ メール送信に失敗しました: " . $e->getMessage());
      Log::error('メール送信テストエラー', [
        'email' => $email,
        'error' => $e->getMessage()
      ]);
    }
  }
}
