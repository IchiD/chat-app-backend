<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // SQLiteの場合はスキーマ変更をスキップ
    if (DB::getDriverName() !== 'sqlite') {
      // まず、enumに'friend_chat'を追加
      DB::statement("ALTER TABLE chat_rooms MODIFY COLUMN type ENUM('group_chat', 'member_chat', 'friend_chat')");
    }

    // 既存のmember_chatデータで、group_idがnullのものをfriend_chatに変更
    DB::table('chat_rooms')
      ->where('type', 'member_chat')
      ->whereNull('group_id')
      ->update(['type' => 'friend_chat']);
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // friend_chatをmember_chatに戻す
    DB::table('chat_rooms')
      ->where('type', 'friend_chat')
      ->update(['type' => 'member_chat']);

    // SQLiteの場合はスキーマ変更をスキップ
    if (DB::getDriverName() !== 'sqlite') {
      // enumからfriend_chatを削除
      DB::statement("ALTER TABLE chat_rooms MODIFY COLUMN type ENUM('group_chat', 'member_chat')");
    }
  }
};
