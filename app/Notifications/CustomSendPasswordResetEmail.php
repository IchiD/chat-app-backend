<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class CustomSendPasswordResetEmail extends Notification
{
  use Queueable;

  public $token;

  /**
   * Create a new notification instance.
   *
   * @param string $token
   * @return void
   */
  public function __construct($token)
  {
    $this->token = $token;
  }

  /**
   * Get the notification's delivery channels.
   *
   * @return array<int, string>
   */
  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  /**
   * Build the mail representation of the notification.
   *
   * @param  mixed  $notifiable
   * @return \Illuminate\Notifications\Messages\MailMessage
   */
  public function toMail($notifiable)
  {
    // パスワード再設定URLを作成
    // $url = url(config('app.url') . route('password.reset', [
    //   'token' => $this->token,
    //   'email' => $notifiable->getEmailForPasswordReset(),
    // ], false));

    // フロントエンドのパスワードリセットページのURLを直接生成
    // .envにFRONTEND_URLが設定されていることを前提とします。
    // 例: FRONTEND_URL=http://localhost:3000
    // $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
    // 今回は直接指定します。
    $frontendUrl = 'http://localhost:3000';
    $url = $frontendUrl . '/auth/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

    return (new MailMessage)
      ->subject(Lang::get('パスワード再設定のお知らせ'))
      ->view('emails.password_reset_request', [
        'user'     => $notifiable,
        'resetUrl' => $url,
      ]);
  }

  /**
   * Get the array representation of the notification.
   *
   * @return array<string, mixed>
   */
  public function toArray(object $notifiable): array
  {
    return [
      //
    ];
  }
}
