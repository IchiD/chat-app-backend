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
    Schema::create('messages', function (Blueprint $table) {
      $table->id();
      $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
      $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
      $table->enum('content_type', ['text'])->default('text'); // MVPはtextのみ
      $table->text('text_content');
      $table->timestamp('sent_at')->useCurrent();
      $table->timestamps();
      $table->timestamp('edited_at')->nullable();
      $table->timestamp('deleted_at')->nullable(); // 論理削除用
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('messages');
  }
};
