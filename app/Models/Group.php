<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Group extends Model
{
  use HasFactory;

  protected $fillable = [
    'name',
    'description',
    'max_members',
    'chat_styles',
    'owner_user_id',
    'qr_code_token',
  ];

  protected $casts = [
    'chat_styles' => 'array',
  ];

  /**
   * モデルの起動メソッド
   */
  protected static function booted(): void
  {
    static::creating(function (Group $group) {
      if (empty($group->qr_code_token)) {
        do {
          $token = Str::random(32);
        } while (static::where('qr_code_token', $token)->exists());
        $group->qr_code_token = $token;
      }
    });
  }

  /**
   * グループオーナー
   */
  public function owner(): BelongsTo
  {
    return $this->belongsTo(User::class, 'owner_user_id');
  }

  /**
   * グループのチャットルーム
   */
  public function chatRooms(): HasMany
  {
    return $this->hasMany(ChatRoom::class);
  }

  /**
   * グループ全体チャットルーム
   */
  public function groupChatRoom()
  {
    return $this->hasOne(ChatRoom::class)->where('type', 'group_chat');
  }

  /**
   * メンバー間チャットルーム
   */
  public function memberChatRooms(): HasMany
  {
    return $this->hasMany(ChatRoom::class)->where('type', 'member_chat');
  }

  /**
   * グループのメンバー（グループチャットの参加者）
   */
  public function members()
  {
    $groupChatRoom = $this->groupChatRoom()->first();
    if (!$groupChatRoom) {
      return User::whereRaw('1 = 0'); // 空のクエリを返す
    }

    return User::whereHas('participations', function ($query) use ($groupChatRoom) {
      $query->where('chat_room_id', $groupChatRoom->id);
    });
  }

  /**
   * ユーザーがグループのメンバーかどうかをチェック
   */
  public function hasMember(int $userId): bool
  {
    $groupChatRoom = $this->groupChatRoom()->first();
    if (!$groupChatRoom) {
      return false;
    }

    return $groupChatRoom->participants()
      ->where('user_id', $userId)
      ->exists();
  }

  /**
   * グループの全参加者
   */
  public function participants()
  {
    return $this->hasManyThrough(
      Participant::class,
      ChatRoom::class,
      'group_id', // chat_roomsのgroup_id
      'chat_room_id', // participantsのchat_room_id
      'id', // groupsのid
      'id' // chat_roomsのid
    );
  }

  /**
   * チャットスタイルの確認
   */
  public function hasGroupChat(): bool
  {
    return in_array('group', $this->chat_styles ?? []);
  }

  public function hasMemberChat(): bool
  {
    return in_array('group_member', $this->chat_styles ?? []);
  }

  /**
   * QRコードトークンを再生成
   */
  public function regenerateQrToken(): void
  {
    do {
      $token = Str::random(32);
    } while (static::where('qr_code_token', $token)->exists());

    $this->update(['qr_code_token' => $token]);
  }

  /**
   * メンバー数制限チェック
   */
  public function canAddMember(): bool
  {
    return $this->getMemberCount() < $this->max_members;
  }

  /**
   * 現在のメンバー数を取得
   */
  public function getMemberCount(): int
  {
    $groupChatRoom = $this->groupChatRoom()->first();
    if (!$groupChatRoom) {
      return 0;
    }

    return $groupChatRoom->participants()->count();
  }
}
