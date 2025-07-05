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
    Schema::table('groups', function (Blueprint $table) {
      $table->softDeletes();
      $table->unsignedBigInteger('deleted_by')->nullable();
      $table->string('deleted_reason')->nullable();

      $table->foreign('deleted_by')->references('id')->on('admins');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('groups', function (Blueprint $table) {
      $table->dropForeign(['deleted_by']);
      $table->dropColumn(['deleted_at', 'deleted_by', 'deleted_reason']);
    });
  }
};
