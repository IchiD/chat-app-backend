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
  ];

  protected $casts = [
    'joined_at' => 'datetime',
    'left_at' => 'datetime',
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
}
