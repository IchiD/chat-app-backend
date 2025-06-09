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
    Schema::create('groups', function (Blueprint $table) {
      $table->id();
      $table->string('name', 100);
      $table->text('description')->nullable();
      $table->integer('max_members')->default(50);
      $table->json('chat_styles'); // ['group', 'group_member']
      $table->foreignId('owner_user_id')->constrained('users');
      $table->string('qr_code_token', 32)->unique();
      $table->timestamps();

      // インデックス
      $table->index(['owner_user_id']);
      $table->index(['qr_code_token']);
      $table->index(['created_at']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('groups');
  }
};
