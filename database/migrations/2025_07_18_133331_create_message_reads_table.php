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
    Schema::create('message_reads', function (Blueprint $table) {
      $table->id();
      $table->foreignId('message_id')->constrained()->onDelete('cascade');
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->timestamp('read_at')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->timestamps();

      // ユニーク制約：同じユーザーが同じメッセージを複数回既読にできないように
      $table->unique(['message_id', 'user_id']);

      // インデックス：パフォーマンスの最適化
      $table->index(['message_id']);
      $table->index(['user_id', 'message_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('message_reads');
  }
};
