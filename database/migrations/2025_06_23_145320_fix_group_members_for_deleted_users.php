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
    // 削除されたユーザーのGroupMemberレコードを更新
    DB::table('group_members')
      ->join('users', 'group_members.user_id', '=', 'users.id')
      ->where(function ($query) {
        $query->whereNotNull('users.deleted_at')
          ->orWhere('users.is_banned', true);
      })
      ->whereNull('group_members.left_at')
      ->update([
        'group_members.left_at' => DB::raw('users.deleted_at'),
        'group_members.removal_type' => DB::raw("CASE 
                    WHEN users.is_banned = 1 THEN 'kicked_by_admin'
                    WHEN users.deleted_by_self = 1 THEN 'self_leave'
                    ELSE 'kicked_by_admin'
                END"),
        'group_members.removed_by_user_id' => DB::raw("CASE 
                    WHEN users.deleted_by_self = 1 THEN users.id
                    ELSE NULL
                END"),
        'group_members.removed_by_admin_id' => DB::raw("CASE 
                    WHEN users.deleted_by_self = 0 AND users.deleted_by IS NOT NULL THEN users.deleted_by
                    ELSE NULL
                END"),
        'group_members.can_rejoin' => DB::raw("CASE 
                    WHEN users.deleted_by_self = 1 THEN 1
                    ELSE 0
                END"),
      ]);
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // この操作は元に戻すことができません
    // 必要に応じて警告を出力
    echo "Warning: This migration cannot be reversed without data loss.\n";
  }
};
