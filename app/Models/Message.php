<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
  use HasFactory;

  protected $fillable = [
    'chat_room_id',
    'sender_id',
    'admin_sender_id',
    'content_type',
    'text_content',
    'sent_at',
    'edited_at',
    'deleted_at',
    'admin_deleted_at',
    'admin_deleted_by',
    'admin_deleted_reason',
  ];

  protected $casts = [
    'sent_at' => 'datetime',
    'edited_at' => 'datetime',
    'deleted_at' => 'datetime',
    'admin_deleted_at' => 'datetime',
  ];



  /**
   * このメッセージの送信者を取得（ユーザー）
   */
  public function sender(): BelongsTo
  {
    return $this->belongsTo(User::class, 'sender_id');
  }

  /**
   * このメッセージの送信者を取得（管理者）
   */
  public function adminSender(): BelongsTo
  {
    return $this->belongsTo(Admin::class, 'admin_sender_id');
  }

  /**
   * 管理者による削除を実行した管理者を取得
   */
  public function adminDeletedBy(): BelongsTo
  {
    return $this->belongsTo(Admin::class, 'admin_deleted_by');
  }

  /**
   * このメッセージの既読記録
   */
  public function messageReads(): HasMany
  {
    return $this->hasMany(MessageRead::class);
  }

  /**
   * メッセージが管理者によって削除されているかチェック
   */
  public function isAdminDeleted(): bool
  {
    return !is_null($this->admin_deleted_at);
  }

  /**
   * メッセージが管理者からのものかチェック
   */
  public function isFromAdmin(): bool
  {
    return !is_null($this->admin_sender_id);
  }

  /**
   * メッセージの実際の送信者を取得（ユーザーまたは管理者）
   */
  public function getActualSender()
  {
    if ($this->isFromAdmin()) {
      return $this->adminSender;
    }
    return $this->sender;
  }

  /**
   * 管理者によるメッセージ削除
   */
  public function deleteByAdmin(int $adminId, string $reason = null): bool
  {
    return $this->update([
      'admin_deleted_at' => now(),
      'admin_deleted_reason' => $reason,
      'admin_deleted_by' => $adminId,
    ]);
  }

  /**
   * 管理者による削除を取り消し
   */
  public function restoreByAdmin(): bool
  {
    return $this->update([
      'admin_deleted_at' => null,
      'admin_deleted_reason' => null,
      'admin_deleted_by' => null,
    ]);
  }

  /**
   * このメッセージが送信されたチャットルーム（新構造）
   */
  public function chatRoom(): BelongsTo
  {
    return $this->belongsTo(ChatRoom::class);
  }

  /**
   * 送信者の表示名を取得（退室状態を考慮）
   */
  public function getSenderDisplayName(): string
  {
    // 管理者メッセージの場合
    if ($this->isFromAdmin()) {
      return $this->adminSender ? $this->adminSender->name : '管理者';
    }

    // ユーザーメッセージの場合
    if ($this->sender) {
      // グループチャットの場合は退室状態をチェック
      if ($this->chatRoom && $this->chatRoom->isGroupChat() && $this->chatRoom->group) {
        $memberInfo = $this->chatRoom->group->groupMembers()
          ->where('user_id', $this->sender_id)
          ->first();

        if ($memberInfo && $memberInfo->left_at) {
          return $this->sender->name . '（退室済み）';
        }
      }

      return $this->sender->name;
    }

    return 'ユーザー';
  }

  /**
   * 相手が既読したかチェック（1対1チャット用）
   */
  public function isReadByOtherParticipant(int $currentUserId): bool
  {
    if (!$this->chatRoom) {
      return false;
    }

    // 自分以外の参加者を取得
    $otherParticipantId = $this->chatRoom->participant1_id === $currentUserId
      ? $this->chatRoom->participant2_id
      : $this->chatRoom->participant1_id;

    \Log::info('既読チェックデバッグ', [
      'message_id' => $this->id,
      'current_user_id' => $currentUserId,
      'participant1_id' => $this->chatRoom->participant1_id,
      'participant2_id' => $this->chatRoom->participant2_id,
      'other_participant_id' => $otherParticipantId,
      'chat_room_type' => $this->chatRoom->type
    ]);

    if (!$otherParticipantId) {
      return false;
    }

    $isRead = $this->messageReads()
      ->where('user_id', $otherParticipantId)
      ->exists();

    \Log::info('既読チェック結果', [
      'message_id' => $this->id,
      'other_participant_id' => $otherParticipantId,
      'is_read' => $isRead
    ]);

    return $isRead;
  }

  /**
   * グループチャット用：既読したユーザー数を取得
   */
  public function getReadCount(): int
  {
    // 自分の既読は除外
    return $this->messageReads()
      ->where('user_id', '!=', $this->sender_id)
      ->count();
  }

  /**
   * 既読したユーザーのリストを取得
   */
  public function getReadUsersList(): array
  {
    return $this->messageReads()
      ->with('user:id,name')
      ->get()
      ->map(function ($read) {
        return [
          'user_id' => $read->user_id,
          'user_name' => $read->user->name ?? '不明',
          'read_at' => $read->read_at,
        ];
      })
      ->toArray();
  }

  /**
   * 指定ユーザーがこのメッセージを既読したかチェック
   */
  public function isReadByUser(int $userId): bool
  {
    return $this->messageReads()
      ->where('user_id', $userId)
      ->exists();
  }
}
