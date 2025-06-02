<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminConversationRead extends Model
{
  use HasFactory;

  protected $fillable = [
    'admin_id',
    'conversation_id',
    'last_read_at',
  ];

  protected $casts = [
    'last_read_at' => 'datetime',
  ];

  /**
   * この記録に関連する管理者を取得
   */
  public function admin(): BelongsTo
  {
    return $this->belongsTo(Admin::class);
  }

  /**
   * この記録に関連する会話を取得
   */
  public function conversation(): BelongsTo
  {
    return $this->belongsTo(Conversation::class);
  }

  /**
   * 指定した管理者と会話の最後読み取り時刻を更新
   */
  public static function updateLastRead(int $adminId, int $conversationId): void
  {
    static::updateOrCreate(
      [
        'admin_id' => $adminId,
        'conversation_id' => $conversationId,
      ],
      [
        'last_read_at' => now(),
      ]
    );
  }

  /**
   * 指定した管理者の未読メッセージ数を取得
   */
  public static function getUnreadCount(int $adminId): int
  {
    return Conversation::where('type', 'support')
      ->whereNull('deleted_at')
      ->whereHas('messages', function ($messageQuery) use ($adminId) {
        $messageQuery->whereNull('admin_sender_id') // ユーザーからのメッセージのみ
          ->whereNull('admin_deleted_at')
          ->where(function ($q) use ($adminId) {
            $q->whereNotExists(function ($subQuery) use ($adminId) {
              $subQuery->select('id')
                ->from('admin_conversation_reads')
                ->where('admin_id', $adminId)
                ->whereColumn('conversation_id', 'messages.conversation_id')
                ->whereColumn('last_read_at', '>=', 'messages.sent_at');
            });
          });
      })
      ->count();
  }

  /**
   * 指定した管理者と会話の未読メッセージ数を取得
   */
  public static function getConversationUnreadCount(int $adminId, int $conversationId): int
  {
    $lastRead = static::where('admin_id', $adminId)
      ->where('conversation_id', $conversationId)
      ->first();

    $query = Message::where('conversation_id', $conversationId)
      ->whereNull('admin_sender_id') // ユーザーからのメッセージのみ
      ->whereNull('admin_deleted_at');

    if ($lastRead) {
      $query->where('sent_at', '>', $lastRead->last_read_at);
    }

    return $query->count();
  }
}
