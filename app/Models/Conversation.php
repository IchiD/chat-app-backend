<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Conversation extends Model
{
  use HasFactory;

  protected $fillable = [
    'type',
    'deleted_at',
    'deleted_reason',
    'deleted_by',
  ];

  protected $casts = [
    'deleted_at' => 'datetime',
  ];

  /**
   * モデルの起動メソッド
   * creatingイベントでroom_tokenを自動生成
   */
  protected static function booted(): void
  {
    static::creating(function (Conversation $conversation) {
      if (empty($conversation->room_token)) {
        do {
          // 16文字のランダムな英数字トークンを生成
          $token = Str::random(16);
        } while (static::where('room_token', $token)->exists());
        $conversation->room_token = $token;
      }
    });
  }

  /**
   * この会話に参加しているユーザーを取得 (Participantsテーブル経由)
   */
  public function participants(): HasManyThrough
  {
    return $this->hasManyThrough(User::class, Participant::class, 'conversation_id', 'id', 'id', 'user_id');
  }

  /**
   * この会話のParticipantレコードを取得
   */
  public function conversationParticipants(): HasMany // より直接的なリレーション名
  {
    return $this->hasMany(Participant::class);
  }

  /**
   * この会話のメッセージを取得
   */
  public function messages(): HasMany
  {
    return $this->hasMany(Message::class)->orderBy('sent_at', 'desc');
  }

  /**
   * この会話の最新メッセージを取得（削除されたメッセージは除外）
   */
  public function latestMessage()
  {
    return $this->hasOne(Message::class)
      ->whereNull('deleted_at') // ユーザーによる削除を除外
      ->whereNull('admin_deleted_at') // 管理者による削除を除外
      ->latest('sent_at');
  }

  /**
   * 削除を実行した管理者を取得
   */
  public function deletedByAdmin()
  {
    return $this->belongsTo(Admin::class, 'deleted_by');
  }

  /**
   * 会話が論理削除されているかチェック
   */
  public function isDeleted(): bool
  {
    return !is_null($this->deleted_at);
  }

  /**
   * 管理者による会話削除
   */
  public function deleteByAdmin(?int $adminId, string $reason = null): bool
  {
    return $this->update([
      'deleted_at' => now(),
      'deleted_reason' => $reason,
      'deleted_by' => $adminId,
    ]);
  }

  /**
   * 会話の削除を取り消し
   */
  public function restoreByAdmin(): bool
  {
    return $this->update([
      'deleted_at' => null,
      'deleted_reason' => null,
      'deleted_by' => null,
    ]);
  }
}
