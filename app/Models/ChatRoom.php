<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ChatRoom extends Model
{
  use HasFactory, SoftDeletes;

  protected $fillable = [
    'type',
    'group_id',
    'participant1_id',
    'participant2_id',
    'room_token',
    'deleted_at',
    'deleted_by',
    'deleted_reason',
  ];

  protected $dates = [
    'deleted_at',
  ];

  /**
   * モデルの起動メソッド
   */
  protected static function booted(): void
  {
    static::creating(function (ChatRoom $chatRoom) {
      if (empty($chatRoom->room_token)) {
        do {
          $token = Str::random(16);
        } while (static::where('room_token', $token)->exists());
        $chatRoom->room_token = $token;
      }
    });
  }

  /**
   * 所属グループ
   */
  public function group(): BelongsTo
  {
    return $this->belongsTo(Group::class);
  }

  /**
   * 参加者1
   */
  public function participant1(): BelongsTo
  {
    return $this->belongsTo(User::class, 'participant1_id');
  }

  /**
   * 参加者2
   */
  public function participant2(): BelongsTo
  {
    return $this->belongsTo(User::class, 'participant2_id');
  }

  /**
   * チャットルームの参加者を取得（新アーキテクチャ）
   * ユーザーIDの配列を返す
   */
  public function getParticipants()
  {
    if ($this->isGroupChat() && $this->group) {
      // グループチャットの場合は、group_membersから取得
      return $this->group->activeMembers()->pluck('user_id');
    }

    // friend_chatやmember_chatの場合
    $participants = collect();
    if ($this->participant1_id) {
      $participants->push($this->participant1_id);
    }
    if ($this->participant2_id) {
      $participants->push($this->participant2_id);
    }

    return $participants->filter();
  }

  /**
   * チャットルームのメッセージ
   */
  public function messages(): HasMany
  {
    return $this->hasMany(Message::class)->orderBy('sent_at', 'desc');
  }

  /**
   * 最新メッセージ
   */
  public function latestMessage()
  {
    return $this->hasOne(Message::class)
      ->whereNull('deleted_at')
      ->whereNull('admin_deleted_at')
      ->latest('sent_at');
  }

  /**
   * グループチャットかどうか
   */
  public function isGroupChat(): bool
  {
    return $this->type === 'group_chat';
  }

  /**
   * メンバーチャットかどうか
   */
  public function isMemberChat(): bool
  {
    return $this->type === 'member_chat';
  }

  /**
   * 指定ユーザーが参加しているかチェック
   */
  public function hasParticipant(int $userId): bool
  {
    if ($this->isGroupChat()) {
      // グループチャットの場合は、group_membersテーブルをチェック
      if (!$this->group) {
        return false;
      }
      return $this->group->hasMember($userId);
    }

    return $this->participant1_id === $userId || $this->participant2_id === $userId;
  }

  /**
   * チャットルーム名を取得
   */
  public function getDisplayName(): string
  {
    if ($this->isGroupChat()) {
      return $this->group->name ?? 'グループチャット';
    }

    // メンバーチャットの場合は参加者名
    if ($this->participant1 && $this->participant2) {
      return $this->participant1->name . ' & ' . $this->participant2->name;
    }

    return 'メンバーチャット';
  }

  /**
   * 特定ユーザーから見た相手の名前（メンバーチャット用）
   */
  public function getOtherParticipantName(int $userId): string
  {
    if (!$this->isMemberChat()) {
      return $this->getDisplayName();
    }

    if ($this->participant1_id === $userId) {
      return $this->participant2->name ?? '不明なユーザー';
    }

    if ($this->participant2_id === $userId) {
      return $this->participant1->name ?? '不明なユーザー';
    }

    return '不明なユーザー';
  }

  /**
   * 削除を実行した管理者を取得
   */
  public function deletedByAdmin(): BelongsTo
  {
    return $this->belongsTo(Admin::class, 'deleted_by');
  }

  /**
   * チャットルームが論理削除されているかチェック
   */
  public function isDeleted(): bool
  {
    return !is_null($this->deleted_at);
  }

  /**
   * 管理者によるチャットルーム削除
   */
  public function deleteByAdmin(?int $adminId, ?string $reason = null): bool
  {
    return $this->update([
      'deleted_at' => now(),
      'deleted_reason' => $reason,
      'deleted_by' => $adminId,
    ]);
  }

  /**
   * ユーザー自己削除によるチャットルーム削除
   */
  public function deleteBySelfRemoval(string $reason = null): bool
  {
    return $this->update([
      'deleted_at' => now(),
      'deleted_reason' => $reason,
      'deleted_by' => null, // 自己削除の場合はnull
    ]);
  }

  /**
   * チャットルームの削除を取り消し
   */
  public function restoreByAdmin(): bool
  {
    return $this->update([
      'deleted_at' => null,
      'deleted_reason' => null,
      'deleted_by' => null,
    ]);
  }

  /**
   * 友達関係削除による論理削除
   */
  public function deleteByFriendshipRemoval(int $userId, string $reason = '友達関係の削除'): bool
  {
    return $this->update([
      'deleted_at' => now(),
      'deleted_reason' => $reason,
      'deleted_by' => $userId,
    ]);
  }

  /**
   * 友達関係復活による復活
   */
  public function restoreByFriendshipRestore(): bool
  {
    return $this->update([
      'deleted_at' => null,
      'deleted_reason' => null,
      'deleted_by' => null,
    ]);
  }

  /**
   * 2人のユーザー間の友達チャットを取得
   */
  public static function getFriendChat(int $userId1, int $userId2): ?self
  {
    return static::where('type', 'friend_chat')
      ->where(function ($query) use ($userId1, $userId2) {
        $query->where(function ($q) use ($userId1, $userId2) {
          $q->where('participant1_id', $userId1)
            ->where('participant2_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
          $q->where('participant1_id', $userId2)
            ->where('participant2_id', $userId1);
        });
      })
      ->withTrashed() // 論理削除されたものも含める
      ->first();
  }
}
