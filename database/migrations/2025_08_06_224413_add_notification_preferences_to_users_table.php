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
        Schema::table('users', function (Blueprint $table) {
            // 通知設定のカラムを追加
            $table->json('notification_preferences')->nullable()->after('friend_id');
        });

        // 既存ユーザーにデフォルト設定を適用
        DB::table('users')->update([
            'notification_preferences' => json_encode([
                'email' => [
                    'messages' => true,
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
                'push' => [
                    'messages' => true,
                    'friend_requests' => true,
                    'group_invites' => true,
                    'group_messages' => true,
                ],
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
