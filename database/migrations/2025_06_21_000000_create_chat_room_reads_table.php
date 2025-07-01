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
    Schema::create('chat_room_reads', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->foreignId('chat_room_id')->constrained()->onDelete('cascade');
      $table->timestamp('last_read_at')->nullable();
      $table->timestamps();

      $table->unique(['user_id', 'chat_room_id']);
      $table->index(['user_id', 'last_read_at']);
      $table->index(['chat_room_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('chat_room_reads');
  }
};
