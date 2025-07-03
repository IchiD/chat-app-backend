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
    Schema::create('admin_chat_reads', function (Blueprint $table) {
      $table->id();
      $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
      $table->foreignId('chat_room_id')->constrained('chat_rooms')->onDelete('cascade');
      $table->timestamp('last_read_at')->nullable();
      $table->timestamps();

      $table->unique(['admin_id', 'chat_room_id']);
      $table->index(['admin_id', 'last_read_at']);
      $table->index(['chat_room_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('admin_chat_reads');
  }
};