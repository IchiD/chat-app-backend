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
    Schema::table('users', function (Blueprint $table) {
      $table->boolean('original_is_verified')->nullable()->after('is_verified')->comment('削除前の認証状態（復元時に使用）');
      $table->timestamp('original_email_verified_at')->nullable()->after('email_verified_at')->comment('削除前の認証日時（復元時に使用）');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn(['original_is_verified', 'original_email_verified_at']);
    });
  }
};
