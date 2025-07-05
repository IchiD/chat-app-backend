<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Group extends Model
{
  use HasFactory, SoftDeletes;

  protected $fillable = [
    'name',
    'description',
    'max_members',
    'chat_styles',
    'owner_user_id',
    'qr_code_token',
    'deleted_at',
    'deleted_by',
    'deleted_reason',
  ];

  protected $casts = [
    'chat_styles' => 'array',
    'deleted_at' => 'datetime',
  ];

  /**
   * モデルの起動メソッド
   */
  protected static function booted(): void
  {
    static::creating(function (Group $group) {
      if (empty($group->qr_code_token)) {
        do {
          $token = Str::random(32);
        } while (static::where('qr_code_token', $token)->exists());
        $group->qr_code_token = $token;
      }
    });
  }

  /**
   * グループオーナー（削除されたユーザーも含む）
   */
  public function owner(): BelongsTo
  {
    return $this->belongsTo(User::class, 'owner_user_id')->withTrashed();
  }

  /**
   * グループのチャットルーム
   */
  public function chatRooms(): HasMany
  {
    return $this->hasMany(ChatRoom::class);
  }

  /**
   * グループ全体チャットルーム
   */
  public function groupChatRoom()
  {
    return $this->hasOne(ChatRoom::class)->where('type', 'group_chat');
  }

  /**
   * メンバー間チャットルーム
   */
  public function memberChatRooms(): HasMany
  {
    return $this->hasMany(ChatRoom::class)->where('type', 'member_chat');
  }

  /**
   * グループのメンバー（GroupMemberテーブル経由）
   */
  public function groupMembers(): HasMany
  {
    return $this->hasMany(GroupMember::class);
  }

  /**
   * アクティブなグループメンバー
   */
  public function activeMembers(): HasMany
  {
    return $this->hasMany(GroupMember::class)->active();
  }

  /**
   * グループのメンバーユーザー（Userモデル直接取得）
   */
  public function members()
  {
    return $this->belongsToMany(User::class, 'group_members')
      ->withPivot(['joined_at', 'left_at', 'role', 'owner_nickname'])
      ->withTimestamps()
      ->wherePivotNull('left_at'); // アクティブなメンバーのみ
  }

  /**
   * ユーザーがグループのメンバーかどうかをチェック
   */
  public function hasMember(int $userId): bool
  {
    return $this->activeMembers()->where('user_id', $userId)->exists();
  }

  /**
   * 現在のメンバー数を取得
   */
  public function getMembersCount(): int
  {
    return $this->activeMembers()->count();
  }

  /**
   * チャットスタイルの確認
   */
  public function hasGroupChat(): bool
  {
    return in_array('group', $this->chat_styles ?? []);
  }

  public function hasMemberChat(): bool
  {
    return in_array('group_member', $this->chat_styles ?? []);
  }

  /**
   * QRコードトークンを再生成
   */
  public function regenerateQrToken(): void
  {
    do {
      $token = Str::random(32);
    } while (static::where('qr_code_token', $token)->exists());

    $this->update(['qr_code_token' => $token]);
  }

  /**
   * メンバー数制限チェック
   */
  public function canAddMember(): bool
  {
    return $this->getMembersCount() < $this->max_members;
  }

  /**
   * グループが削除されているかチェック
   */
  public function isDeleted(): bool
  {
    return !is_null($this->deleted_at);
  }

  /**
   * 削除を実行した管理者
   */
  public function deletedByAdmin(): BelongsTo
  {
    return $this->belongsTo(\App\Models\Admin::class, 'deleted_by');
  }

  /**
   * 管理者によるグループ削除
   */
  public function deleteByAdmin(int $adminId, string $reason = null): bool
  {
    $result = $this->update([
      'deleted_at' => now(),
      'deleted_by' => $adminId,
      'deleted_reason' => $reason,
    ]);

    if ($result) {
      // グループに関連するチャットルームも削除
      $this->chatRooms()->whereNull('deleted_at')->each(function ($chatRoom) use ($adminId, $reason) {
        $chatRoom->deleteByAdmin($adminId, "グループ削除に伴う自動削除: " . ($reason ?? '管理者による削除'));
      });
    }

    return $result;
  }

  /**
   * ユーザー自身による削除（オーナーのアカウント削除時）
   */
  public function deleteBySelf(string $reason = null): bool
  {
    $result = $this->update([
      'deleted_at' => now(),
      'deleted_by' => null, // ユーザー自身による削除の場合は管理者IDは null
      'deleted_reason' => $reason,
    ]);

    if ($result) {
      // グループに関連するチャットルームも削除
      $this->chatRooms()->whereNull('deleted_at')->each(function ($chatRoom) use ($reason) {
        $chatRoom->deleteBySelfRemoval("グループ削除に伴う自動削除: " . ($reason ?? 'オーナーによる削除'));
      });
    }

    return $result;
  }

  /**
   * 管理者によるグループ復活
   */
  public function restoreByAdmin(): bool
  {
    return $this->update([
      'deleted_at' => null,
      'deleted_by' => null,
      'deleted_reason' => null,
    ]);
  }
}
