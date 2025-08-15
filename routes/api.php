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
Route::post('/login', [AuthController::class, 'login'])->name('login');

// 管理画面セッションタイムアウト対応：GET /api/login は管理画面ログインにリダイレクト
Route::get('/login', function () {
  return redirect()->route('admin.login');
});
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
Route::get('/pricing', [AppConfigController::class, 'getPricing']);

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

  // 名前変更提案に対する応答
  Route::post('/user/handle-name-suggestion', [AuthController::class, 'handleNameChangeSuggestion']);

  // パスワードを更新
  Route::put('/user/update-password', [AuthController::class, 'updatePassword']);

  // アカウント削除
  Route::delete('/user/delete-account', [AuthController::class, 'deleteAccount']);

  // 友達関係のAPI
  Route::prefix('friends')->group(function () {
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

  // チャット機能のAPI (チャットとメッセージ)
  Route::prefix('conversations')->controller(ConversationsController::class)->group(function () {
    Route::get('/', 'index'); // ユーザーのチャット一覧
    Route::post('/', 'store'); // 新規チャット開始
    Route::get('/token/{room_token}', 'showByToken'); // トークンで特定のチャット情報を取得
    Route::post('/room/{chatRoom}/read', 'markAsReadByRoomId'); // チャットルームIDでの既読処理

    // グループ管理
    Route::prefix('groups')->group(function () {
      Route::get('/', 'getGroups'); // グループ一覧
      Route::post('/', 'createGroup'); // グループ作成
      Route::get('/{group}', 'showGroup'); // グループ詳細
      Route::put('/{group}', 'updateGroup'); // グループ更新
      Route::delete('/{group}', 'destroyGroup'); // グループ削除
      Route::post('/{group}/members', 'addGroupMember'); // メンバー追加
      Route::delete('/{group}/members/{groupMember}', 'removeGroupMember'); // メンバー削除
      Route::get('/{group}/qr-code', 'getGroupQrCode'); // QRコード取得
      Route::post('/{group}/qr-code/regenerate', 'regenerateGroupQrCode'); // QRコード再生成

      // グループメンバー間チャット機能
      Route::get('/{group}/members', 'getGroupMembers'); // グループメンバー一覧
      Route::get('/{group}/members/all', 'getAllGroupMembers'); // 全メンバー一覧（削除済み含む）- オーナー専用
      Route::post('/{group}/member-chat', 'getOrCreateMemberChat'); // メンバー間チャット取得/作成
      Route::post('/{group}/messages/bulk', 'sendBulkMessageToMembers'); // メンバーに一斉送信

      // メンバー管理
      Route::patch('/{group}/members/{groupMember}/rejoin', 'toggleMemberRejoin'); // 再参加可否切り替え
      Route::post('/{group}/members/{groupMember}/restore', 'restoreGroupMember'); // メンバー復活
      Route::patch('/{group}/members/{groupMember}/nickname', 'updateMemberNickname'); // ニックネーム更新
    });

    // 特定のチャットルームのメッセージ関連 (room_token を使用)
    Route::prefix('room/{chatRoom:room_token}/messages')->controller(MessagesController::class)->group(function () {
      Route::get('/', 'index'); // メッセージ一覧
      Route::post('/', 'store')->middleware('throttle:10,1'); // メッセージ送信
      Route::get('/read-status', 'getReadStatus'); // 既読状態取得
    });
  });

  // プッシュ通知関連のAPI
  Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
    Route::post('/subscribe', 'subscribe'); // プッシュ通知の購読登録
    Route::post('/unsubscribe', 'unsubscribe'); // プッシュ通知の購読解除
    Route::get('/preferences', 'getPreferences'); // 通知設定取得
    Route::put('/preferences', 'updatePreferences'); // 通知設定更新
  });



  // Stripe 決済関連
  Route::post('/stripe/create-checkout-session', [StripeController::class, 'createCheckoutSession']);
  Route::get('/stripe/subscription', [StripeController::class, 'getSubscriptionDetails']);
  Route::post('/stripe/subscription/cancel', [StripeController::class, 'cancelSubscription']);
  Route::post('/stripe/subscription/resume', [StripeController::class, 'resumeSubscription']);
  Route::get('/stripe/subscription/history', [StripeController::class, 'getSubscriptionHistory']);
  Route::post('/stripe/customer-portal', [StripeController::class, 'createCustomerPortalSession']);


  // メールアドレス変更関連
  Route::put('/user/update-email', [AuthController::class, 'requestEmailChange']);

  // お問い合わせ機能
  Route::prefix('support')->group(function () {
    Route::post('/conversation', [ConversationsController::class, 'createSupportConversation']); // サポートチャットの作成
    Route::get('/conversation', [ConversationsController::class, 'getSupportConversation']); // サポートチャットの取得
  });
});

// メールアドレス変更確認（認証不要）
Route::get('/verify-email-change', [AuthController::class, 'confirmEmailChange']);

// Stripe Webhook (認証不要)
Route::post('/stripe/webhook', [StripeController::class, 'webhook']);

// QRコード参加 (認証必須)
Route::post('/conversations/groups/join/{token}', [ConversationsController::class, 'joinGroupByToken'])->middleware(['auth:sanctum', 'check.user.status']);

// グループ情報取得 (認証不要)
Route::get('/conversations/groups/info/{token}', [ConversationsController::class, 'getGroupInfoByToken']);


