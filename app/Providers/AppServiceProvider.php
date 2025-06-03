<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // 本番環境でHTTPS強制
    if (app()->environment('production')) {
      URL::forceScheme('https');

      // Railway特有の設定
      if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $_SERVER['HTTPS'] = 'on';
      }
    }

    ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
      // フロントエンドのベースURLを環境変数から取得
      $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
      return $frontendUrl . '/auth/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    });
  }
}
