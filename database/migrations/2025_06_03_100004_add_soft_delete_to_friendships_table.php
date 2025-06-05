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
    Schema::table('friendships', function (Blueprint $table) {
      if (!Schema::hasColumn('friendships', 'deleted_at')) {
        $table->timestamp('deleted_at')->nullable()->comment('友達関係の削除日時');
      }
      if (!Schema::hasColumn('friendships', 'deleted_reason')) {
        $table->text('deleted_reason')->nullable()->comment('削除理由');
      }
      if (!Schema::hasColumn('friendships', 'deleted_by')) {
        $table->unsignedBigInteger('deleted_by')->nullable()->comment('削除を実行した管理者ID');

        $table->foreign('deleted_by')->references('id')->on('admins')->onDelete('set null');
      }
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('friendships', function (Blueprint $table) {
      if (Schema::hasColumn('friendships', 'deleted_by')) {
        $table->dropForeign(['deleted_by']);
      }
      $columnsToCheck = ['deleted_at', 'deleted_reason', 'deleted_by'];
      foreach ($columnsToCheck as $column) {
        if (Schema::hasColumn('friendships', $column)) {
          $table->dropColumn($column);
        }
      }
    });
  }
};
