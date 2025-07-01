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
    Schema::table('admin_conversation_reads', function (Blueprint $table) {
      // 新しいchat_room_idカラムを追加
      $table->unsignedBigInteger('chat_room_id')->nullable()->after('conversation_id');

      // chat_room_idに外部キー制約を追加
      $table->foreign('chat_room_id')->references('id')->on('chat_rooms')->onDelete('cascade');

      // conversation_idカラムをnullableにする（互換性のため）
      $table->unsignedBigInteger('conversation_id')->nullable()->change();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('admin_conversation_reads', function (Blueprint $table) {
      // 外部キー制約を削除
      $table->dropForeign(['chat_room_id']);

      // chat_room_idカラムを削除
      $table->dropColumn('chat_room_id');

      // conversation_idをnot nullに戻す
      $table->unsignedBigInteger('conversation_id')->nullable(false)->change();
    });
  }
};
