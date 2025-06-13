<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
  use HasFactory;

  protected $table = 'participants';

  protected $fillable = [
    'conversation_id',
    'chat_room_id',
    'user_id',
    'joined_at',
    'last_read_message_id',
    'last_read_at',
  ];

  protected $casts = [
    'joined_at' => 'datetime',
    'last_read_at' => 'datetime',
  ];

  /**
   * この参加情報が属するチャットを取得
   */
  public function conversation(): BelongsTo
  {
    return $this->belongsTo(Conversation::class);
  }

  /**
   * この参加情報に紐づくユーザーを取得
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * 最後に読んだメッセージを取得
   */
  public function lastReadMessage(): BelongsTo
  {
    return $this->belongsTo(Message::class, 'last_read_message_id');
  }

  /**
   * この参加者が所属するチャットルーム（新構造）
   */
  public function chatRoom(): BelongsTo
  {
    return $this->belongsTo(ChatRoom::class);
  }

  /**
   * 新構造を使用しているかチェック
   */
  public function usesNewStructure(): bool
  {
    return !is_null($this->chat_room_id);
  }

  /**
   * 実際のチャットルームを取得（新旧構造対応）
   */
  public function getActiveChatRoom()
  {
    if ($this->usesNewStructure()) {
      return $this->chatRoom;
    }

    // 旧構造の場合はConversationから対応するChatRoomを探す
    if ($this->conversation) {
      return ChatRoom::where('room_token', $this->conversation->room_token)->first();
    }

    return null;
  }
}
