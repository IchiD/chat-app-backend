<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\PreRegistrationEmail;
use App\Models\User;

class DebugController extends Controller
{
  /**
   * メール設定のデバッグ情報を表示
   */
  public function emailConfig()
  {
    // セキュリティのため、本番環境でのみ利用可能
    if (env('APP_ENV') !== 'production') {
      return response()->json(['error' => 'この機能は本番環境でのみ利用できます']);
    }

    $config = [
      'mail_driver' => config('mail.default'),
      'mail_host' => config('mail.mailers.smtp.host'),
      'mail_port' => config('mail.mailers.smtp.port'),
      'mail_username_set' => !empty(config('mail.mailers.smtp.username')),
      'mail_password_set' => !empty(config('mail.mailers.smtp.password')),
      'mail_encryption' => config('mail.mailers.smtp.encryption'),
      'mail_from_address' => config('mail.from.address'),
      'mail_from_name' => config('mail.from.name'),
      'railway_env' => env('RAILWAY_ENVIRONMENT'),
      'app_env' => env('APP_ENV'),

      // 環境変数の確認
      'env_vars' => [
        'MAIL_MAILER' => env('MAIL_MAILER'),
        'MAIL_HOST' => env('MAIL_HOST'),
        'MAIL_PORT' => env('MAIL_PORT'),
        'MAIL_USERNAME_SET' => !empty(env('MAIL_USERNAME')),
        'MAIL_PASSWORD_SET' => !empty(env('MAIL_PASSWORD')),
        'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
        'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
      ]
    ];

    return response()->json($config);
  }

  /**
   * メール送信テスト
   */
  public function testEmail(Request $request)
  {
    // セキュリティのため、本番環境でのみ利用可能
    if (env('APP_ENV') !== 'production') {
      return response()->json(['error' => 'この機能は本番環境でのみ利用できます']);
    }

    $email = $request->get('email', 'test@example.com');

    try {
      // ログに詳細情報を出力
      Log::info('=== メール送信テスト開始 ===', [
        'target_email' => $email,
        'mail_driver' => config('mail.default'),
        'mail_host' => config('mail.mailers.smtp.host'),
        'mail_port' => config('mail.mailers.smtp.port'),
      ]);

      // 簡単なテストメール送信
      Mail::raw('これはテストメールです。Railway環境からの送信テストです。', function ($message) use ($email) {
        $message->to($email)
          ->subject('Railway メール送信テスト')
          ->from(config('mail.from.address'), config('mail.from.name'));
      });

      Log::info('✅ メール送信テスト成功', ['email' => $email]);

      return response()->json([
        'success' => true,
        'message' => 'メール送信テストが成功しました',
        'email' => $email
      ]);
    } catch (\Exception $e) {
      Log::error('❌ メール送信テスト失敗', [
        'email' => $email,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'メール送信テストが失敗しました',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * SMTP接続テスト
   */
  public function testSmtp()
  {
    // セキュリティのため、本番環境でのみ利用可能
    if (env('APP_ENV') !== 'production') {
      return response()->json(['error' => 'この機能は本番環境でのみ利用できます']);
    }

    try {
      Log::info('=== SMTP接続テスト開始 ===');

      if (config('mail.default') !== 'smtp') {
        throw new \Exception('現在のメールドライバーはSMTPではありません: ' . config('mail.default'));
      }

      $transport = Mail::getSymfonyTransport();
      Log::info('✅ SMTP Transport作成成功');

      return response()->json([
        'success' => true,
        'message' => 'SMTP接続テストが成功しました',
        'driver' => config('mail.default'),
        'host' => config('mail.mailers.smtp.host'),
        'port' => config('mail.mailers.smtp.port')
      ]);
    } catch (\Exception $e) {
      Log::error('❌ SMTP接続テスト失敗', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'SMTP接続テストが失敗しました',
        'error' => $e->getMessage()
      ], 500);
    }
  }
}
