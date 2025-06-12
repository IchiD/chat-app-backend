<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Friendship extends Model
{
  use HasFactory, SoftDeletes;

  /**
   * 複数代入可能な属性
   */
  protected $fillable = [
    'user_id',
    'friend_id',
    'status',
    'message',
    'deleted_at',
    'deleted_reason',
    'deleted_by',
  ];

  /**
   * 日付フィールドのキャスト
   */
  protected $dates = [
    'deleted_at',
  ];

  /**
   * ステータスの定数定義
   */
  const STATUS_PENDING = 0;  // 申請中
  const STATUS_ACCEPTED = 1; // 承認済み
  const STATUS_REJECTED = 2; // 拒否

  /**
   * 友達申請を送ったユーザーとのリレーション
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  /**
   * 友達申請を受けたユーザーとのリレーション
   */
  public function friend(): BelongsTo
  {
    return $this->belongsTo(User::class, 'friend_id');
  }

  /**
   * 削除を実行した管理者とのリレーション
   */
  public function deletedByAdmin(): BelongsTo
  {
    return $this->belongsTo(Admin::class, 'deleted_by');
  }

  /**
   * 特定のユーザー間の友達関係を取得（論理削除を含む）
   */
  public static function getFriendshipWithTrashed(int $userId, int $friendId)
  {
    return self::withTrashed()->where(function ($query) use ($userId, $friendId) {
      $query->where('user_id', $userId)
        ->where('friend_id', $friendId);
    })->orWhere(function ($query) use ($userId, $friendId) {
      $query->where('user_id', $friendId)
        ->where('friend_id', $userId);
    })->first();
  }

  /**
   * 特定のユーザー間の友達関係を取得（アクティブなもののみ）
   */
  public static function getFriendship(int $userId, int $friendId)
  {
    return self::whereNull('deleted_at')->where(function ($query) use ($userId, $friendId) {
      $query->where('user_id', $userId)
        ->where('friend_id', $friendId);
    })->orWhere(function ($query) use ($userId, $friendId) {
      $query->whereNull('deleted_at')
        ->where('user_id', $friendId)
        ->where('friend_id', $userId);
    })->first();
  }

  /**
   * 友達関係のステータスを取得（どちらがリクエストしたかに関わらず）
   */
  public static function getFriendshipStatus(int $userId, int $friendId): int
  {
    $friendship = self::getFriendship($userId, $friendId);

    if (!$friendship) {
      return -1; // 関係なし
    }

    return $friendship->status;
  }

  /**
   * 友達関係を論理削除（管理者による）
   */
  public function deleteByAdmin(?int $adminId, string $reason = null): bool
  {
    $this->deleted_at = now();
    $this->deleted_reason = $reason;
    $this->deleted_by = $adminId;
    return $this->save();
  }

  /**
   * 友達関係を論理削除（ユーザー自己削除による）
   */
  public function deleteBySelfRemoval(string $reason = null): bool
  {
    $this->deleted_at = now();
    $this->deleted_reason = $reason;
    $this->deleted_by = null; // 自己削除の場合はnull
    return $this->save();
  }

  /**
   * 友達関係を復活
   */
  public function restoreByAdmin(): bool
  {
    $this->deleted_at = null;
    $this->deleted_reason = null;
    $this->deleted_by = null;
    return $this->save();
  }

  /**
   * 友達関係が論理削除されているかチェック
   */
  public function isDeleted(): bool
  {
    return !is_null($this->deleted_at);
  }
}
