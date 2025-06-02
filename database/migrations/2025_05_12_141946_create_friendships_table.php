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
    Schema::create('friendships', function (Blueprint $table) {
      $table->id();
      // ユーザーの関係を定義
      $table->unsignedBigInteger('user_id');  // 友達申請を送ったユーザー
      $table->unsignedBigInteger('friend_id'); // 友達申請を受けたユーザー

      // ステータス: 0=申請中, 1=承認済み, 2=拒否
      $table->tinyInteger('status')->default(0);

      // 申請メッセージ（オプション）
      $table->string('message')->nullable();

      $table->timestamps();

      // 同じユーザー間の重複友達関係を防ぐ
      $table->unique(['user_id', 'friend_id']);

      // 外部キー制約
      $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
      $table->foreign('friend_id')->references('id')->on('users')->onDelete('cascade');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('friendships');
  }
};
