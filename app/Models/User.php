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
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
  /** @use HasFactory<\Database\Factories\UserFactory> */
  use HasFactory, Notifiable, HasApiTokens, HasPushSubscriptions;

  /**
   * The attributes that are mass assignable.
   *
   * @var list<string>
   */
  protected $fillable = [
    'name',
    'email',
    'password',
    'is_verified',
    'email_verification_token',
    'token_expires_at',
    'email_verified_at',
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
      'deleted_at' => 'datetime',
      'is_banned' => 'boolean',
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
      return $deletedFriendship;
    }

    // 新しい友達申請を作成
    return Friendship::create([
      'user_id' => $this->id,
      'friend_id' => $friendId,
      'status' => Friendship::STATUS_PENDING,
      'message' => $message,
    ]);
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
    return $friendship->save();
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

    // 論理削除を実行（一般ユーザーによる削除の場合はadmin_idをnullにする）
    return $friendship->deleteByAdmin(null, 'ユーザーによる友達解除');
  }

  /**
   * ユーザーが参加している会話を取得 (Participantsテーブル経由)
   */
  public function conversations(): HasManyThrough
  {
    return $this->hasManyThrough(Conversation::class, Participant::class, 'user_id', 'id', 'id', 'conversation_id');
  }

  /**
   * ユーザーが直接参加しているParticipantレコードを取得
   */
  public function participations(): HasMany
  {
    return $this->hasMany(Participant::class);
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
    return $this->is_banned;
  }

  /**
   * 管理者によるユーザー削除
   */
  public function deleteByAdmin(int $adminId, string $reason = null): bool
  {
    $result = $this->update([
      'deleted_at' => now(),
      'deleted_reason' => $reason,
      'deleted_by' => $adminId,
      'is_banned' => true,
    ]);

    if ($result) {
      // ユーザーが参加している会話も自動削除
      $this->conversations()->whereNull('deleted_at')->each(function ($conversation) use ($adminId, $reason) {
        $conversation->deleteByAdmin($adminId, "参加者（{$this->name}）の削除に伴う自動削除: " . ($reason ?? '管理者による削除'));
      });

      // ユーザーの友達関係も論理削除
      $this->deleteFriendshipsByAdmin($adminId, "ユーザー（{$this->name}）の削除に伴う友達関係の削除: " . ($reason ?? '管理者による削除'));
    }

    return $result;
  }

  /**
   * ユーザーの削除を取り消し
   */
  public function restoreByAdmin(): bool
  {
    $result = $this->update([
      'deleted_at' => null,
      'deleted_reason' => null,
      'deleted_by' => null,
      'is_banned' => false,
    ]);

    if ($result) {
      // このユーザーの削除が原因で削除された会話を復元
      $this->conversations()
        ->whereNotNull('deleted_at')
        ->where('deleted_reason', 'LIKE', "%参加者（{$this->name}）の削除に伴う自動削除%")
        ->each(function ($conversation) {
          $conversation->restoreByAdmin();
        });

      // ユーザーの削除が原因で削除された友達関係を復元
      $this->restoreFriendshipsByAdmin();
    }

    return $result;
  }

  /**
   * ユーザーの友達関係を論理削除
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
   * ユーザーの削除が原因で削除された友達関係を復元
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
}
