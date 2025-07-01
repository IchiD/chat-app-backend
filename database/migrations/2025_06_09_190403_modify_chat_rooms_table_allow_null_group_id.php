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
      // group_idカラムは既にnullableなので、制約削除のみ実行
      // unique_member_chat制約は最初から作成されていないため、削除処理はスキップ
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('chat_rooms', function (Blueprint $table) {
      // 元々nullableで制約もないため、何もしない
    });
  }
};
