<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Illuminate\Support\Facades\Log;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\Admin;
use App\Models\ChatRoom;
use App\Models\Group;
use App\Models\Subscription;

class User extends Authenticatable
{
  /** @use HasFactory<\Database\Factories\UserFactory> */
  use HasFactory, Notifiable, HasApiTokens, HasPushSubscriptions, SoftDeletes;

  /**
   * The attributes that are mass assignable.
   *
   * @var list<string>
   */
  protected $fillable = [
    'name',
    'email',
    'original_email',
    'previous_name',
    'allow_re_registration',
    'deleted_by_self',
    'password',
    'is_verified',
    'email_verification_token',
    'token_expires_at',
    'email_verified_at',
    'last_login_at',
    'friend_id',
    'new_email',
    'email_change_token',
    'deleted_at',
    'deleted_reason',
    'deleted_by',
    'is_banned',
    'google_id',
    'avatar',
    'social_type',
    'plan',
    'subscription_status',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var list<string>
   */
  protected $hidden = [
    'password',
    'remember_token',
  ];

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'last_login_at' => 'datetime',
      'deleted_at' => 'datetime',
      'is_banned' => 'boolean',
      'allow_re_registration' => 'boolean',
      'deleted_by_self' => 'boolean',
      'password' => 'hashed',
    ];
  }

  /**
   * 6桁のランダムな英数字のコードを生成する
   * 
   * @return string
   */
  protected static function generateFriendId(): string
  {
    // 英数字の文字セット（紛らわしい文字は除外）
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $friendId = '';

    // 6桁のランダムコードを生成
    for ($i = 0; $i < 6; $i++) {
      $friendId .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $friendId;
  }

  protected static function booted()
  {
    static::creating(function ($user) {
      // email_verification_token が設定されていない場合、自動生成する
      if (empty($user->email_verification_token)) {
        $user->email_verification_token = Str::random(60);
      }
      // token_expires_at が設定されていない場合、自動で有効期限を設定する（1時間後）
      if (empty($user->token_expires_at)) {
        $user->token_expires_at = Carbon::now()->addHours(1);
      }

      // is_banned が設定されていない場合、デフォルトでfalseを設定
      if (is_null($user->is_banned)) {
        $user->is_banned = false;
      }

      // friend_id が設定されていない場合、自動生成する
      // 6桁のランダム文字列を使用
      if (empty($user->friend_id)) {
        // 衝突を避けるためにループでユニーク性を確保
        do {
          $friendId = static::generateFriendId();
        } while (static::where('friend_id', $friendId)->exists());

        $user->friend_id = $friendId;
      }
    });
  }

  /**
   * 仮登録ユーザーの再登録時に新しいパスワードや名前などの情報で上書きし、
   * 再度確認用のトークン・有効期限を設定する。
   *
   * @param array $attributes
   * @return bool
   */
  public function updateProvisionalRegistration(array $attributes): bool
  {
    // 再登録時は必ず新しい確認用トークンと有効期限を発行する
    $attributes['email_verification_token'] = Str::random(60);
    $attributes['token_expires_at'] = Carbon::now()->addHours(1);

    // previous_nameを保護（名前変更提案機能のため）
    unset($attributes['previous_name']);

    // 本登録済みの場合は更新しない
    if ($this->is_verified) {
      return false;
    }
    return $this->update($attributes);
  }

  public function sendPasswordResetNotification($token)
  {
    $this->notify(new \App\Notifications\CustomSendPasswordResetEmail($token));
  }

  /**
   * 自分が送った友達申請
   */
  public function sentFriendships(): HasMany
  {
    return $this->hasMany(Friendship::class, 'user_id');
  }

  /**
   * 自分が受け取った友達申請
   */
  public function receivedFriendships(): HasMany
  {
    return $this->hasMany(Friendship::class, 'friend_id');
  }

  /**
   * 承認済みの友達関係を全て取得（削除・バンされたユーザーは除外）
   * 
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function friends()
  {
    $sentFriendIds = $this->sentFriendships()
      ->where('status', Friendship::STATUS_ACCEPTED)
      ->pluck('friend_id')
      ->toArray();

    $receivedFriendIds = $this->receivedFriendships()
      ->where('status', Friendship::STATUS_ACCEPTED)
      ->pluck('user_id')
      ->toArray();

    $friendIds = array_merge($sentFriendIds, $receivedFriendIds);

    // 削除・バンされていないユーザーのみを取得
    return User::whereIn('id', $friendIds)
      ->whereNull('deleted_at')
      ->where('is_banned', false)
      ->get();
  }

  /**
   * 友達申請中の友達関係を取得（削除・バンされたユーザーは除外）
   * 
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function pendingFriendRequests()
  {
    return $this->sentFriendships()
      ->where('status', Friendship::STATUS_PENDING)
      ->with(['friend' => function ($query) {
        $query->whereNull('deleted_at')->where('is_banned', false);
      }])
      ->whereHas('friend', function ($query) {
        $query->whereNull('deleted_at')->where('is_banned', false);
      })
      ->get();
  }

  /**
   * 受け取った友達申請を取得（削除・バンされたユーザーからの申請は除外）
   * 
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function friendRequests()
  {
    return $this->receivedFriendships()
      ->where('status', Friendship::STATUS_PENDING)
      ->with(['user' => function ($query) {
        $query->whereNull('deleted_at')->where('is_banned', false);
      }])
      ->whereHas('user', function ($query) {
        $query->whereNull('deleted_at')->where('is_banned', false);
      })
      ->get();
  }

  /**
   * フレンドIDでユーザーを検索
   * 
   * @param string $friendId
   * @return User|null
   */
  public static function findByFriendId(string $friendId)
  {
    return static::where('friend_id', $friendId)->first();
  }

  /**
   * 友達申請を送信
   * 
   * @param int $friendId
   * @param string $message
   * @return Friendship
   */
  public function sendFriendRequest(int $friendId, string $message = null)
  {
    try {
      // 既存のアクティブな友達関係をチェック
      $existingFriendship = Friendship::getFriendship($this->id, $friendId);

      if ($existingFriendship) {
        return $existingFriendship;
      }

      // 論理削除された友達関係があるかチェック
      $deletedFriendship = Friendship::getFriendshipWithTrashed($this->id, $friendId);

      if ($deletedFriendship && $deletedFriendship->isDeleted()) {
        // 論理削除された関係を復活させる
        $deletedFriendship->restoreByAdmin();
        $deletedFriendship->status = Friendship::STATUS_PENDING;
        $deletedFriendship->message = $message;
        $deletedFriendship->save();

        Log::info('論理削除された友達関係を復活', [
          'user_id' => $this->id,
          'friend_id' => $friendId,
          'friendship_id' => $deletedFriendship->id
        ]);

        return $deletedFriendship;
      }

      // 新しい友達申請を作成
      $friendship = Friendship::create([
        'user_id' => $this->id,
        'friend_id' => $friendId,
        'status' => Friendship::STATUS_PENDING,
        'message' => $message,
      ]);

      return $friendship;
    } catch (\Exception $e) {
      Log::error('User::sendFriendRequestでエラー', [
        'user_id' => $this->id,
        'friend_id' => $friendId,
        'error' => $e->getMessage()
      ]);
      throw $e;
    }
  }

  /**
   * 友達申請を承認
   * 
   * @param int $userId
   * @return bool
   */
  public function acceptFriendRequest(int $userId): bool
  {
    $friendship = Friendship::where('user_id', $userId)
      ->where('friend_id', $this->id)
      ->where('status', Friendship::STATUS_PENDING)
      ->first();

    if (!$friendship) {
      return false;
    }

    $friendship->status = Friendship::STATUS_ACCEPTED;
    $saved = $friendship->save();

    if ($saved) {
      \App\Services\OperationLogService::log(
        'frontend',
        'friend_accept',
        'user:' . $this->id . ' friend:' . $userId
      );

      // 友達関係復活時でも、削除された友達チャットは復活させない
      // 必要に応じて新しいチャットが作成される
    }

    return $saved;
  }

  /**
   * 友達申請を拒否
   * 
   * @param int $userId
   * @return bool
   */
  public function rejectFriendRequest(int $userId): bool
  {
    $friendship = Friendship::where('user_id', $userId)
      ->where('friend_id', $this->id)
      ->where('status', Friendship::STATUS_PENDING)
      ->first();

    if (!$friendship) {
      return false;
    }

    $friendship->status = Friendship::STATUS_REJECTED;
    return $friendship->save();
  }

  /**
   * 友達関係を解除（論理削除に変更）
   * 
   * @param int $friendId
   * @return bool
   */
  public function unfriend(int $friendId): bool
  {
    $friendship = Friendship::getFriendship($this->id, $friendId);

    if (!$friendship) {
      return false;
    }

    // 友達関係の論理削除を実行
    $friendshipDeleted = $friendship->deleteByAdmin(null, 'ユーザーによる友達解除');

    // 対応する友達チャットも論理削除
    if ($friendshipDeleted) {
      $friendChat = ChatRoom::getFriendChat($this->id, $friendId);
      if ($friendChat && !$friendChat->trashed()) {
        $friendChat->deleteByFriendshipRemoval($this->id, '友達関係の解除に伴う削除');
      }
    }

    return $friendshipDeleted;
  }

  /**
   * ユーザーが参加しているチャットルームを取得（新アーキテクチャ）
   */
  public function getChatRooms()
  {
    return ChatRoom::where(function ($query) {
      $query->where('participant1_id', $this->id)
        ->orWhere('participant2_id', $this->id)
        ->orWhereHas('group.activeMembers', function ($q) {
          $q->where('user_id', $this->id);
        });
    })->get();
  }

  /**
   * ユーザーが参加しているチャットルーム（リレーション）
   * 複雑な条件のため、直接getChatRooms()メソッドを使用することを推奨
   */
  public function chatRooms()
  {
    // 複雑な条件があるため、getChatRooms()メソッドを使用
    return $this->getChatRooms();
  }

  /**
   * ユーザーが送信したメッセージを取得
   */
  public function messages(): HasMany
  {
    return $this->hasMany(Message::class, 'sender_id');
  }

  /**
   * 削除を実行した管理者を取得
   */
  public function deletedByAdmin(): BelongsTo
  {
    return $this->belongsTo(Admin::class, 'deleted_by');
  }

  /**
   * ユーザーが論理削除されているかチェック
   */
  public function isDeleted(): bool
  {
    return !is_null($this->deleted_at);
  }

  /**
   * ユーザーがバンされているかチェック
   */
  public function isBanned(): bool
  {
    return (bool) $this->is_banned;
  }

  /**
   * 管理者によるユーザー削除
   */
  public function deleteByAdmin(int $adminId, string $reason = null): bool
  {
    // 削除前の元のメールアドレスと名前を保存（復元時に使用）
    $originalEmail = $this->email;
    $originalName = $this->name;

    $updateData = [
      'deleted_at' => now(),
      'deleted_reason' => $reason,
      'deleted_by' => $adminId,
      'is_banned' => true,
      'deleted_by_self' => false, // 管理者による削除
    ];

    // 再登録許可の場合のみメールアドレスを変更
    if ($this->allow_re_registration) {
      $updateData['email'] = $this->email . '.deleted.' . time() . '.' . $this->id;
    }

    $result = $this->update($updateData);

    if ($result) {
      // 元のメールアドレスと名前を別カラムに保存（復元時に使用）
      $this->update([
        'original_email' => $originalEmail,
        'previous_name' => $originalName,
      ]);

      // ユーザーが参加しているチャットルームも自動削除
      $this->getChatRooms()->whereNull('deleted_at')->each(function ($chatRoom) use ($adminId, $reason) {
        $chatRoom->deleteByAdmin($adminId, "参加者（{$this->name}）の削除に伴う自動削除: " . ($reason ?? '管理者による削除'));
      });

      // ユーザーの友達関係も論理削除
      $this->deleteFriendshipsByAdmin($adminId, "ユーザー（{$this->name}）の削除に伴う友達関係の削除: " . ($reason ?? '管理者による削除'));
    }

    return $result;
  }

  /**
   * ユーザー自身によるアカウント削除
   */
  public function deleteBySelf(string $reason = null): bool
  {
    // 削除前の名前を保存（復元時に使用）
    $originalName = $this->name;

    $result = $this->update([
      'deleted_at' => now(),
      'deleted_reason' => $reason ?? 'ユーザー自身による削除',
      'deleted_by' => null, // 自己削除の場合はnull
      'is_banned' => false, // 自己削除の場合はバンではない
      'deleted_by_self' => true, // 自己削除フラグ
      'allow_re_registration' => true, // 自己削除の場合はデフォルトで再登録可能
      'previous_name' => $originalName, // 元の名前を保存
      // メールアドレスは変更せず、再登録可能にする
    ]);

    if ($result) {
      // ユーザーが参加しているチャットルームも自動削除
      $this->getChatRooms()->whereNull('deleted_at')->each(function ($chatRoom) use ($reason) {
        // 自己削除の場合は専用メソッドを使用
        $chatRoom->deleteBySelfRemoval("参加者（{$this->name}）の自己削除に伴う自動削除: " . ($reason ?? 'ユーザー自身による削除'));
      });

      // ユーザーの友達関係も論理削除
      $this->deleteFriendshipsBySelf($reason ?? 'ユーザー自身による削除');
    }

    return $result;
  }

  /**
   * ユーザー自身による削除の復元（再登録）
   */
  public function restoreBySelf(): bool
  {
    // 自己削除でない場合は復元不可
    if (!$this->deleted_by_self) {
      throw new \Exception("自己削除以外のアカウントは復元できません。");
    }

    $result = $this->update([
      'deleted_at' => null,
      'deleted_reason' => null,
      'deleted_by' => null,
      'is_banned' => false,
      'deleted_by_self' => false,
      'is_verified' => false, // 復元時は未認証状態にリセット
      'email_verification_token' => null, // 古いトークンをクリア
      'token_expires_at' => null, // 有効期限もクリア
      'email_verified_at' => null, // 認証日時もクリア
      // メールアドレスはそのまま（変更していないため）
    ]);

    if ($result) {
      // このユーザーの自己削除が原因で削除されたチャットルームを復元
      ChatRoom::onlyTrashed()
        ->where('deleted_reason', 'LIKE', "%参加者（{$this->name}）の自己削除に伴う自動削除%")
        ->each(function ($chatRoom) {
          $chatRoom->restoreByAdmin();
        });

      // ユーザーの自己削除が原因で削除された友達関係を復元
      $this->restoreFriendshipsBySelf();
    }

    return $result;
  }

  /**
   * ユーザー自身による削除かどうかをチェック
   */
  public function isDeletedBySelf(): bool
  {
    return $this->isDeleted() && $this->deleted_by_self;
  }

  /**
   * 名前変更提案が必要かどうかをチェック
   * （削除前の名前と現在の名前が異なり、まだ提案を受けていない場合）
   */
  public function shouldSuggestNameChange(): bool
  {
    $result = !empty($this->previous_name) &&
      $this->previous_name !== $this->name &&
      $this->is_verified; // 認証済みユーザーのみ対象

    Log::info('名前変更提案チェック', [
      'user_id' => $this->id,
      'previous_name' => $this->previous_name,
      'current_name' => $this->name,
      'is_verified' => $this->is_verified,
      'should_suggest' => $result
    ]);

    return $result;
  }

  /**
   * 名前変更提案を完了する（previous_nameをクリア）
   */
  public function markNameSuggestionComplete(): bool
  {
    return $this->update(['previous_name' => null]);
  }

  /**
   * 管理者による削除かどうかをチェック
   */
  public function isDeletedByAdmin(): bool
  {
    return $this->isDeleted() && !$this->deleted_by_self;
  }

  /**
   * 再登録可能かどうかをチェック
   */
  public function canReRegister(): bool
  {
    if (!$this->isDeleted()) {
      return false;
    }

    // 自己削除・管理者削除に関わらず、管理者による再登録許可フラグをチェック
    return $this->allow_re_registration;
  }

  /**
   * 管理者による削除の復元
   */
  public function restoreByAdmin(): bool
  {
    // 元のメールアドレスを復元（original_emailカラムから取得）
    $originalEmail = $this->original_email ?? $this->email;

    // 復元時にメールアドレスが既に使用されていないかチェック
    if (static::where('email', $originalEmail)->whereNull('deleted_at')->exists()) {
      // メールアドレスが既に使用されている場合は復元できない
      throw new \Exception("メールアドレス「{$originalEmail}」は既に使用されているため、復元できません。");
    }

    $result = $this->update([
      'email' => $originalEmail, // 元のメールアドレスに復元
      'original_email' => null, // 復元用カラムをクリア
      'deleted_at' => null,
      'deleted_reason' => null,
      'deleted_by' => null,
      'is_banned' => false,
      'deleted_by_self' => false,
      'is_verified' => false, // 復元時は未認証状態にリセット
      'email_verification_token' => null, // 古いトークンをクリア
      'token_expires_at' => null, // 有効期限もクリア
      'email_verified_at' => null, // 認証日時もクリア
      // 注意: previous_nameは削除しない（名前変更提案機能で使用）
    ]);

    if ($result) {
      // このユーザーの削除が原因で削除されたチャットルームを復元
      ChatRoom::onlyTrashed()
        ->where('deleted_reason', 'LIKE', "%参加者（{$this->name}）の削除に伴う自動削除%")
        ->each(function ($chatRoom) {
          $chatRoom->restoreByAdmin();
        });

      // ユーザーの削除が原因で削除された友達関係を復元
      $this->restoreFriendshipsByAdmin();
    }

    return $result;
  }

  /**
   * ユーザー自身による削除時の友達関係の論理削除
   */
  private function deleteFriendshipsBySelf(string $reason): void
  {
    // 送信した友達関係
    $this->sentFriendships()->each(function ($friendship) use ($reason) {
      if (!$friendship->isDeleted()) {
        $friendship->deleteBySelfRemoval($reason); // 自己削除専用メソッドを使用
      }
    });

    // 受信した友達関係
    $this->receivedFriendships()->each(function ($friendship) use ($reason) {
      if (!$friendship->isDeleted()) {
        $friendship->deleteBySelfRemoval($reason); // 自己削除専用メソッドを使用
      }
    });
  }

  /**
   * ユーザーの自己削除が原因で削除された友達関係を復元
   */
  private function restoreFriendshipsBySelf(): void
  {
    // 送信した友達関係の復元
    Friendship::withTrashed()
      ->where('user_id', $this->id)
      ->whereNotNull('deleted_at')
      ->where('deleted_reason', 'LIKE', "%ユーザー自身による削除%")
      ->each(function ($friendship) {
        $friendship->restoreByAdmin();
      });

    // 受信した友達関係の復元
    Friendship::withTrashed()
      ->where('friend_id', $this->id)
      ->whereNotNull('deleted_at')
      ->where('deleted_reason', 'LIKE', "%ユーザー自身による削除%")
      ->each(function ($friendship) {
        $friendship->restoreByAdmin();
      });
  }

  /**
   * ユーザーの友達関係を論理削除（管理者による削除時）
   */
  private function deleteFriendshipsByAdmin(int $adminId, string $reason): void
  {
    // 送信した友達関係
    $this->sentFriendships()->each(function ($friendship) use ($adminId, $reason) {
      if (!$friendship->isDeleted()) {
        $friendship->deleteByAdmin($adminId, $reason);
      }
    });

    // 受信した友達関係
    $this->receivedFriendships()->each(function ($friendship) use ($adminId, $reason) {
      if (!$friendship->isDeleted()) {
        $friendship->deleteByAdmin($adminId, $reason);
      }
    });
  }

  /**
   * ユーザーの削除が原因で削除された友達関係を復元（管理者による復元時）
   */
  private function restoreFriendshipsByAdmin(): void
  {
    // 送信した友達関係の復元
    Friendship::withTrashed()
      ->where('user_id', $this->id)
      ->whereNotNull('deleted_at')
      ->where('deleted_reason', 'LIKE', "%ユーザー（{$this->name}）の削除に伴う友達関係の削除%")
      ->each(function ($friendship) {
        $friendship->restoreByAdmin();
      });

    // 受信した友達関係の復元
    Friendship::withTrashed()
      ->where('friend_id', $this->id)
      ->whereNotNull('deleted_at')
      ->where('deleted_reason', 'LIKE', "%ユーザー（{$this->name}）の削除に伴う友達関係の削除%")
      ->each(function ($friendship) {
        $friendship->restoreByAdmin();
      });
  }

  /**
   * ユーザーが所有するグループ（新アーキテクチャ）
   */
  public function ownedGroups(): HasMany
  {
    return $this->hasMany(Group::class, 'owner_id');
  }

  /**
   * ユーザーのサブスクリプション
   */
  public function subscriptions(): HasMany
  {
    return $this->hasMany(Subscription::class);
  }
}
