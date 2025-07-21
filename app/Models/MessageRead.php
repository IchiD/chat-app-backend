<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageRead extends Model
{
  use HasFactory;

  protected $fillable = [
    'message_id',
    'user_id',
    'read_at',
  ];

  protected $casts = [
    'read_at' => 'datetime',
  ];

  /**
   * 既読されたメッセージ
   */
  public function message(): BelongsTo
  {
    return $this->belongsTo(Message::class);
  }

  /**
   * 既読したユーザー
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * 指定メッセージを既読にする
   */
  public static function markAsRead(int $messageId, int $userId): self
  {
    return static::firstOrCreate(
      [
        'message_id' => $messageId,
        'user_id' => $userId,
      ],
      [
        'read_at' => now(),
      ]
    );
  }

  /**
   * 複数メッセージを一括で既読にする
   */
  public static function markMultipleAsRead(array $messageIds, int $userId): void
  {
    if (empty($messageIds)) {
      return;
    }

    $data = [];
    $now = now();

    foreach ($messageIds as $messageId) {
      $data[] = [
        'message_id' => $messageId,
        'user_id' => $userId,
        'read_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
      ];
    }

    // 既存レコードがある場合は無視する（ユニーク制約）
    static::insertOrIgnore($data);
  }

  /**
   * 指定ユーザーが指定メッセージを既読したかチェック
   */
  public static function isReadByUser(int $messageId, int $userId): bool
  {
    return static::where('message_id', $messageId)
      ->where('user_id', $userId)
      ->exists();
  }

  /**
   * 指定メッセージの既読ユーザー数を取得
   */
  public static function getReadCount(int $messageId): int
  {
    return static::where('message_id', $messageId)->count();
  }

  /**
   * 指定メッセージの既読ユーザーリストを取得
   */
  public static function getReadUsers(int $messageId): \Illuminate\Support\Collection
  {
    return static::where('message_id', $messageId)
      ->with('user:id,name')
      ->get()
      ->map(function ($read) {
        return [
          'user_id' => $read->user_id,
          'user_name' => $read->user->name ?? '不明',
          'read_at' => $read->read_at,
        ];
      });
  }
}
