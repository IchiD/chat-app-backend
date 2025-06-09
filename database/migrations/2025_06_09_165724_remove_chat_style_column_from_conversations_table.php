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
      $table->dropColumn('chat_style');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('conversations', function (Blueprint $table) {
      $table->enum('chat_style', ['group_chat', 'member_chat'])->nullable()->after('max_members');
    });
  }
};
