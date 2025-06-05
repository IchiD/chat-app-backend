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
      if (!Schema::hasColumn('messages', 'admin_deleted_at')) {
        $table->timestamp('admin_deleted_at')->nullable()->comment('管理者による削除日時');
      }
      if (!Schema::hasColumn('messages', 'admin_deleted_reason')) {
        $table->string('admin_deleted_reason')->nullable()->comment('管理者による削除理由');
      }
      if (!Schema::hasColumn('messages', 'admin_deleted_by')) {
        $table->bigInteger('admin_deleted_by')->unsigned()->nullable()->comment('削除を実行した管理者ID');

        // 外部キー制約
        $table->foreign('admin_deleted_by')->references('id')->on('admins')->onDelete('set null');
      }
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('messages', function (Blueprint $table) {
      if (Schema::hasColumn('messages', 'admin_deleted_by')) {
        $table->dropForeign(['admin_deleted_by']);
        $table->dropColumn(['admin_deleted_at', 'admin_deleted_reason', 'admin_deleted_by']);
      }
    });
  }
};
