<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->onDelete('cascade');
            $table->string('name', 100)->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('max_members')->default(50);
            $table->string('qr_code_token')->unique();
            $table->timestamps();

            $table->index('owner_user_id');
            $table->index('qr_code_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
