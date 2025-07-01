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
    Schema::table('group_members', function (Blueprint $table) {
      $table->string('owner_nickname', 100)->nullable()->after('role')
        ->comment('グループオーナーが設定するメンバーのニックネーム（オーナーのみ表示）');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('group_members', function (Blueprint $table) {
      $table->dropColumn('owner_nickname');
    });
  }
};
