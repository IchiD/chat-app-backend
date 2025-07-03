<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChatRead extends Model
{
  use HasFactory;

  protected $fillable = [
    'admin_id',
    'chat_room_id',
    'last_read_at',
  ];

  protected $casts = [
    'last_read_at' => 'datetime',
  ];

  /**
   * この既読記録に関連する管理者を取得
   */
  public function admin(): BelongsTo
  {
    return $this->belongsTo(Admin::class);
  }

  /**
   * この既読記録に関連するチャットルームを取得
   */
  public function chatRoom(): BelongsTo
  {
    return $this->belongsTo(ChatRoom::class);
  }

  /**
   * 指定管理者とチャットルームの最後読み取り時刻を更新
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
   * 指定管理者の指定チャットルームの未読メッセージ数を取得
   */
  public static function getUnreadCount(int $adminId, int $chatRoomId): int
  {
    $lastRead = static::where('admin_id', $adminId)
      ->where('chat_room_id', $chatRoomId)
      ->first();

    $query = Message::where('chat_room_id', $chatRoomId)
      ->where('sender_id', '!=', null) // ユーザーからのメッセージのみ
      ->whereNull('deleted_at')
      ->whereNull('admin_deleted_at');

    if ($lastRead && $lastRead->last_read_at) {
      $query->where('sent_at', '>', $lastRead->last_read_at);
    }

    return $query->count();
  }

  /**
   * 指定管理者がアクセス可能な全チャットルームの未読メッセージ数を一括取得
   */
  public static function getUnreadCountsForChatRooms(int $adminId, array $chatRoomIds): array
  {
    if (empty($chatRoomIds)) {
      return [];
    }

    // 管理者の既読情報を一括取得
    $reads = static::where('admin_id', $adminId)
      ->whereIn('chat_room_id', $chatRoomIds)
      ->get()
      ->keyBy('chat_room_id');

    $unreadCounts = [];

    foreach ($chatRoomIds as $chatRoomId) {
      $lastRead = $reads->get($chatRoomId);

      $query = Message::where('chat_room_id', $chatRoomId)
        ->where('sender_id', '!=', null) // ユーザーからのメッセージのみ
        ->whereNull('deleted_at')
        ->whereNull('admin_deleted_at');

      if ($lastRead && $lastRead->last_read_at) {
        $query->where('sent_at', '>', $lastRead->last_read_at);
      }

      $unreadCounts[$chatRoomId] = $query->count();
    }

    return $unreadCounts;
  }

  /**
   * 指定管理者の全サポートチャットの総未読メッセージ数を取得
   */
  public static function getTotalUnreadCount(int $adminId): int
  {
    $chatRoomIds = ChatRoom::where('type', 'support_chat')
      ->whereNull('deleted_at')
      ->pluck('id')
      ->toArray();

    if (empty($chatRoomIds)) {
      return 0;
    }

    $unreadCounts = static::getUnreadCountsForChatRooms($adminId, $chatRoomIds);
    
    return array_sum($unreadCounts);
  }

  /**
   * 新着メッセージがあるチャットルームIDを取得
   */
  public static function getChatRoomsWithUnreadMessages(int $adminId): array
  {
    $chatRoomIds = ChatRoom::where('type', 'support_chat')
      ->whereNull('deleted_at')
      ->pluck('id')
      ->toArray();

    if (empty($chatRoomIds)) {
      return [];
    }

    $unreadCounts = static::getUnreadCountsForChatRooms($adminId, $chatRoomIds);
    
    return array_keys(array_filter($unreadCounts, function ($count) {
      return $count > 0;
    }));
  }
}