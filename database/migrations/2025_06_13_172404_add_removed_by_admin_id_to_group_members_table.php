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
      $table->unsignedBigInteger('removed_by_admin_id')->nullable()->after('removed_by_user_id');
      $table->foreign('removed_by_admin_id')->references('id')->on('admins')->onDelete('set null');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('group_members', function (Blueprint $table) {
      $table->dropForeign(['removed_by_admin_id']);
      $table->dropColumn('removed_by_admin_id');
    });
  }
};
