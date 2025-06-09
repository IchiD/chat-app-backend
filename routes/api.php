<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\API\FriendshipController;
use App\Http\Controllers\API\ConversationsController;
use App\Http\Controllers\API\MessagesController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\AppConfigController;
use App\Http\Controllers\API\ExternalResourceController;
use App\Http\Controllers\API\StripeController;
use App\Http\Controllers\API\ExternalAuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/resend-verification', [AuthController::class, 'resendVerificationEmail']);
Route::get('/verify', [AuthController::class, 'verifyEmail']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'check.user.status']);
Route::post('/password/email', [AuthController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');

// Google認証ルート（セッションミドルウェアを追加）
Route::middleware(['web'])->group(function () {
  Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
  Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
});

// アプリケーション設定情報取得
Route::get('/config', [AppConfigController::class, 'getPublicConfig']);
Route::post('/external/fetch', [ExternalResourceController::class, 'fetch']);

// 外部システム向け認証
Route::prefix('auth/external')->controller(ExternalAuthController::class)->group(function () {
  Route::post('/token', 'issueToken')->middleware('throttle:10,1');
  Route::post('/verify', 'verifyToken')->middleware('throttle:30,1');
});

// 認証済みユーザーのみアクセス可能なエンドポイント
Route::middleware(['auth:sanctum', 'check.user.status'])->group(function () {
  // 現在のユーザー情報を取得
  Route::get('/users/me', [AuthController::class, 'getCurrentUser']);
  // ユーザー名を更新
  Route::put('/user/update-name', [AuthController::class, 'updateName']);

  // パスワードを更新
  Route::put('/user/update-password', [AuthController::class, 'updatePassword']);

  // 友達関係のAPI
  Route::prefix('friends')->group(function () {
    // デバッグテスト用エンドポイント
    Route::get('/debug-test', [FriendshipController::class, 'testDebug']);

    // 友達一覧
    Route::get('/', [FriendshipController::class, 'getFriends']);

    // 友達申請の一覧
    Route::get('/requests/sent', [FriendshipController::class, 'getSentRequests']);
    Route::get('/requests/received', [FriendshipController::class, 'getReceivedRequests']);

    // フレンドIDでユーザーを検索
    Route::post('/search', [FriendshipController::class, 'searchByFriendId']);

    // 友達申請の送信
    Route::post('/requests', [FriendshipController::class, 'sendRequest']);

    // 友達申請の承認/拒否
    Route::post('requests/accept', [FriendshipController::class, 'acceptRequest']);
    Route::post('requests/reject', [FriendshipController::class, 'rejectRequest']);

    // 友達削除
    Route::delete('/unfriend', [FriendshipController::class, 'unfriend']);
    // 送信済み申請の取り消し (FriendshipController にメソッド追加が必要)
    Route::delete('/requests/cancel/{requestId}', [FriendshipController::class, 'cancelSentRequest']);
  });

  // チャット機能のAPI (会話とメッセージ)
  Route::prefix('conversations')->controller(ConversationsController::class)->group(function () {
    Route::get('/', 'index'); // ユーザーの会話一覧
    Route::post('/', 'store'); // 新規会話開始
    Route::get('/token/{room_token}', 'showByToken'); // トークンで特定の会話情報を取得
    Route::post('/{conversation}/read', 'markAsRead'); // idでの既読処理も残すか検討

    // グループ管理
    Route::prefix('groups')->group(function () {
      Route::get('/', 'getGroups'); // グループ一覧
      Route::post('/', 'createGroup'); // グループ作成
      Route::get('/{conversation}', 'showGroup'); // グループ詳細
      Route::put('/{conversation}', 'updateGroup'); // グループ更新
      Route::delete('/{conversation}', 'destroyGroup'); // グループ削除
      Route::post('/{conversation}/members', 'addGroupMember'); // メンバー追加
      Route::delete('/{conversation}/members/{participant}', 'removeGroupMember'); // メンバー削除
      Route::get('/{conversation}/qr-code', 'getGroupQrCode'); // QRコード取得
      Route::post('/{conversation}/qr-code/regenerate', 'regenerateGroupQrCode'); // QRコード再生成

      // グループメンバー間チャット機能
      Route::get('/{conversation}/members', 'getGroupMembers'); // グループメンバー一覧
      Route::post('/{conversation}/member-chat', 'getOrCreateMemberChat'); // メンバー間チャット取得/作成
      Route::post('/{conversation}/messages/bulk', 'sendBulkMessageToMembers'); // メンバーに一斉送信
    });

    // 特定の会話のメッセージ関連 (room_token を使用)
    Route::prefix('room/{conversation:room_token}/messages')->controller(MessagesController::class)->group(function () {
      Route::get('/', 'index'); // メッセージ一覧
      Route::post('/', 'store')->middleware('throttle:10,1'); // メッセージ送信
    });
  });

  // プッシュ通知関連のAPI
  Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
    Route::post('/subscribe', 'subscribe'); // プッシュ通知の購読登録
    Route::post('/unsubscribe', 'unsubscribe'); // プッシュ通知の購読解除
    Route::post('/test', 'sendTestNotification'); // テスト通知の送信（開発用）
  });



  // Stripe 決済関連
  Route::post('/stripe/create-checkout-session', [StripeController::class, 'createCheckoutSession']);

  // メールアドレス変更関連
  Route::put('/user/update-email', [AuthController::class, 'requestEmailChange']);

  // お問い合わせ機能
  Route::prefix('support')->group(function () {
    Route::post('/conversation', [ConversationsController::class, 'createSupportConversation']); // サポート会話の作成
    Route::get('/conversation', [ConversationsController::class, 'getSupportConversation']); // サポート会話の取得
  });
});

// メールアドレス変更確認（認証不要）
Route::get('/verify-email-change', [AuthController::class, 'confirmEmailChange']);

// Stripe Webhook (認証不要)
Route::post('/stripe/webhook', [StripeController::class, 'webhook']);

// QRコード参加 (認証必須)
Route::post('/conversations/groups/join/{token}', [ConversationsController::class, 'joinGroupByToken'])->middleware(['auth:sanctum', 'check.user.status']);

// 既存のユーザー情報取得エンドポイント
Route::middleware(['auth:sanctum', 'check.user.status'])->get('/user', function (Request $request) {
  return $request->user();
});
