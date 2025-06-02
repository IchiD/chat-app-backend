<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailChangeVerification extends Mailable
{
  use Queueable, SerializesModels;

  public $user;
  public $token;

  /**
   * Create a new message instance.
   *
   * @param User $user
   * @param string $token
   * @return void
   */
  public function __construct(User $user, string $token)
  {
    $this->user = $user;
    $this->token = $token;
  }

  /**
   * Build the message.
   *
   * @return $this
   */
  public function build()
  {
    // 認証リンク（フロントエンドのverify-email-changeページを使用）
    $verificationUrl = 'http://localhost:3000/auth/verify-email-change?token=' . $this->token;

    return $this->subject('メールアドレス変更確認')
      ->view('emails.verify-email-change')
      ->with([
        'verificationUrl' => $verificationUrl,
        'user' => $this->user,
        'newEmail' => $this->user->new_email,
      ]);
  }
}
