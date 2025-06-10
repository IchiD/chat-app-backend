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
    Schema::create('admin_conversation_reads', function (Blueprint $table) {
      $table->id();
      $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
      $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
      $table->timestamp('last_read_at');
      $table->timestamps();

      $table->unique(['admin_id', 'conversation_id']);
      $table->index(['admin_id', 'last_read_at']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('admin_conversation_reads');
  }
};
