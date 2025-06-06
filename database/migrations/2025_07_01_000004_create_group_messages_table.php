<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('sender_user_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->enum('target_type', ['all', 'specific_members', 'subgroup'])->default('all');
            $table->json('target_ids')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['group_id', 'created_at']);
            $table->index('sender_user_id');
            $table->index('target_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_messages');
    }
};
