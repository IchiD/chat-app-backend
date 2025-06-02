<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

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
    ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
      // .envにFRONTEND_URLが設定されていることを前提とします。
      // 例: FRONTEND_URL=http://localhost:3000
      // $frontendUrl = config('app.frontend_url', 'http://localhost:3000'); 
      // 今回は直接指定します。
      $frontendUrl = 'http://localhost:3000';
      return $frontendUrl . '/auth/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    });
  }
}
