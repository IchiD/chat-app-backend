<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
  use HasFactory;

  protected $fillable = [
    'group_id',
    'user_id',
    'joined_at',
    'left_at',
    'role',
    'can_rejoin',
    'removal_type',
    'removed_by_user_id',
    'removed_by_admin_id',
  ];

  protected $casts = [
    'joined_at' => 'datetime',
    'left_at' => 'datetime',
    'can_rejoin' => 'boolean',
  ];

  /**
   * 所属グループ
   */
  public function group(): BelongsTo
  {
    return $this->belongsTo(Group::class);
  }

  /**
   * メンバーのユーザー
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * 現在アクティブなメンバーのみを取得するスコープ
   */
  public function scopeActive($query)
  {
    return $query->whereNull('left_at');
  }

  /**
   * 特定の役割のメンバーのみを取得するスコープ
   */
  public function scopeWithRole($query, string $role)
  {
    return $query->where('role', $role);
  }

  /**
   * オーナーかどうかチェック
   */
  public function isOwner(): bool
  {
    return $this->role === 'owner';
  }

  /**
   * 管理者かどうかチェック
   */
  public function isAdmin(): bool
  {
    return $this->role === 'admin';
  }

  /**
   * 削除実行者との関係
   */
  public function removedByUser()
  {
    return $this->belongsTo(User::class, 'removed_by_user_id');
  }

  /**
   * 削除した管理者との関係
   */
  public function removedByAdmin()
  {
    return $this->belongsTo(Admin::class, 'removed_by_admin_id');
  }

  /**
   * 削除済みメンバーのスコープ
   */
  public function scopeRemoved($query)
  {
    return $query->whereNotNull('left_at');
  }

  /**
   * 再参加禁止メンバーのスコープ
   */
  public function scopeCannotRejoin($query)
  {
    return $query->where('can_rejoin', false);
  }

  /**
   * 全メンバー（アクティブ+削除済み）のスコープ
   */
  public function scopeAllMembers($query)
  {
    return $query; // 制限なし
  }

  /**
   * メンバーを削除（論理削除）
   */
  public function removeMember(string $removalType, int $removedByUserId, bool $canRejoin = true): void
  {
    $this->update([
      'left_at' => now(),
      'removal_type' => $removalType,
      'removed_by_user_id' => $removedByUserId,
      'can_rejoin' => $canRejoin,
    ]);
  }

  /**
   * メンバーを復活
   */
  public function restoreMember(): void
  {
    $this->update([
      'left_at' => null,
      'removal_type' => null,
      'removed_by_user_id' => null,
      'can_rejoin' => true,
    ]);
  }
}
