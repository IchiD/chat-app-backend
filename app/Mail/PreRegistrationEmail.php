<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PreRegistrationEmail extends Mailable
{
  use Queueable, SerializesModels;

  public $user;

  // コンストラクタでユーザー情報を受け取る
  public function __construct(User $user)
  {
    $this->user = $user;
  }

  // メールのビルド
  public function build()
  {
    // フロントエンドのベースURLを環境変数から取得
    $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

    // 認証リンク（フロントエンドのverifyページを使用）
    $verificationUrl = $frontendUrl . '/auth/verify?token=' . $this->user->email_verification_token;

    return $this->subject('メール認証のお願い')
      ->view('emails.verify')
      ->with([
        'verificationUrl' => $verificationUrl,
        'user' => $this->user,
      ]);
  }
}
