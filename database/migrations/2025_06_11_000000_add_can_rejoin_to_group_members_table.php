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
      $table->boolean('can_rejoin')->default(true)->after('left_at')
        ->comment('再参加可能フラグ（false=再参加禁止）');

      // 削除理由を記録するためのカラムも追加
      $table->enum('removal_type', ['self_leave', 'kicked_by_owner', 'kicked_by_admin'])
        ->nullable()->after('can_rejoin')
        ->comment('削除理由（self_leave=自主退会, kicked_by_owner=オーナーによる削除, kicked_by_admin=管理者による削除）');

      $table->unsignedBigInteger('removed_by_user_id')->nullable()->after('removal_type')
        ->comment('削除実行者のユーザーID');

      // インデックス追加
      $table->index(['group_id', 'can_rejoin']);
      $table->index(['removed_by_user_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('group_members', function (Blueprint $table) {
      $table->dropIndex(['group_id', 'can_rejoin']);
      $table->dropIndex(['removed_by_user_id']);
      $table->dropColumn(['can_rejoin', 'removal_type', 'removed_by_user_id']);
    });
  }
};
