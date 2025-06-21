<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRoomRead extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'chat_room_id',
    'last_read_at',
  ];

  protected $casts = [
    'last_read_at' => 'datetime',
  ];

  /**
   * この既読記録に関連するユーザーを取得
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * この既読記録に関連するチャットルームを取得
   */
  public function chatRoom(): BelongsTo
  {
    return $this->belongsTo(ChatRoom::class);
  }

  /**
   * 指定ユーザーとチャットルームの最後読み取り時刻を更新
   */
  public static function updateLastRead(int $userId, int $chatRoomId): void
  {
    static::updateOrCreate(
      [
        'user_id' => $userId,
        'chat_room_id' => $chatRoomId,
      ],
      [
        'last_read_at' => now(),
      ]
    );
  }

  /**
   * 指定ユーザーの指定チャットルームの未読メッセージ数を取得
   */
  public static function getUnreadCount(int $userId, int $chatRoomId): int
  {
    $lastRead = static::where('user_id', $userId)
      ->where('chat_room_id', $chatRoomId)
      ->first();

    $query = Message::where('chat_room_id', $chatRoomId)
      ->where('sender_id', '!=', $userId) // 自分のメッセージは除外
      ->whereNull('deleted_at')
      ->whereNull('admin_deleted_at');

    if ($lastRead && $lastRead->last_read_at) {
      $query->where('sent_at', '>', $lastRead->last_read_at);
    }

    return $query->count();
  }

  /**
   * 指定ユーザーがアクセス可能な全チャットルームの未読メッセージ数を一括取得
   */
  public static function getUnreadCountsForChatRooms(int $userId, array $chatRoomIds): array
  {
    if (empty($chatRoomIds)) {
      return [];
    }

    // ユーザーの既読情報を一括取得
    $reads = static::where('user_id', $userId)
      ->whereIn('chat_room_id', $chatRoomIds)
      ->get()
      ->keyBy('chat_room_id');

    $unreadCounts = [];

    foreach ($chatRoomIds as $chatRoomId) {
      $lastRead = $reads->get($chatRoomId);

      $query = Message::where('chat_room_id', $chatRoomId)
        ->where(function ($q) use ($userId) {
          $q->where('sender_id', '!=', $userId)
            ->orWhereNotNull('admin_sender_id'); // 管理者メッセージも未読対象
        })
        ->whereNull('deleted_at')
        ->whereNull('admin_deleted_at');

      if ($lastRead && $lastRead->last_read_at) {
        $query->where('sent_at', '>', $lastRead->last_read_at);
      }

      $unreadCounts[$chatRoomId] = $query->count();
    }

    return $unreadCounts;
  }
}
