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
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE conversations MODIFY COLUMN type ENUM('direct', 'group', 'support') DEFAULT 'direct'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("DELETE FROM conversations WHERE type = 'support'");
            DB::statement("ALTER TABLE conversations MODIFY COLUMN type ENUM('direct', 'group') DEFAULT 'direct'");
        }
    }
};