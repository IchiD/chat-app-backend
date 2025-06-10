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
      $table->timestamp('deleted_at')->nullable();
      $table->unsignedBigInteger('deleted_by')->nullable();
      $table->text('deleted_reason')->nullable();

      $table->foreign('deleted_by')->references('id')->on('admins')->onDelete('set null');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('chat_rooms', function (Blueprint $table) {
      $table->dropForeign(['deleted_by']);
      $table->dropColumn(['deleted_at', 'deleted_by', 'deleted_reason']);
    });
  }
};
