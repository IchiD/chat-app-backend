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
      DB::statement("ALTER TABLE chat_rooms MODIFY COLUMN type ENUM('member_chat', 'friend_chat', 'group_chat', 'support_chat') DEFAULT 'member_chat'");
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // SQLiteの場合はスキーマ変更をスキップ
    if (DB::getDriverName() !== 'sqlite') {
      DB::statement("ALTER TABLE chat_rooms MODIFY COLUMN type ENUM('member_chat', 'friend_chat', 'group_chat') DEFAULT 'member_chat'");
    }
  }
};
