<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVerificationColumnsToUsersTable extends Migration
{
  public function up()
  {
    Schema::table('users', function (Blueprint $table) {
      // 仮登録か本登録かを区別するフラグ。初期値はfalse（仮登録状態）
      $table->boolean('is_verified')->default(false);
      // 認証リンク用トークン（null許容）
      $table->string('email_verification_token')->nullable();
      // トークンの有効期限（null許容）
      $table->timestamp('token_expires_at')->nullable();
    });
  }

  public function down()
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn(['is_verified', 'email_verification_token', 'token_expires_at']);
    });
  }
}
