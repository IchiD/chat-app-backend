<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordResetEmailRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Mail\PreRegistrationEmail;
use App\Services\AuthService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
  protected $authService;

  public function __construct(AuthService $authService)
  {
    $this->authService = $authService;
  }
  /**
   * 仮登録処理
   */
  public function register(RegisterRequest $request)
  {
    // バリデーション済みデータを取得
    $data = $request->validated();

    Log::info('仮登録の試行を開始しました', [
      'email' => $data['email'],
      'ip'    => $request->ip(),
    ]);

    $result = $this->authService->register($data);

    if ($result['status'] === 'error') {
      Log::warning('仮登録処理でエラーが発生しました', ['email' => $data['email']]);
      return response()->json($result, 422);
    }

    Log::info('仮登録処理が正常に完了しました', ['email' => $data['email']]);
    return response()->json($result, 201);
  }

  /**
   * メール認証リンクによる本登録処理
   */
  public function verifyEmail(VerifyEmailRequest $request)
  {
    $data = $request->validated();
    $token = $data['token'];
    $redirectTo = $request->query('redirect_to', '/user'); // デフォルトは /user

    Log::info('メール認証の試行を開始しました', [
      'token' => $token,
      'ip'    => $request->ip(),
      'redirect_to' => $redirectTo
    ]);

    $result = $this->authService->verifyEmail($token, $request->ip());

    if ($result['status'] === 'error') {
      // エラーの場合はレスポンスを返す
      Log::warning('メール認証に失敗しました', [
        'error_type' => $result['error_type'],
        'ip'         => $request->ip(),
      ]);
      // エラーコードは必要に応じて調整してください
      return response()->json($result, 400);
    }

    Log::info('アクセストークンを発行しました', ['email' => $result['email']]);

    // 成功時にリダイレクト情報を含める
    $result['frontend_redirect_url'] = env('FRONTEND_URL', 'http://localhost:3000') . $redirectTo;

    return response()->json($result, 200);
  }

  /**
   * ログイン処理
   */
  public function login(LoginRequest $request)
  {
    Log::info('ログインの試行を開始しました', [
      'email' => $request->email,
      'ip'    => $request->ip(),
    ]);

    // サービス層に処理を委譲
    $result = $this->authService->login(
      $request->email,
      $request->password,
      $request->ip()
    );

    // エラーの場合
    if ($result['status'] === 'error') {
      // フロントエンドが期待するレスポンス形式に変換
      $errorResponse = [
        'message' => $result['message'],
        'error_type' => $result['error_type'] ?? 'login_error'
      ];

      return response()->json($errorResponse, 401);
    }

    Log::info('ログインに成功しました', [
      'email' => $result['email'],
      'ip'    => $request->ip(),
    ]);

    return response()->json($result, 200);
  }

  /**
   * ログアウト処理
   */
  public function logout(Request $request)
  {
    Log::info('ログアウトの試行を開始しました', [
      'email' => $request->user()->email,
      'ip'    => $request->ip(),
    ]);

    try {
      $request->user()->currentAccessToken()->delete();
      Log::info('ログアウトに成功しました', [
        'email' => $request->user()->email,
        'ip'    => $request->ip(),
      ]);
      return response()->json([
        'message' => 'ログアウトしました。',
      ], 200);
    } catch (\Exception $e) {
      Log::error('ログアウトに失敗しました', [
        'email' => $request->user()->email,
        'ip'    => $request->ip(),
        'error' => $e->getMessage(),
      ]);
      return response()->json([
        'message' => 'ログアウト処理中にエラーが発生しました。',
      ], 500);
    }
  }

  /**
   * パスワード再設定リンク送信
   */
  public function sendResetLinkEmail(PasswordResetEmailRequest $request)
  {
    $email = $request->input('email');
    $ip = $request->ip();

    $result = $this->authService->sendResetLinkEmail($email, $ip);

    if ($result['status'] === 'success') {
      return response()->json($result, 200);
    }

    if ($result['status'] === 'error') {
      if ($result['error_type'] === 'invalid_user') {
        return response()->json($result, 404);
      }
    }

    return response()->json($result, 500);
  }


  /**
   * パスワードリセット処理
   */
  public function resetPassword(ResetPasswordRequest $request)
  {
    Log::info('【コントローラー】パスワードリセットの試行を開始しました', [
      'email' => $request->input('email'),
      'ip'    => $request->ip(),
    ]);

    $data = $request->only('email', 'password', 'password_confirmation', 'token');

    // サービス層に処理を委譲
    $result = $this->authService->resetPassword($data);

    if ($result['status'] === 'success') {
      return response()->json($result, 200);
    }

    return response()->json($result, 500);
  }

  /**
   * 現在のログインユーザー情報を取得
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getCurrentUser(Request $request)
  {
    $user = $request->user();

    // 必要な情報のみを返す
    return response()->json([
      'id' => $user->id,
      'name' => $user->name,
      'email' => $user->email,
      'friend_id' => $user->friend_id,
      'google_id' => $user->google_id,
      'avatar' => $user->avatar,
      'social_type' => $user->social_type,
    ]);
  }

  /**
   * ユーザー名を更新
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updateName(Request $request)
  {
    $user = $request->user();

    $validator = Validator::make($request->all(), [
      'name' => 'required|string|max:10',
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $newName = $request->input('name');

    try {
      $user->name = $newName;
      $user->save();

      Log::info('ユーザー名が更新されました', ['user_id' => $user->id, 'new_name' => $newName]);

      return response()->json([
        'message' => 'ユーザー名が正常に更新されました。',
        'user' => [
          'id' => $user->id,
          'friend_id' => $user->friend_id,
          'name' => $user->name,
          'email' => $user->email,
          'created_at' => $user->created_at,
        ]
      ], 200);
    } catch (\Exception $e) {
      Log::error('ユーザー名の更新中にエラーが発生しました', [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
      ]);
      return response()->json(['message' => 'ユーザー名の更新中にエラーが発生しました。'], 500);
    }
  }

  /**
   * メールアドレス変更リクエスト
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function requestEmailChange(Request $request)
  {
    $user = $request->user();

    // Google認証ユーザーはメールアドレス変更不可
    if ($user->social_type === 'google') {
      Log::warning('Google認証ユーザーによるメールアドレス変更の試行', [
        'user_id' => $user->id,
        'email' => $user->email
      ]);
      return response()->json([
        'status' => 'error',
        'message' => 'Google認証ユーザーはメールアドレスを変更できません。Googleアカウント設定から変更してください。'
      ], 403);
    }

    $validator = Validator::make($request->all(), [
      'email' => 'required|email|unique:users,email',
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    $newEmail = $request->input('email');
    $user = $request->user();
    $ip = $request->ip();

    Log::info('メールアドレス変更リクエストを受信', [
      'user_id' => $user->id,
      'current_email' => $user->email,
      'new_email' => $newEmail,
      'ip' => $ip
    ]);

    try {
      $result = $this->authService->requestEmailChange($user, $newEmail, $ip);

      if ($result['status'] === 'success') {
        return response()->json($result, 200);
      } else {
        return response()->json($result, 400);
      }
    } catch (\Exception $e) {
      Log::error('メールアドレスの更新処理中にエラーが発生しました', [
        'user_id' => $user->id,
        'error' => $e->getMessage()
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'メールアドレスの更新処理中にエラーが発生しました'
      ], 500);
    }
  }

  /**
   * メールアドレス変更を確認
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function confirmEmailChange(Request $request)
  {
    $token = $request->input('token');
    $ip = $request->ip();

    Log::info('メールアドレス変更確認リクエストを受信', [
      'token' => $token,
      'ip' => $ip
    ]);

    try {
      $result = $this->authService->confirmEmailChange($token, $ip);

      if ($result['status'] === 'success') {
        return response()->json($result, 200);
      } else {
        return response()->json($result, 400);
      }
    } catch (\Exception $e) {
      Log::error('メールアドレス変更確認処理中にエラーが発生しました', [
        'error' => $e->getMessage()
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'メールアドレス変更確認処理中にエラーが発生しました'
      ], 500);
    }
  }

  /**
   * パスワードを更新
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updatePassword(Request $request)
  {
    $user = $request->user();

    // Google認証ユーザーはパスワード変更不可
    if ($user->social_type === 'google') {
      Log::warning('Google認証ユーザーによるパスワード変更の試行', [
        'user_id' => $user->id,
        'email' => $user->email
      ]);
      return response()->json([
        'status' => 'error',
        'message' => 'Google認証ユーザーはパスワードを変更できません。Googleアカウントで安全に認証されています。'
      ], 403);
    }

    $validator = Validator::make($request->all(), [
      'current_password' => 'required|string',
      'password' => 'required|string|min:8|confirmed',
    ]);

    if ($validator->fails()) {
      Log::warning('パスワード更新のバリデーションエラー', [
        'user_id' => $user->id,
        'errors' => $validator->errors()->toArray()
      ]);
      return response()->json(['errors' => $validator->errors()], 422);
    }

    // 現在のパスワードが正しいか確認
    if (!Hash::check($request->current_password, $user->password)) {
      Log::warning('パスワード更新失敗: 現在のパスワードが不正確', ['user_id' => $user->id]);
      return response()->json(['errors' => ['current_password' => ['現在のパスワードが正しくありません。']]], 422);
    }

    try {
      $user->password = Hash::make($request->password);
      $user->save();

      // 必要であれば、他のデバイスのセッションを無効化するなどの処理を追加
      // $user->tokens()->delete(); // 全てのトークンを削除 (現在のセッションも含む)
      // Auth::logoutOtherDevices($request->password); // 他のデバイスのみログアウト

      Log::info('パスワードが更新されました', ['user_id' => $user->id]);

      return response()->json(['message' => 'パスワードが正常に更新されました。'], 200);
    } catch (\Exception $e) {
      Log::error('パスワードの更新中にエラーが発生しました', [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
      ]);
      return response()->json(['message' => 'パスワードの更新中にエラーが発生しました。'], 500);
    }
  }

  /**
   * 確認メールを再送信
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function resendVerificationEmail(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'email' => 'required|email|exists:users,email',
    ]);

    if ($validator->fails()) {
      return response()->json([
        'status' => 'error',
        'message' => '有効なメールアドレスを入力してください。',
        'errors' => $validator->errors()
      ], 422);
    }

    $email = $request->input('email');
    $ip = $request->ip();

    Log::info('確認メール再送信の試行を開始しました', [
      'email' => $email,
      'ip' => $ip,
    ]);

    // ユーザーを検索
    $user = User::where('email', $email)->first();

    if (!$user) {
      Log::warning('確認メール再送信: ユーザーが見つかりません', ['email' => $email]);
      return response()->json([
        'status' => 'error',
        'message' => 'ユーザーが見つかりません。'
      ], 404);
    }

    // 既に認証済みの場合
    if ($user->is_verified) {
      Log::info('確認メール再送信: 既に認証済み', ['email' => $email]);
      return response()->json([
        'status' => 'error',
        'message' => 'このアカウントは既に認証済みです。'
      ], 422);
    }

    try {
      // 新しい認証トークンを生成
      $user->updateProvisionalRegistration([
        'password' => $user->password, // 既存のパスワードを保持
        'name' => $user->name, // 既存の名前を保持
      ]);

      // 確認メールを再送信
      Mail::to($user->email)->queue(new PreRegistrationEmail($user));

      Log::info('確認メール再送信が完了しました', ['email' => $email]);

      return response()->json([
        'status' => 'success',
        'message' => '確認メールを再送信しました。メール内のリンクをクリックして認証を完了してください。'
      ], 200);
    } catch (\Exception $e) {
      Log::error('確認メール再送信中にエラーが発生しました', [
        'email' => $email,
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'status' => 'error',
        'message' => '確認メールの送信中にエラーが発生しました。しばらくしてから再度お試しください。'
      ], 500);
    }
  }
}
