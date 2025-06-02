<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetSuccess;
use Illuminate\Support\Facades\Log;

class SendPasswordResetSuccessNotification
{
  /**
   * Handle the event.
   *
   * @param  \Illuminate\Auth\Events\PasswordReset  $event
   * @return void
   */
  public function handle(PasswordReset $event)
  {
    $user = $event->user;

    try {
      Mail::to($user->email)->send(new PasswordResetSuccess($user));
      Log::info('パスワードリセット完了通知メールを送信しました', [
        'email' => $user->email,
      ]);
    } catch (\Exception $ex) {
      Log::error('パスワードリセット完了通知メールの送信に失敗しました', [
        'email'       => $user->email,
        'エラー内容'  => $ex->getMessage(),
      ]);
    }
  }
}
