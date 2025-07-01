<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // messages テーブルの更新
    Schema::table('messages', function (Blueprint $table) {
      // 新しいchat_room_idカラムを追加
      $table->foreignId('chat_room_id')->nullable()->after('conversation_id')->constrained('chat_rooms');

      // インデックス追加
      $table->index(['chat_room_id']);
    });

    // participants テーブルの更新
    Schema::table('participants', function (Blueprint $table) {
      // 新しいchat_room_idカラムを追加
      $table->foreignId('chat_room_id')->nullable()->after('conversation_id')->constrained('chat_rooms');

      // インデックス追加
      $table->index(['chat_room_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('messages', function (Blueprint $table) {
      $table->dropForeign(['chat_room_id']);
      $table->dropIndex(['chat_room_id']);
      $table->dropColumn('chat_room_id');
    });

    Schema::table('participants', function (Blueprint $table) {
      $table->dropForeign(['chat_room_id']);
      $table->dropIndex(['chat_room_id']);
      $table->dropColumn('chat_room_id');
    });
  }
};
