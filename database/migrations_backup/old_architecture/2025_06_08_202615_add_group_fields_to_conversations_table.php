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
    Schema::table('conversations', function (Blueprint $table) {
      // グループタイプの場合のみ使用するフィールド
      $table->string('name', 100)->nullable()->after('type'); // グループ名
      $table->text('description')->nullable()->after('name'); // グループ説明
      $table->unsignedInteger('max_members')->nullable()->default(50)->after('description'); // 最大メンバー数
      $table->foreignId('owner_user_id')->nullable()->constrained('users')->onDelete('cascade')->after('max_members'); // グループオーナー
      $table->string('qr_code_token')->nullable()->unique()->after('owner_user_id'); // QRコードトークン

      // インデックス追加
      $table->index('owner_user_id');
      $table->index('qr_code_token');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('conversations', function (Blueprint $table) {
      $table->dropForeign(['owner_user_id']);
      $table->dropIndex(['owner_user_id']);
      $table->dropIndex(['qr_code_token']);
      $table->dropColumn([
        'name',
        'description',
        'max_members',
        'owner_user_id',
        'qr_code_token'
      ]);
    });
  }
};
