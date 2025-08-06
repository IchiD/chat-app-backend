<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Notifications\PushNotification;
use NotificationChannels\WebPush\PushSubscription;

class NotificationController extends Controller
{
  /**
   * ユーザーのプッシュ通知購読情報を保存
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function subscribe(Request $request): JsonResponse
  {
    $request->validate([
      'subscription' => 'required|array',
      'subscription.endpoint' => 'required|string',
      'subscription.keys' => 'required|array',
      'subscription.keys.p256dh' => 'required|string',
      'subscription.keys.auth' => 'required|string',
    ]);

    $user = Auth::user();

    // 既存の購読を探す
    $subscription = PushSubscription::findByEndpoint(
      $request->input('subscription.endpoint')
    );

    // 既存の購読がある場合は更新、なければ新規追加
    if ($subscription) {
      $subscription->public_key = $request->input('subscription.keys.p256dh');
      $subscription->auth_token = $request->input('subscription.keys.auth');
      $subscription->save();
    } else {
      $user->updatePushSubscription(
        $request->input('subscription.endpoint'),
        $request->input('subscription.keys.p256dh'),
        $request->input('subscription.keys.auth')
      );
    }

    return response()->json([
      'success' => true,
      'message' => 'プッシュ通知の購読が登録されました'
    ]);
  }

  /**
   * プッシュ通知の購読を解除
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function unsubscribe(Request $request): JsonResponse
  {
    $request->validate([
      'endpoint' => 'required|string',
    ]);

    $deleted = PushSubscription::findByEndpoint(
      $request->input('endpoint')
    )?->delete();

    return response()->json([
      'success' => true,
      'message' => 'プッシュ通知の購読が解除されました'
    ]);
  }

  /**
   * テスト通知を送信 (開発用)
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function sendTestNotification(): JsonResponse
  {
    $user = Auth::user();
    $frontendUrl = config('app.frontend_url', 'https://chat-app-frontend-sigma-puce.vercel.app');

    // テスト通知の作成と送信
    $notification = new PushNotification(
      'テスト通知',
      'これはテスト通知です。',
      [
        'url' => $frontendUrl . '/chat',
        'timestamp' => now()->timestamp
      ],
      [
        'tag' => 'test-notification',
        'requireInteraction' => true
      ]
    );

    $user->notify($notification);

    return response()->json([
      'success' => true,
      'message' => 'テスト通知が送信されました'
    ]);
  }

  /**
   * 新しいメッセージの通知を送信
   *
   * @param User $recipient 受信者
   * @param string $senderName 送信者名
   * @param string $messagePreview メッセージプレビュー
   * @param int $roomId チャットルームID
   * @param string $roomToken チャットルームトークン
   * @return void
   */
  public function sendNewMessageNotification(User $recipient, string $senderName, string $messagePreview, int $roomId, string $roomToken): void
  {
    $frontendUrl = config('app.frontend_url', 'https://chat-app-frontend-sigma-puce.vercel.app');

    $notification = new PushNotification(
      $senderName . 'からのメッセージ',
      $messagePreview,
      [
        'url' => $frontendUrl . '/chat',
        'type' => 'new_message',
        'room_id' => $roomId,
        'room_token' => $roomToken,
        'timestamp' => now()->timestamp
      ],
      [
        'tag' => 'chat-' . $roomId,
        'requireInteraction' => true
      ]
    );

    $recipient->notify($notification);
  }

  /**
   * フレンド申請の通知を送信
   *
   * @param User $recipient 受信者
   * @param string $senderName 送信者名
   * @return void
   */
  public function sendFriendRequestNotification(User $recipient, string $senderName): void
  {
    $frontendUrl = config('app.frontend_url', 'https://chat-app-frontend-sigma-puce.vercel.app');

    $notification = new PushNotification(
      'フレンド申請',
      $senderName . 'さんからフレンド申請が届きました',
      [
        'url' => $frontendUrl . '/friends',
        'type' => 'friend_request',
        'timestamp' => now()->timestamp
      ],
      [
        'tag' => 'friend-request',
        'requireInteraction' => true
      ]
    );

    $recipient->notify($notification);
  }

  /**
   * 通知設定を取得
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPreferences(): JsonResponse
  {
    $user = Auth::user();
    
    // デフォルト設定
    $defaultPreferences = [
      'email' => [
        'messages' => true,
        'friend_requests' => true,
        'group_invites' => true,
        'group_messages' => true,
      ],
      'push' => [
        'messages' => true,
        'friend_requests' => true,
        'group_invites' => true,
        'group_messages' => true,
      ],
    ];
    
    // ユーザーの設定が存在しない場合はデフォルトを返す
    $preferences = $user->notification_preferences ?? $defaultPreferences;
    
    return response()->json([
      'success' => true,
      'preferences' => $preferences,
    ]);
  }

  /**
   * 通知設定を更新
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function updatePreferences(Request $request): JsonResponse
  {
    $request->validate([
      'preferences' => 'required|array',
      'preferences.email' => 'required|array',
      'preferences.email.messages' => 'required|boolean',
      'preferences.email.friend_requests' => 'required|boolean',
      'preferences.email.group_invites' => 'required|boolean',
      'preferences.email.group_messages' => 'required|boolean',
      'preferences.push' => 'required|array',
      'preferences.push.messages' => 'required|boolean',
      'preferences.push.friend_requests' => 'required|boolean',
      'preferences.push.group_invites' => 'required|boolean',
      'preferences.push.group_messages' => 'required|boolean',
    ]);
    
    $user = Auth::user();
    $user->notification_preferences = $request->input('preferences');
    $user->save();
    
    return response()->json([
      'success' => true,
      'message' => '通知設定を更新しました',
      'preferences' => $user->notification_preferences,
    ]);
  }
}
