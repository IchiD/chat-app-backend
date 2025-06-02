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
    Schema::table('messages', function (Blueprint $table) {
      // 管理者からのメッセージを識別するためのカラムを追加
      $table->unsignedBigInteger('admin_sender_id')->nullable()->after('sender_id');

      // 外部キー制約
      $table->foreign('admin_sender_id')->references('id')->on('admins')->onDelete('set null');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('messages', function (Blueprint $table) {
      $table->dropForeign(['admin_sender_id']);
      $table->dropColumn('admin_sender_id');
    });
  }
};
