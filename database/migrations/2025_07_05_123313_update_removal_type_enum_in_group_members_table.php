<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // MySQLのENUM型を更新するには、カラムを再定義する必要があります
    DB::statement("ALTER TABLE group_members MODIFY removal_type ENUM('self_leave', 'kicked_by_owner', 'kicked_by_admin', 'user_deleted', 'user_self_deleted') COMMENT '削除理由（self_leave=自主退会, kicked_by_owner=オーナーによる削除, kicked_by_admin=管理者による削除, user_deleted=ユーザー削除による自動削除, user_self_deleted=ユーザー自己削除による自動削除）'");
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // ロールバック時は元のenum定義に戻す
    DB::statement("ALTER TABLE group_members MODIFY removal_type ENUM('self_leave', 'kicked_by_owner', 'kicked_by_admin') COMMENT '削除理由（self_leave=自主退会, kicked_by_owner=オーナーによる削除, kicked_by_admin=管理者による削除）'");
  }
};
