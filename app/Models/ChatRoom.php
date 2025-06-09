<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatRoom extends Model
{
  use HasFactory;

  protected $fillable = [
    'type',
    'group_id',
    'participant1_id',
    'participant2_id',
    'room_token',
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
   * チャットルームの参加者（Participantテーブル経由）
   */
  public function participants(): HasMany
  {
    return $this->hasMany(Participant::class);
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
      return $this->participants()->where('user_id', $userId)->exists();
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
}
