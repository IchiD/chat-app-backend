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
      // enumに'support_chat'を追加
      DB::statement("ALTER TABLE chat_rooms MODIFY COLUMN type ENUM('group_chat', 'member_chat', 'friend_chat', 'support_chat')");
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // SQLiteの場合はスキーマ変更をスキップ
    if (DB::getDriverName() !== 'sqlite') {
      // enumから'support_chat'を削除
      DB::statement("ALTER TABLE chat_rooms MODIFY COLUMN type ENUM('group_chat', 'member_chat', 'friend_chat')");
    }
  }
};
