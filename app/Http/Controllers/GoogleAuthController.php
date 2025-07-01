<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
  protected $authService;

  public function __construct(AuthService $authService)
  {
    $this->authService = $authService;
  }

  /**
   * Googleの認証ページにリダイレクト
   */
  public function redirectToGoogle(Request $request)
  {
    try {
      Log::info('Google認証へのリダイレクトを開始');

      // intentパラメータを取得（デフォルトは'login'）
      $intent = $request->query('intent', 'login');

      // intentが正しい値かチェック
      if (!in_array($intent, ['login', 'register'])) {
        $intent = 'login';
      }

      // セッションにintentを保存
      session(['google_auth_intent' => $intent]);

      // 環境変数の確認
      $clientId = config('services.google.client_id');
      $redirectUri = config('services.google.redirect');

      Log::info('Google OAuth設定確認', [
        'client_id' => $clientId ? 'SET' : 'NOT_SET',
        'redirect_uri' => $redirectUri,
        'intent' => $intent
      ]);

      // redirect_uriを明示的に設定
      return Socialite::driver('google')
        ->redirectUrl($redirectUri)
        ->redirect();
    } catch (\Exception $e) {
      Log::error('Google認証リダイレクトでエラーが発生しました', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      return response()->json([
        'status' => 'error',
        'message' => 'Google認証サービスに接続できませんでした。'
      ], 500);
    }
  }

  /**
   * Googleからのコールバック処理
   */
  public function handleGoogleCallback(Request $request)
  {
    // intent変数をtryの外で定義
    $intent = 'login'; // デフォルト

    try {
      Log::info('Googleコールバック処理を開始', [
        'query_params' => $request->query()
      ]);

      // セッションからintentを取得
      $intent = session('google_auth_intent', 'login');

      // セッションから削除
      session()->forget('google_auth_intent');

      Log::info('Google認証のintentを取得', ['intent' => $intent]);

      // Googleからユーザー情報を取得
      $googleUser = Socialite::driver('google')->user();

      Log::info('Googleユーザー情報を取得しました', [
        'google_id' => $googleUser->id,
        'email' => $googleUser->email,
        'name' => $googleUser->name,
        'intent' => $intent
      ]);

      // まずGoogle IDで既存ユーザーを検索（削除されたユーザーも含む）
      $userByGoogleId = User::withTrashed()->where('google_id', $googleUser->id)->first();

      // 次にメールアドレスで既存ユーザーを検索（削除されたユーザーも含む）
      $userByEmail = User::withTrashed()->where('email', $googleUser->email)->first();

      $user = null;

      if ($userByGoogleId) {
        // Google IDで既存ユーザーが見つかった場合

        // 新規登録の意図なのに既存ユーザーがいる場合はエラー
        if ($intent === 'register' && !$userByGoogleId->isDeleted()) {
          Log::warning('Google新規登録で既存ユーザーが見つかりました', [
            'google_id' => $googleUser->id,
            'email' => $googleUser->email
          ]);
          return $this->redirectWithError('このGoogleアカウントは既に登録されています。ログインページからログインしてください。', $intent);
        }

        $user = $userByGoogleId;

        Log::info('Google IDで既存ユーザーが見つかりました', [
          'user_id' => $user->id,
          'current_email' => $user->email,
          'google_email' => $googleUser->email,
          'is_deleted' => $user->isDeleted()
        ]);

        // 削除されていない場合のみメールアドレス同期処理を実行
        if (!$user->isDeleted()) {
          // メールアドレスの不整合チェック
          if ($user->email !== $googleUser->email) {
            Log::warning('Google認証でメールアドレス不整合を検出', [
              'user_id' => $user->id,
              'app_email' => $user->email,
              'google_email' => $googleUser->email
            ]);

            // メールアドレスが変更されている場合、Googleのメールアドレスで同期
            $user->update([
              'email' => $googleUser->email,
              'avatar' => $googleUser->avatar,
            ]);

            Log::info('Google認証によりメールアドレスを同期しました', [
              'user_id' => $user->id,
              'new_email' => $googleUser->email
            ]);
          }
        }
      } elseif ($userByEmail) {
        // メールアドレスで既存ユーザーが見つかった場合（Google ID未設定）

        // 新規登録の意図なのに既存ユーザーがいる場合はエラー
        if ($intent === 'register' && !$userByEmail->isDeleted()) {
          Log::warning('Google新規登録で既存メールアドレスが見つかりました', [
            'email' => $googleUser->email
          ]);
          return $this->redirectWithError('このメールアドレスは既に登録されています。ログインページからログインしてください。', $intent);
        }

        $user = $userByEmail;

        Log::info('メールアドレスで既存ユーザーが見つかりました', [
          'user_id' => $user->id,
          'is_deleted' => $user->isDeleted()
        ]);

        // 削除されていない場合のみGoogle ID設定処理を実行
        if (!$user->isDeleted()) {
          // Google IDを設定
          $user->update([
            'google_id' => $googleUser->id,
            'avatar' => $googleUser->avatar,
            'social_type' => 'google'
          ]);
          Log::info('既存ユーザーにGoogle ID を設定しました', ['user_id' => $user->id]);
        }
      } else {
        // 新規ユーザーの場合

        // ログインの意図なのに新規ユーザーの場合はエラー
        if ($intent === 'login') {
          Log::warning('Googleログインで未登録ユーザー', [
            'email' => $googleUser->email
          ]);
          return $this->redirectWithError('このGoogleアカウントは登録されていません。新規登録ページから登録してください。', $intent);
        }

        Log::info('新規ユーザーを作成します', ['email' => $googleUser->email]);

        $user = User::create([
          'name' => $googleUser->name,
          'email' => $googleUser->email,
          'google_id' => $googleUser->id,
          'avatar' => $googleUser->avatar,
          'social_type' => 'google',
          'is_verified' => true,
          'is_banned' => false,
          'email_verified_at' => Carbon::now(),
          'password' => Hash::make(Str::random(32)), // ランダムパスワード
        ]);

        // サポートチャットルームを自動作成
        $this->createSupportChatRoom($user);

        Log::info('新規ユーザーが作成されました', ['user_id' => $user->id]);
        \App\Services\OperationLogService::log('frontend', 'user_register', 'user_id:' . $user->id . ' google:true');
      }

      // 共通の処理
      if ($user) {
        // 削除されたアカウントの場合は復元処理
        if ($user->isDeleted()) {
          Log::info('削除されたアカウントのGoogle認証による復元', [
            'user_id' => $user->id,
            'email' => $user->email,
            'deleted_by_self' => $user->deleted_by_self,
            'intent' => $intent
          ]);

          // ログインの意図で削除されたアカウントにアクセスした場合はエラー
          if ($intent === 'login') {
            Log::warning('削除されたアカウントでのGoogleログイン試行', [
              'user_id' => $user->id,
              'email' => $user->email
            ]);
            return $this->redirectWithError('このアカウントは削除されています。再登録する場合は新規登録ページから登録してください。', $intent);
          }

          // 再登録可能かチェック
          if (!$user->canReRegister()) {
            Log::warning('削除されたアカウントは再登録許可されていません', [
              'user_id' => $user->id,
              'email' => $user->email
            ]);
            return $this->redirectWithError('このアカウントでの再登録は許可されていません。管理者にお問い合わせください。', $intent);
          }

          // 自己削除の場合は復元
          if ($user->isDeletedBySelf()) {
            $user->restoreBySelf();
            Log::info('自己削除されたアカウントをGoogle認証により復元しました', [
              'user_id' => $user->id,
              'email' => $user->email
            ]);
          } else {
            // 管理者削除で再登録許可されている場合は復元
            $user->restoreByAdmin();
            Log::info('管理者削除されたアカウントをGoogle認証により復元しました', [
              'user_id' => $user->id,
              'email' => $user->email
            ]);
          }

          // Google情報を更新（復元時）
          $user->update([
            'name' => $googleUser->name, // Googleの名前で更新
            'google_id' => $googleUser->id,
            'avatar' => $googleUser->avatar,
            'social_type' => 'google',
            'is_verified' => true,
            'email_verified_at' => Carbon::now()
          ]);

          Log::info('復元されたユーザーのGoogle情報を更新しました', [
            'user_id' => $user->id,
            'name' => $googleUser->name,
            'email' => $user->email
          ]);

          // 復元されたユーザーのサポートチャットルームを確認・作成
          $this->createSupportChatRoom($user);
        }

        // バンされたアカウントの場合
        if ($user->isBanned()) {
          Log::warning('バンされたアカウントでのGoogle認証試行', [
            'user_id' => $user->id,
            'email' => $user->email
          ]);
          return $this->redirectWithError('このアカウントは利用停止されています。', $intent);
        }

        // メール認証が完了していない場合は自動認証
        if (!$user->is_verified) {
          $user->update([
            'is_verified' => true,
            'email_verified_at' => Carbon::now()
          ]);
          Log::info('Google認証によりメール認証を完了しました', ['user_id' => $user->id]);
        }
      }

      // 最終ログイン日時を更新
      $user->update([
        'last_login_at' => Carbon::now(),
      ]);

      // Sanctumトークンを発行
      $tokenResult = $user->createToken('authToken');

      Log::info('Google認証が完了しました', [
        'user_id' => $user->id,
        'email' => $user->email
      ]);
      \App\Services\OperationLogService::log('frontend', 'user_login', 'user_id:' . $user->id . ' google:true');

      // フロントエンドにトークンを渡してリダイレクト
      return $this->redirectWithSuccess($tokenResult->plainTextToken, $user);
    } catch (\Exception $e) {
      Log::error('Google認証コールバック処理でエラーが発生しました', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return $this->redirectWithError('Google認証処理中にエラーが発生しました。', $intent);
    }
  }

  /**
   * エラー時のフロントエンドリダイレクト
   */
  private function redirectWithError(string $message, string $intent = 'login')
  {
    $encodedMessage = urlencode($message);
    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

    // intentに応じてリダイレクト先を変更
    $redirectPath = $intent === 'register' ? '/auth/register' : '/auth/login';

    return redirect("{$frontendUrl}{$redirectPath}?error={$encodedMessage}");
  }

  /**
   * 成功時のフロントエンドリダイレクト
   */
  private function redirectWithSuccess(string $token, User $user)
  {
    $encodedToken = urlencode($token);
    $userData = [
      'id' => $user->id,
      'name' => $user->name,
      'email' => $user->email,
      'friend_id' => $user->friend_id,
      'google_id' => $user->google_id,
      'avatar' => $user->avatar,
      'social_type' => $user->social_type,
    ];
    $encodedUserData = urlencode(json_encode($userData));

    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
    $redirectUrl = "{$frontendUrl}/auth/google/callback?token={$encodedToken}&user={$encodedUserData}";

    Log::info('フロントエンドにリダイレクトします', [
      'redirect_url' => $redirectUrl,
      'user_data' => $userData,
      'token_length' => strlen($token)
    ]);

    return redirect($redirectUrl);
  }

  /**
   * サポートチャットルームを作成
   *
   * @param User $user
   * @return void
   */
  private function createSupportChatRoom(User $user): void
  {
    try {
      // 既存のサポートチャットルームがないことを確認
      $existingSupport = \App\Models\ChatRoom::where('type', 'support_chat')
        ->where('participant1_id', $user->id)
        ->first();

      if (!$existingSupport) {
        \App\Models\ChatRoom::create([
          'type' => 'support_chat',
          'participant1_id' => $user->id,
          'participant2_id' => null, // サポートチャットは管理者が後から参加
        ]);

        Log::info('サポートチャットルームを作成しました', ['user_id' => $user->id]);
      }
    } catch (\Exception $e) {
      Log::error('サポートチャットルーム作成でエラーが発生しました', [
        'user_id' => $user->id,
        'error' => $e->getMessage()
      ]);
      // サポートチャット作成の失敗は登録全体を失敗させない
    }
  }
}
