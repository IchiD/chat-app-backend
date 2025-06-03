<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\JsonResponse;

class FriendshipController extends Controller
{
  /**
   * 認証されたユーザーを取得し、認証チェックを行う
   * ミドルウェアに加えて二重のセキュリティレイヤーとして機能
   *
   * @return User|JsonResponse ユーザーオブジェクトまたはエラーレスポンス
   */
  private function getAuthenticatedUser()
  {
    $user = Auth::user();

    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => '認証が必要です。再ログインしてください。'
      ], 401);
    }

    return $user;
  }

  /**
   * 友達一覧を取得
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getFriends()
  {
    // 認証チェック - ミドルウェアのバックアップとして機能
    $user = $this->getAuthenticatedUser();
    if ($user instanceof JsonResponse) {
      return $user; // 認証エラーレスポンスを返す
    }

    $friends = $user->friends()->map(function ($friend) {
      return [
        'id' => $friend->id,
        'name' => $friend->name,
        'friend_id' => $friend->friend_id,
        'email' => $friend->email,
        'created_at' => $friend->created_at,
      ];
    })->toArray();

    return response()->json([
      'status' => 'success',
      'friends' => $friends
    ]);
  }

  /**
   * 自分が送信した友達申請の一覧を取得
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getSentRequests()
  {
    // 認証チェック
    $user = $this->getAuthenticatedUser();
    if ($user instanceof JsonResponse) {
      return $user;
    }

    $requests = $user->pendingFriendRequests()->map(function ($request) {
      return [
        'id' => $request->id,
        'user' => [
          'id' => $request->user->id,
          'name' => $request->user->name,
          'friend_id' => $request->user->friend_id,
          'email' => $request->user->email,
        ],
        'friend' => [
          'id' => $request->friend->id,
          'name' => $request->friend->name,
          'friend_id' => $request->friend->friend_id,
          'email' => $request->friend->email,
        ],
        'message' => $request->message,
        'created_at' => $request->created_at,
        'status' => $request->status,
      ];
    })->toArray();

    return response()->json([
      'status' => 'success',
      'sent_requests' => $requests
    ]);
  }

  /**
   * 自分が受け取った友達申請の一覧を取得
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getReceivedRequests()
  {
    // 認証チェック
    $user = $this->getAuthenticatedUser();
    if ($user instanceof JsonResponse) {
      return $user;
    }

    $requests = $user->friendRequests()->map(function ($request) {
      return [
        'id' => $request->id,
        'user' => [
          'id' => $request->user->id,
          'name' => $request->user->name,
          'friend_id' => $request->user->friend_id,
          'email' => $request->user->email,
        ],
        'friend' => [
          'id' => $request->friend->id,
          'name' => $request->friend->name,
          'friend_id' => $request->friend->friend_id,
          'email' => $request->friend->email,
        ],
        'message' => $request->message,
        'created_at' => $request->created_at,
        'status' => $request->status,
      ];
    })->toArray();

    return response()->json([
      'status' => 'success',
      'received_requests' => $requests
    ]);
  }

  /**
   * フレンドIDでユーザーを検索
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function searchByFriendId(Request $request)
  {
    // 認証チェック
    $currentUser = $this->getAuthenticatedUser();
    if ($currentUser instanceof JsonResponse) {
      return $currentUser;
    }

    $validator = Validator::make($request->all(), [
      'friend_id' => 'required|string|size:6'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'status' => 'error',
        'message' => '有効なフレンドIDを入力してください',
        'errors' => $validator->errors()
      ], 422);
    }

    $friendId = $request->input('friend_id');

    // 自分自身のフレンドIDでの検索を防止
    if ($currentUser->friend_id === $friendId) {
      return response()->json([
        'status' => 'error',
        'message' => '自分自身のフレンドIDは使用できません'
      ], 422);
    }

    // 削除・バンされていないユーザーのみを検索
    $user = User::where('friend_id', $friendId)
      ->whereNull('deleted_at')
      ->where('is_banned', false)
      ->first();

    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => '指定されたフレンドIDのユーザーが見つかりません'
      ], 404);
    }

    // 既存の友達関係をチェック
    $friendshipStatus = Friendship::getFriendshipStatus($currentUser->id, $user->id);

    return response()->json([
      'found' => true,
      'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'friend_id' => $user->friend_id,
      ],
      'message' => 'ユーザーが見つかりました。'
    ]);
  }

  /**
   * 友達申請を送信
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function sendRequest(Request $request)
  {
    try {
      Log::info('友達申請処理開始', ['request_data' => $request->all()]);

      // 認証チェック
      $currentUser = $this->getAuthenticatedUser();
      if ($currentUser instanceof JsonResponse) {
        Log::warning('認証チェック失敗');
        return $currentUser;
      }

      Log::info('認証チェック成功', ['user_id' => $currentUser->id]);

      $validator = Validator::make($request->all(), [
        'user_id' => 'required|integer|exists:users,id',
        'message' => 'nullable|string|max:255'
      ]);

      if ($validator->fails()) {
        Log::warning('バリデーション失敗', ['errors' => $validator->errors()]);
        return response()->json([
          'status' => 'error',
          'message' => '入力内容に問題があります',
          'errors' => $validator->errors()
        ], 422);
      }

      $friendId = $request->input('user_id');
      $message = $request->input('message');

      Log::info('バリデーション成功', ['friend_id' => $friendId, 'message' => $message]);

      // 自分自身への申請を防止
      if ($currentUser->id === (int)$friendId) {
        Log::warning('自分自身への申請');
        return response()->json([
          'status' => 'error',
          'message' => '自分自身に友達申請はできません'
        ], 422);
      }

      // 送信先のユーザーが削除・バンされていないかチェック
      $friendUser = User::find($friendId);
      if (!$friendUser || $friendUser->isDeleted() || $friendUser->isBanned()) {
        Log::warning('送信先ユーザーが利用不可', ['friend_id' => $friendId]);
        return response()->json([
          'status' => 'error',
          'message' => '指定されたユーザーは現在利用できません'
        ], 422);
      }

      Log::info('送信先ユーザーチェック成功', ['friend_user_id' => $friendUser->id]);

      // 既存の友達関係をチェック
      $existingFriendship = Friendship::getFriendship($currentUser->id, $friendId);
      Log::info('既存の友達関係チェック', ['existing_friendship' => $existingFriendship ? $existingFriendship->id : null]);

      if ($existingFriendship) {
        if ($existingFriendship->status === Friendship::STATUS_ACCEPTED) {
          Log::warning('既に友達');
          return response()->json([
            'status' => 'error',
            'message' => 'すでに友達です'
          ], 422);
        } elseif ($existingFriendship->status === Friendship::STATUS_PENDING) {
          // 自分が送った申請の場合とそうでない場合で分ける
          if ($existingFriendship->user_id === $currentUser->id) {
            Log::warning('既に友達申請送信済み');
            return response()->json([
              'status' => 'error',
              'message' => '既に友達申請を送信済みです'
            ], 422);
          } else {
            // 相手から既に申請が来ている場合は自動的に承認する
            Log::info('相手からの申請を自動承認');
            $existingFriendship->status = Friendship::STATUS_ACCEPTED;
            $existingFriendship->save();

            return response()->json([
              'status' => 'success',
              'message' => '相手からの友達申請を承認しました',
              'friendship' => $existingFriendship
            ]);
          }
        } elseif ($existingFriendship->status === Friendship::STATUS_REJECTED) {
          // 拒否された申請は再度送信可能にする
          Log::info('拒否された申請を再送信');
          $existingFriendship->status = Friendship::STATUS_PENDING;
          $existingFriendship->message = $message;
          $existingFriendship->save();

          // 再申請の通知を送信
          if ($friendUser) {
            try {
              $notificationController = new NotificationController();
              $notificationController->sendFriendRequestNotification($friendUser, $currentUser->name);
            } catch (\Exception $e) {
              Log::warning('友達申請再送信通知の送信に失敗しました', [
                'friend_user_id' => $friendUser->id,
                'sender_user_id' => $currentUser->id,
                'error' => $e->getMessage()
              ]);
              // 通知エラーは無視して処理を続行
            }
          }

          return response()->json([
            'status' => 'success',
            'message' => '友達申請を送信しました',
            'friendship' => $existingFriendship
          ]);
        }
      }

      // 新しい友達申請を作成
      Log::info('新しい友達申請を作成開始');
      $friendship = $currentUser->sendFriendRequest($friendId, $message);
      Log::info('新しい友達申請を作成完了', ['friendship_id' => $friendship->id]);

      // 友達申請の通知を送信（エラーが発生しても友達申請自体は成功させる）
      if ($friendUser) {
        try {
          $notificationController = new NotificationController();
          $notificationController->sendFriendRequestNotification($friendUser, $currentUser->name);
          Log::info('友達申請通知送信成功');
        } catch (\Exception $e) {
          Log::warning('友達申請通知の送信に失敗しました', [
            'friend_user_id' => $friendUser->id,
            'sender_user_id' => $currentUser->id,
            'error' => $e->getMessage()
          ]);
          // 通知エラーは無視して処理を続行
        }
      }

      Log::info('友達申請処理完了');
      return response()->json([
        'status' => 'success',
        'message' => '友達申請を送信しました',
        'friendship' => $friendship
      ]);
    } catch (\Exception $e) {
      Log::error('友達申請処理でエラーが発生', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'request_data' => $request->all()
      ]);

      return response()->json([
        'status' => 'error',
        'message' => '友達申請の送信中にエラーが発生しました'
      ], 500);
    }
  }

  /**
   * 友達申請を承認
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function acceptRequest(Request $request)
  {
    // 認証チェック
    $currentUser = $this->getAuthenticatedUser();
    if ($currentUser instanceof JsonResponse) {
      return $currentUser;
    }

    $validator = Validator::make($request->all(), [
      'user_id' => 'required|integer|exists:users,id'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'status' => 'error',
        'message' => '入力内容に問題があります',
        'errors' => $validator->errors()
      ], 422);
    }

    $userId = $request->input('user_id');

    $result = $currentUser->acceptFriendRequest($userId);

    if (!$result) {
      return response()->json([
        'status' => 'error',
        'message' => '友達申請が見つからないか、すでに承認/拒否されています'
      ], 404);
    }

    return response()->json([
      'status' => 'success',
      'message' => '友達申請を承認しました'
    ]);
  }

  /**
   * 友達申請を拒否
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function rejectRequest(Request $request)
  {
    // 認証チェック
    $currentUser = $this->getAuthenticatedUser();
    if ($currentUser instanceof JsonResponse) {
      return $currentUser;
    }

    $validator = Validator::make($request->all(), [
      'user_id' => 'required|integer|exists:users,id'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'status' => 'error',
        'message' => '入力内容に問題があります',
        'errors' => $validator->errors()
      ], 422);
    }

    $userId = $request->input('user_id');

    $result = $currentUser->rejectFriendRequest($userId);

    if (!$result) {
      return response()->json([
        'status' => 'error',
        'message' => '友達申請が見つからないか、すでに承認/拒否されています'
      ], 404);
    }

    return response()->json([
      'status' => 'success',
      'message' => '友達申請を拒否しました'
    ]);
  }

  /**
   * 友達関係を解除
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function unfriend(Request $request)
  {
    // 認証チェック
    $currentUser = $this->getAuthenticatedUser();
    if ($currentUser instanceof JsonResponse) {
      return $currentUser;
    }

    $validator = Validator::make($request->all(), [
      'user_id' => 'required|integer|exists:users,id'
    ]);

    if ($validator->fails()) {
      return response()->json([
        'status' => 'error',
        'message' => '入力内容に問題があります',
        'errors' => $validator->errors()
      ], 422);
    }

    $friendId = $request->input('user_id');

    $result = $currentUser->unfriend($friendId);

    if (!$result) {
      return response()->json([
        'status' => 'error',
        'message' => '友達関係が見つかりません'
      ], 404);
    }

    return response()->json([
      'status' => 'success',
      'message' => '友達を削除しました'
    ]);
  }

  /**
   * 送信済みの友達申請を取り消す
   *
   * @param int $requestId 友達申請ID
   * @return \Illuminate\Http\JsonResponse
   */
  public function cancelSentRequest($requestId)
  {
    // 認証チェック
    $currentUser = $this->getAuthenticatedUser();
    if ($currentUser instanceof JsonResponse) {
      return $currentUser;
    }

    // リクエストIDの基本的なバリデーション
    if (!is_numeric($requestId) || (int)$requestId <= 0) {
      return response()->json([
        'status' => 'error',
        'message' => '無効なリクエストIDです'
      ], 422);
    }

    // 指定されたIDの申請を検索（自分が送った申請に限定）
    $request = Friendship::where('id', $requestId)
      ->where('user_id', $currentUser->id)
      ->where('status', Friendship::STATUS_PENDING)
      ->first();

    if (!$request) {
      return response()->json([
        'status' => 'error',
        'message' => '取り消し可能な友達申請が見つかりません'
      ], 404);
    }

    // 申請を削除
    $request->delete();

    return response()->json([
      'status' => 'success',
      'message' => '友達申請を取り消しました'
    ]);
  }

  /**
   * デバッグ用のテストエンドポイント
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function testDebug(Request $request)
  {
    try {
      Log::info('=== DEBUG TEST START ===');

      // 認証チェック
      $currentUser = $this->getAuthenticatedUser();
      if ($currentUser instanceof JsonResponse) {
        Log::error('認証チェック失敗 in testDebug');
        return $currentUser;
      }

      Log::info('認証チェック成功', ['user_id' => $currentUser->id, 'user_name' => $currentUser->name]);

      // Friendshipクラスのテスト
      Log::info('Friendshipクラステスト');
      $testFriendship = new Friendship();
      Log::info('Friendship STATUS_PENDING: ' . Friendship::STATUS_PENDING);
      Log::info('Friendship STATUS_ACCEPTED: ' . Friendship::STATUS_ACCEPTED);
      Log::info('Friendship STATUS_REJECTED: ' . Friendship::STATUS_REJECTED);

      // データベース接続のテスト
      Log::info('データベーステスト');
      $userCount = User::count();
      Log::info('ユーザー数: ' . $userCount);

      // Friendshipテーブルのテスト
      $friendshipCount = Friendship::count();
      Log::info('Friendship数: ' . $friendshipCount);

      Log::info('=== DEBUG TEST END ===');

      return response()->json([
        'status' => 'success',
        'message' => 'デバッグテスト完了',
        'data' => [
          'user_id' => $currentUser->id,
          'user_name' => $currentUser->name,
          'user_count' => $userCount,
          'friendship_count' => $friendshipCount,
          'status_constants' => [
            'PENDING' => Friendship::STATUS_PENDING,
            'ACCEPTED' => Friendship::STATUS_ACCEPTED,
            'REJECTED' => Friendship::STATUS_REJECTED,
          ]
        ]
      ]);
    } catch (\Exception $e) {
      Log::error('=== DEBUG TEST ERROR ===', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'デバッグテスト中にエラーが発生しました: ' . $e->getMessage()
      ], 500);
    }
  }
}
