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
        Schema::table('messages', function (Blueprint $table) {
            // 外部キー制約を一時的に削除
            $table->dropForeign(['sender_id']);
            
            // sender_idをnullableに変更
            $table->unsignedBigInteger('sender_id')->nullable()->change();
            
            // 外部キー制約を再追加（nullableで）
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // 外部キー制約を削除
            $table->dropForeign(['sender_id']);
            
            // null値のレコードがある場合は削除（管理者メッセージ）
            DB::table('messages')->whereNull('sender_id')->delete();
            
            // sender_idをNOT NULLに戻す
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();
            
            // 外部キー制約を再追加
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};