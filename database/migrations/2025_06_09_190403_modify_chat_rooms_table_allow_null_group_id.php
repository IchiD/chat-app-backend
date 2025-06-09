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
    Schema::table('chat_rooms', function (Blueprint $table) {
      // 既存のユニーク制約を削除
      $table->dropUnique('unique_member_chat');

      // group_idカラムをnullableに変更
      $table->foreignId('group_id')->nullable()->change();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('chat_rooms', function (Blueprint $table) {
      // group_idカラムをnot nullに戻す
      $table->foreignId('group_id')->nullable(false)->change();

      // ユニーク制約を復元
      $table->unique(['group_id', 'participant1_id', 'participant2_id'], 'unique_member_chat');
    });
  }
};
