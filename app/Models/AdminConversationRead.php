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
    'conversation_id', // 旧構造用（互換性のため）
    'chat_room_id',    // 新構造用
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
   * この記録に関連する会話を取得（旧構造）
   */
  public function conversation(): BelongsTo
  {
    return $this->belongsTo(Conversation::class);
  }

  /**
   * この記録に関連するチャットルームを取得（新構造）
   */
  public function chatRoom(): BelongsTo
  {
    return $this->belongsTo(\App\Models\ChatRoom::class);
  }

  /**
   * 指定した管理者と会話の最後読み取り時刻を更新
   * 新構造（ChatRoom）に対応したバージョン
   */
  public static function updateLastRead(int $adminId, int $chatRoomId): void
  {
    static::updateOrCreate(
      [
        'admin_id' => $adminId,
        'chat_room_id' => $chatRoomId,
      ],
      [
        'last_read_at' => now(),
      ]
    );
  }

  /**
   * 指定した管理者の未読メッセージ数を取得
   * 新構造（ChatRoom）に対応したバージョン
   */
  public static function getUnreadCount(int $adminId): int
  {
    return \App\Models\ChatRoom::where('type', 'support_chat')
      ->whereNull('deleted_at')
      ->whereHas('messages', function ($messageQuery) use ($adminId) {
        $messageQuery->whereNull('admin_sender_id') // ユーザーからのメッセージのみ
          ->whereNull('admin_deleted_at')
          ->where(function ($q) use ($adminId) {
            $q->whereNotExists(function ($subQuery) use ($adminId) {
              $subQuery->select('id')
                ->from('admin_conversation_reads')
                ->where('admin_id', $adminId)
                ->whereColumn('chat_room_id', 'messages.chat_room_id')
                ->whereColumn('last_read_at', '>=', 'messages.sent_at');
            });
          });
      })
      ->count();
  }

  /**
   * 指定した管理者と会話の未読メッセージ数を取得
   * 新構造（ChatRoom）に対応したバージョン
   */
  public static function getConversationUnreadCount(int $adminId, int $chatRoomId): int
  {
    $lastRead = static::where('admin_id', $adminId)
      ->where('chat_room_id', $chatRoomId)
      ->first();

    $query = \App\Models\Message::where('chat_room_id', $chatRoomId)
      ->whereNull('admin_sender_id') // ユーザーからのメッセージのみ
      ->whereNull('admin_deleted_at');

    if ($lastRead) {
      $query->where('sent_at', '>', $lastRead->last_read_at);
    }

    return $query->count();
  }
}
