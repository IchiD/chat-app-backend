<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
