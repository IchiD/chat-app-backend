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
    Schema::table('users', function (Blueprint $table) {
      // チャットや友達申請に使用する固有のIDを追加
      // 6桁のランダム文字列を使用
      $table->string('friend_id', 6)->unique()->nullable()->after('id');

      // 友達申請用の検索インデックスを追加
      $table->index('friend_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      // インデックスと列を削除
      $table->dropIndex(['friend_id']);
      $table->dropColumn('friend_id');
    });
  }
};
