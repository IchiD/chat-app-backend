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
use Illuminate\Support\Facades\DB;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/resend-verification', [AuthController::class, 'resendVerificationEmail']);
Route::get('/verify', [AuthController::class, 'verifyEmail']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
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

  // 名前変更提案に対する応答
  Route::post('/user/handle-name-suggestion', [AuthController::class, 'handleNameChangeSuggestion']);

  // パスワードを更新
  Route::put('/user/update-password', [AuthController::class, 'updatePassword']);

  // アカウント削除
  Route::delete('/user/delete-account', [AuthController::class, 'deleteAccount']);

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
    Route::post('/room/{chatRoom}/read', 'markAsReadByRoomId'); // チャットルームIDでの既読処理

    // グループ管理
    Route::prefix('groups')->group(function () {
      Route::get('/', 'getGroups'); // グループ一覧
      Route::post('/', 'createGroup'); // グループ作成
      Route::get('/{group}', 'showGroup'); // グループ詳細
      Route::put('/{group}', 'updateGroup'); // グループ更新
      Route::delete('/{group}', 'destroyGroup'); // グループ削除
      Route::post('/{group}/members', 'addGroupMember'); // メンバー追加
      Route::delete('/{group}/members/{participant}', 'removeGroupMember'); // メンバー削除
      Route::get('/{group}/qr-code', 'getGroupQrCode'); // QRコード取得
      Route::post('/{group}/qr-code/regenerate', 'regenerateGroupQrCode'); // QRコード再生成

      // グループメンバー間チャット機能
      Route::get('/{group}/members', 'getGroupMembers'); // グループメンバー一覧
      Route::post('/{group}/member-chat', 'getOrCreateMemberChat'); // メンバー間チャット取得/作成
      Route::post('/{group}/messages/bulk', 'sendBulkMessageToMembers'); // メンバーに一斉送信
    });

    // 特定のチャットルームのメッセージ関連 (room_token を使用)
    Route::prefix('room/{chatRoom:room_token}/messages')->controller(MessagesController::class)->group(function () {
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

// テスト用の一時的なエンドポイント（本実装後は削除すること）
Route::get('/test/friend-chat-separation', function () {
  try {
    // 現在のChatRoomデータを確認
    $chatRooms = App\Models\ChatRoom::with(['participant1:id,name,friend_id', 'participant2:id,name,friend_id'])
      ->orderBy('type')
      ->get()
      ->map(function ($room) {
        return [
          'id' => $room->id,
          'type' => $room->type,
          'participant1' => $room->participant1 ? [
            'id' => $room->participant1->id,
            'name' => $room->participant1->name,
            'friend_id' => $room->participant1->friend_id,
          ] : null,
          'participant2' => $room->participant2 ? [
            'id' => $room->participant2->id,
            'name' => $room->participant2->name,
            'friend_id' => $room->participant2->friend_id,
          ] : null,
          'group_id' => $room->group_id,
        ];
      });

    // 友達関係を確認
    $friendships = App\Models\Friendship::with(['user:id,name,friend_id', 'friend:id,name,friend_id'])
      ->where('status', 1)
      ->get()
      ->map(function ($friendship) {
        return [
          'id' => $friendship->id,
          'user' => [
            'id' => $friendship->user->id,
            'name' => $friendship->user->name,
            'friend_id' => $friendship->user->friend_id,
          ],
          'friend' => [
            'id' => $friendship->friend->id,
            'name' => $friendship->friend->name,
            'friend_id' => $friendship->friend->friend_id,
          ],
          'status' => $friendship->status,
        ];
      });

    return response()->json([
      'chat_rooms' => $chatRooms,
      'friendships' => $friendships,
    ]);
  } catch (\Exception $e) {
    return response()->json([
      'error' => $e->getMessage(),
      'trace' => $e->getTraceAsString(),
    ], 500);
  }
});

// テスト用：friend_chatを手動作成
Route::post('/test/create-friend-chat', function (Illuminate\Http\Request $request) {
  try {
    $request->validate([
      'user_id1' => 'required|integer',
      'user_id2' => 'required|integer',
    ]);

    $userId1 = $request->user_id1;
    $userId2 = $request->user_id2;

    // 既存のfriend_chatをチェック
    $existingFriendChat = App\Models\ChatRoom::where('type', 'friend_chat')
      ->where(function ($query) use ($userId1, $userId2) {
        $query->where(function ($q) use ($userId1, $userId2) {
          $q->where('participant1_id', $userId1)
            ->where('participant2_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
          $q->where('participant1_id', $userId2)
            ->where('participant2_id', $userId1);
        });
      })
      ->first();

    if ($existingFriendChat) {
      return response()->json([
        'message' => 'friend_chatは既に存在します',
        'chat_room' => $existingFriendChat,
      ]);
    }

    // friend_chatを作成
    $newChatRoom = null;
    DB::transaction(function () use ($userId1, $userId2, &$newChatRoom) {
      $newChatRoom = App\Models\ChatRoom::create([
        'type' => 'friend_chat',
        'group_id' => null,
        'participant1_id' => $userId1,
        'participant2_id' => $userId2,
      ]);

      // 新アーキテクチャでは、participant1_idとparticipant2_idで参加者を管理するため
      // 別途Participantテーブルへの登録は不要
    });

    return response()->json([
      'message' => 'friend_chatを作成しました',
      'chat_room' => $newChatRoom,
    ]);
  } catch (\Exception $e) {
    return response()->json([
      'error' => $e->getMessage(),
    ], 500);
  }
});
