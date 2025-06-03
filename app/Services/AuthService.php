<?php

namespace App\Services;

use App\Models\User;
use App\Mail\PreRegistrationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Carbon\Carbon;
use App\Mail\EmailChangeVerification;


class AuthService extends BaseService
{
  /**
   * ユーザー登録（仮登録）処理
   *
   * @param array $data バリデーション済みの登録データ
   * @return array 登録結果とメッセージ
   */
  public function register(array $data): array
  {
    // トランザクション開始
    DB::beginTransaction();
    try {
      $user = User::where('email', $data['email'])->first();

      if ($user) {
        if ($user->is_verified) {
          DB::rollBack();
          return $this->errorResponse('already_registered', 'このメールアドレスは既に登録されています。');
        }

        // バンされたメールアドレスでの再登録を制限
        if ($user->isBanned()) {
          DB::rollBack();
          return $this->errorResponse('email_banned', 'このメールアドレスは利用停止されており、新規登録できません。');
        }

        // 仮登録状態の場合は入力内容で上書き更新する
        $user->updateProvisionalRegistration([
          'password' => Hash::make($data['password']),
          'name'     => $data['name'],
        ]);
      } else {
        // ユーザーが存在しない場合は新規作成する
        $user = User::create([
          'email'       => $data['email'],
          'password'    => Hash::make($data['password']),
          'name'        => $data['name'],
          'is_verified' => false,
        ]);
      }

      // 認証用メールの送信を同期実施（テスト用）
      Mail::to($user->email)->send(new PreRegistrationEmail($user));

      // throw new \Exception('テスト用例外: メール送信をスキップ');

      DB::commit();
    } catch (\Exception $ex) {
      DB::rollBack();
      Log::error('登録処理中にエラーが発生しました: ' . $ex->getMessage(), [
        'email' => $data['email']
      ]);
      return $this->errorResponse('registration_failed', '登録処理に失敗しました。');
    }

    return [
      'status'  => 'success',
      'message' => '仮登録完了。確認メールを送信しました。メール内の認証リンクをクリックして本登録を完了してください。'
    ];
  }

  /**
   * メール認証処理を行い、成功ならアクセストークンを返す。
   *
   * @param string $token  リクエストから受け取ったトークン
   * @param string $ip     リクエスト元のIPアドレス（ログ用）
   * @return array         成功時は 'status' => 'success'、失敗時は 'status' => 'error' とともにエラーメッセージを返す
   */
  public function verifyEmail(string $token, string $ip): array
  {
    // トークンが空の場合
    if (!$token) {
      return $this->errorResponse('token_missing', '無効な認証リンクです。');
    }

    // ユーザーをトークンから検索
    $user = User::where('email_verification_token', $token)->first();
    if (!$user) {
      return $this->errorResponse('token_invalid', '無効な認証リンクです。（該当するユーザーが見つかりません）');
    }

    // トークンの有効期限チェック
    if (Carbon::now()->greaterThan($user->token_expires_at)) {
      return [
        'status'     => 'error',
        'error_type' => 'token_expired',
        'message'    => '認証リンクの有効期限が切れています。お手数ですが、再度登録を行なってください。',
      ];
    }

    // すでに認証済みの場合
    if ($user->is_verified) {
      return [
        'status'     => 'error',
        'error_type' => 'already_verified',
        'message'    => '既に登録済みです。',
      ];
    }

    // ユーザー情報を更新して本登録とする
    $user->update([
      'is_verified'       => true,
      'email_verified_at' => Carbon::now(),
    ]);

    // Sanctum を利用してアクセストークンを発行
    $tokenResult = $user->createToken('authToken');
    $tokenResult->accessToken->update([
      'expires_at' => Carbon::now()->addDay(),
    ]);

    return [
      'status'       => 'success',
      'message'      => '認証が完了しました。自動ログインします。',
      'access_token' => $tokenResult->plainTextToken,
      'token_type'   => 'Bearer',
      'email'        => $user->email,
    ];
  }

  /**
   * ログイン処理を実行。
   *
   * @param string $email    入力されたメールアドレス
   * @param string $password 入力されたパスワード
   * @param string $ip       リクエスト元のIPアドレス（ログ用）
   * @return array           成功時は 'status' => 'success'、失敗時は 'status' => 'error' とともにエラーメッセージを返す
   */
  public function login(string $email, string $password, string $ip): array
  {
    try {
      // ユーザーの検索
      $user = User::where('email', $email)->first();

      if (!$user) {
        Log::warning('ログイン失敗: ユーザーが見つかりません', ['email' => $email, 'ip' => $ip]);
        return [
          'status'     => 'error',
          'error_type' => 'invalid_credentials',
          'message'    => 'メールアドレスまたはパスワードが正しくありません。',
        ];
      }

      // パスワードチェック
      if (!Hash::check($password, $user->password)) {
        Log::warning('ログイン失敗: パスワードが一致しません', ['email' => $email, 'ip' => $ip]);
        return [
          'status'     => 'error',
          'error_type' => 'invalid_credentials',
          'message'    => 'メールアドレスまたはパスワードが正しくありません。',
        ];
      }

      // 削除されたユーザーのログイン制限
      if ($user->isDeleted()) {
        Log::warning('ログイン失敗: 削除されたアカウント', ['user_id' => $user->id, 'ip' => $ip]);
        return [
          'status'     => 'error',
          'error_type' => 'account_deleted',
          'message'    => 'このアカウントは削除されています。ログインできません。',
        ];
      }

      // バンされたユーザーのログイン制限
      if ($user->isBanned()) {
        Log::warning('ログイン失敗: バンされたアカウント', ['user_id' => $user->id, 'ip' => $ip]);
        return [
          'status'     => 'error',
          'error_type' => 'account_banned',
          'message'    => 'このアカウントは利用停止されています。ログインできません。',
        ];
      }

      if (!$user->is_verified) {
        Log::warning('ログイン失敗: 未認証アカウント', ['user_id' => $user->id, 'ip' => $ip]);
        return [
          'status'     => 'error',
          'error_type' => 'not_verified',
          'message'    => 'メール認証がお済みでないようです。登録メールアドレスに送られたメールをご確認ください。',
        ];
      }

      // アクセストークンの発行
      $tokenResult = $user->createToken('authToken');
      $tokenResult->accessToken->update([
        'expires_at' => Carbon::now()->addDay(),
      ]);

      Log::info('ログイン成功', ['user_id' => $user->id, 'email' => $email, 'ip' => $ip]);

      return [
        'status'       => 'success',
        'message'      => 'ログインに成功しました。',
        'access_token' => $tokenResult->plainTextToken,
        'token_type'   => 'Bearer',
        'email'        => $user->email,
      ];
    } catch (\Exception $e) {
      Log::error('ログイン処理でエラーが発生', [
        'email' => $email,
        'error' => $e->getMessage(),
        'ip' => $ip
      ]);

      return [
        'status'     => 'error',
        'error_type' => 'login_error',
        'message'    => 'ログイン処理中にエラーが発生しました。',
      ];
    }
  }

  /**
   * パスワード再設定リンクのメール送信処理
   *
   * @param string $email
   * @param string $ip
   * @return array
   */
  public function sendResetLinkEmail(string $email, string $ip): array
  {
    Log::info('パスワード再設定リンクの送信試行を開始しました', [
      'email' => $email,
      'ip'    => $ip,
    ]);

    // まずユーザーが存在するかチェック
    $user = User::where('email', $email)->first();

    if (!$user) {
      Log::warning('パスワード再設定: ユーザーが見つかりません', ['email' => $email]);
      return [
        'status'     => 'error',
        'error_type' => 'invalid_user',
        'message'    => __('このメールアドレスは登録されていません。'),
      ];
    }

    // Google認証ユーザーの場合は専用メッセージを返す
    if ($user->social_type === 'google') {
      Log::info('Google認証ユーザーのパスワードリセット試行', [
        'email' => $email,
        'user_id' => $user->id
      ]);
      return [
        'status' => 'error',
        'error_type' => 'google_user',
        'message' => 'このアカウントはGoogle認証でログインされています。パスワードリセットは不要です。Googleアカウントでログインしてください。'
      ];
    }

    $status = Password::sendResetLink(['email' => $email]);

    if ($status === Password::RESET_LINK_SENT) {
      Log::info('パスワード再設定リンクの送信に成功しました', [
        'email' => $email,
      ]);
      return [
        'status'  => 'success',
        'message' => __('パスワード再設定用リンクを ' . $email . ' に送信しました。'),
      ];
    }

    if ($status === Password::INVALID_USER) {
      Log::warning('パスワード再設定リンクの送信に失敗しました（ユーザーが見つかりません）', [
        'email'  => $email,
        'status' => $status,
      ]);
      return [
        'status'     => 'error',
        'error_type' => 'invalid_user',
        'message'    => __('このメールアドレスは登録されていません。'),
      ];
    }

    if ($status === Password::RESET_THROTTLED) {
      Log::warning('パスワード再設定リンクの送信に失敗しました（スロットリング）', [
        'email'  => $email,
        'status' => $status,
      ]);
      return [
        'status'     => 'error',
        'error_type' => 'throttled',
        'message'    => __('送信が連続して行われました。しばらく待ってから再度お試しください。'),
      ];
    }

    Log::warning('パスワード再設定リンクの送信に失敗しました', [
      'email'  => $email,
      'status' => $status,
    ]);
    return $this->errorResponse('send_failed', 'パスワード再設定リンクの送信に失敗しました。');
  }

  /**
   * パスワードリセット処理を実行
   *
   * @param array $data ['email', 'password', 'password_confirmation', 'token']
   * @return array 成功時は 'status' => 'success'、失敗時は 'status' => 'error' とエラーメッセージを返す
   */
  public function resetPassword(array $data): array
  {
    Log::info('パスワードリセットの試行を開始しました', [
      'email' => $data['email'],
    ]);

    // Google認証ユーザーかチェック
    $user = User::where('email', $data['email'])->first();
    if ($user && $user->social_type === 'google') {
      Log::warning('Google認証ユーザーのパスワードリセット試行', [
        'email' => $data['email'],
        'user_id' => $user->id
      ]);
      return [
        'status' => 'error',
        'error_type' => 'google_user',
        'message' => 'このアカウントはGoogle認証でログインされています。パスワードリセットは不要です。'
      ];
    }

    DB::beginTransaction();
    try {
      $status = Password::reset(
        $data,
        function ($user, $password) {
          $user->forceFill([
            'password' => Hash::make($password),
          ])->save();

          // Remember Token の更新
          $user->setRememberToken(Str::random(60));

          // throw new \Exception('テスト例外');

          // パスワード再設定完了イベントの発火（リスナーがメール送信などを非同期で処理する場合は、その設定も有効）
          event(new PasswordReset($user));
        }
      );

      if ($status === Password::PASSWORD_RESET) {
        DB::commit();
        Log::info('パスワードリセットに成功しました', [
          'email' => $data['email'],
        ]);
        return $this->successResponse('パスワードを新しく設定しました。');
      } else {
        DB::rollBack();
        Log::warning('パスワードリセットに失敗しました', [
          'email'  => $data['email'],
          'status' => $status,
        ]);
        return $this->errorResponse('password_reset_failed', __($status));
      }
    } catch (\Exception $ex) {
      DB::rollBack();
      Log::error('パスワードリセット中にエラーが発生しました: ' . $ex->getMessage(), [
        'email' => $data['email'],
      ]);
      return $this->errorResponse('password_reset_failed', 'パスワードリセットに失敗しました。');
    }
  }

  /**
   * メールアドレス変更リクエスト処理
   *
   * @param User $user 現在のユーザー
   * @param string $newEmail 新しいメールアドレス
   * @param string $ip IPアドレス（ログ用）
   * @return array 処理結果
   */
  public function requestEmailChange(User $user, string $newEmail, string $ip): array
  {
    // 現在のメールアドレスと同じ場合
    if ($user->email === $newEmail) {
      return $this->errorResponse('same_email', '現在と同じメールアドレスです。');
    }

    // すでに存在するメールアドレスか確認（バリデーション済みだが念のため）
    if (User::where('email', $newEmail)->exists()) {
      return $this->errorResponse('email_taken', 'このメールアドレスは既に使用されています。');
    }

    // トークン生成
    $token = Str::random(60);
    $expiresAt = Carbon::now()->addHour();

    DB::beginTransaction();

    try {
      // ユーザー情報を更新
      $user->email_change_token = $token;
      $user->new_email = $newEmail;
      $user->token_expires_at = $expiresAt;
      $user->save();

      // メール送信（テスト用に同期送信）
      Mail::to($newEmail)->send(new EmailChangeVerification($user, $token));

      DB::commit();

      Log::info('メールアドレス変更確認メールを送信しました', [
        'user_id' => $user->id,
        'new_email' => $newEmail
      ]);

      return [
        'status' => 'success',
        'message' => '確認メールを送信しました。メール内のリンクをクリックして変更を完了してください。'
      ];
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('メールアドレス変更確認メールの送信に失敗しました', [
        'user_id' => $user->id,
        'new_email' => $newEmail,
        'error' => $e->getMessage()
      ]);

      return $this->errorResponse('email_send_failed', 'メール送信に失敗しました。後でもう一度お試しください。');
    }
  }

  /**
   * メールアドレス変更確認処理
   *
   * @param string $token 認証トークン
   * @param string $ip IPアドレス（ログ用）
   * @return array 処理結果
   */
  public function confirmEmailChange(string $token, string $ip): array
  {
    if (!$token) {
      return $this->errorResponse('token_missing', '無効な認証リンクです。');
    }

    // トークンからユーザーを検索
    $user = User::where('email_change_token', $token)->first();

    if (!$user || !$user->new_email) {
      return $this->errorResponse('token_invalid', '無効な認証リンクです。（該当するユーザーが見つかりません）');
    }

    // トークンの有効期限チェック
    if (Carbon::now()->greaterThan($user->token_expires_at)) {
      return $this->errorResponse('token_expired', '認証リンクの有効期限が切れています。もう一度メールアドレス変更をお試しください。');
    }

    DB::beginTransaction();

    try {
      // メールアドレスを更新
      $oldEmail = $user->email;
      $user->email = $user->new_email;
      $user->email_change_token = null;
      $user->new_email = null;
      $user->token_expires_at = null;
      $user->save();

      DB::commit();

      Log::info('メールアドレスが正常に更新されました', [
        'user_id' => $user->id,
        'old_email' => $oldEmail,
        'new_email' => $user->email
      ]);

      return [
        'status' => 'success',
        'message' => 'メールアドレスが正常に更新されました。',
        'email' => $user->email
      ];
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('メールアドレス更新中にエラーが発生しました', [
        'user_id' => $user->id,
        'error' => $e->getMessage()
      ]);

      return $this->errorResponse('update_failed', 'メールアドレスの更新に失敗しました。後でもう一度お試しください。');
    }
  }
}
