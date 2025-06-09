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
    Schema::create('chat_rooms', function (Blueprint $table) {
      $table->id();
      $table->enum('type', ['group_chat', 'member_chat', 'friend_chat']);
      $table->foreignId('group_id')->nullable()->constrained('groups');
      $table->foreignId('participant1_id')->nullable()->constrained('users');
      $table->foreignId('participant2_id')->nullable()->constrained('users');
      $table->string('room_token', 16)->unique();
      $table->timestamps();

      // インデックス
      $table->index(['type']);
      $table->index(['group_id']);
      $table->index(['participant1_id', 'participant2_id']);
      $table->index(['room_token']);
      $table->index(['created_at']);

      // 制約 - SQLiteの制限により、nullを含むユニーク制約は個別に制御
      // グループ内メンバーチャットの一意性制約
      // $table->unique(['group_id', 'participant1_id', 'participant2_id'], 'unique_member_chat');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('chat_rooms');
  }
};
