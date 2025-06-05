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
    Schema::table('conversations', function (Blueprint $table) {
      // deleted_at カラムの存在チェック
      if (!Schema::hasColumn('conversations', 'deleted_at')) {
        $table->timestamp('deleted_at')->nullable()->comment('管理者による削除日時');
      }

      // deleted_reason カラムの存在チェック
      if (!Schema::hasColumn('conversations', 'deleted_reason')) {
        $table->string('deleted_reason')->nullable()->comment('削除理由');
      }

      // deleted_by カラムの存在チェック
      if (!Schema::hasColumn('conversations', 'deleted_by')) {
        $table->bigInteger('deleted_by')->unsigned()->nullable()->comment('削除を実行した管理者ID');
      }
    });

    // 外部キー制約の追加（既存チェック）
    Schema::table('conversations', function (Blueprint $table) {
      // 外部キー制約が既に存在するかチェック
      $foreignKeys = Schema::getConnection()->getDoctrineSchemaManager()
        ->listTableForeignKeys('conversations');

      $hasDeletedByForeignKey = false;
      foreach ($foreignKeys as $foreignKey) {
        if (in_array('deleted_by', $foreignKey->getLocalColumns())) {
          $hasDeletedByForeignKey = true;
          break;
        }
      }

      if (!$hasDeletedByForeignKey && Schema::hasColumn('conversations', 'deleted_by')) {
        $table->foreign('deleted_by')->references('id')->on('admins')->onDelete('set null');
      }
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('conversations', function (Blueprint $table) {
      // 外部キー制約の削除（存在チェック付き）
      $foreignKeys = Schema::getConnection()->getDoctrineSchemaManager()
        ->listTableForeignKeys('conversations');

      foreach ($foreignKeys as $foreignKey) {
        if (in_array('deleted_by', $foreignKey->getLocalColumns())) {
          $table->dropForeign(['deleted_by']);
          break;
        }
      }

      // カラムの削除（存在チェック付き）
      if (Schema::hasColumn('conversations', 'deleted_at')) {
        $table->dropColumn('deleted_at');
      }
      if (Schema::hasColumn('conversations', 'deleted_reason')) {
        $table->dropColumn('deleted_reason');
      }
      if (Schema::hasColumn('conversations', 'deleted_by')) {
        $table->dropColumn('deleted_by');
      }
    });
  }
};
