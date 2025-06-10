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
    // enumに'support_chat'を追加
    DB::statement("ALTER TABLE chat_rooms MODIFY COLUMN type ENUM('group_chat', 'member_chat', 'friend_chat', 'support_chat')");
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // enumから'support_chat'を削除
    DB::statement("ALTER TABLE chat_rooms MODIFY COLUMN type ENUM('group_chat', 'member_chat', 'friend_chat')");
  }
};
