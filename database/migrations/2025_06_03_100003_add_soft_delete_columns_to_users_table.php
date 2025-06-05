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
      if (!Schema::hasColumn('users', 'deleted_at')) {
        $table->timestamp('deleted_at')->nullable()->comment('管理者による削除日時');
      }
      if (!Schema::hasColumn('users', 'deleted_reason')) {
        $table->string('deleted_reason')->nullable()->comment('削除理由');
      }
      if (!Schema::hasColumn('users', 'deleted_by')) {
        $table->bigInteger('deleted_by')->unsigned()->nullable()->comment('削除を実行した管理者ID');

        // 外部キー制約
        $table->foreign('deleted_by')->references('id')->on('admins')->onDelete('set null');
      }
      if (!Schema::hasColumn('users', 'is_banned')) {
        $table->boolean('is_banned')->default(false)->comment('バン状態（同じメールアドレスでの再登録不可）');
      }
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      if (Schema::hasColumn('users', 'deleted_by')) {
        $table->dropForeign(['deleted_by']);
      }
      $columnsToCheck = ['deleted_at', 'deleted_reason', 'deleted_by', 'is_banned'];
      foreach ($columnsToCheck as $column) {
        if (Schema::hasColumn('users', $column)) {
          $table->dropColumn($column);
        }
      }
    });
  }
};
