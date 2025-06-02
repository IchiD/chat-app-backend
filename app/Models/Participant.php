<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
  use HasFactory;

  protected $fillable = [
    'conversation_id',
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
   * この参加情報が属する会話を取得
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
}
