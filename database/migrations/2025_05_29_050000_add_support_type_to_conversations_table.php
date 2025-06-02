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
        // MySQLのenumを変更するため、直接SQLを実行
        DB::statement("ALTER TABLE conversations MODIFY COLUMN type ENUM('direct', 'group', 'support') DEFAULT 'direct'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は元のenumに戻す
        // 既存の'support'タイプのレコードがあれば削除が必要
        DB::statement("DELETE FROM conversations WHERE type = 'support'");
        DB::statement("ALTER TABLE conversations MODIFY COLUMN type ENUM('direct', 'group') DEFAULT 'direct'");
    }
};