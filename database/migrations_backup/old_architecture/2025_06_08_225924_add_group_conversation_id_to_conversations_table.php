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
      $table->unsignedBigInteger('group_conversation_id')->nullable()->after('qr_code_token');
      $table->foreign('group_conversation_id')->references('id')->on('conversations')->onDelete('cascade');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('conversations', function (Blueprint $table) {
      $table->dropForeign(['group_conversation_id']);
      $table->dropColumn('group_conversation_id');
    });
  }
};
