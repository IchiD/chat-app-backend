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
    Schema::create('group_members', function (Blueprint $table) {
      $table->id();
      $table->foreignId('group_id')->constrained()->onDelete('cascade');
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->timestamp('joined_at')->nullable();
      $table->timestamp('left_at')->nullable(); // 退出時刻
      $table->enum('role', ['member', 'admin', 'owner'])->default('member'); // グループでの役割
      $table->timestamps();

      // ユニークインデックス（同じユーザーが同じグループに重複参加しないように）
      $table->unique(['group_id', 'user_id']);

      // 検索用インデックス
      $table->index(['user_id', 'group_id']);
      $table->index('group_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('group_members');
  }
};
