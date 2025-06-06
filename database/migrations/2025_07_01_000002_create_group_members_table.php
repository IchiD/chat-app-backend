<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('guest_identifier')->nullable();
            $table->string('nickname', 50);
            $table->timestamp('joined_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group_id', 'user_id']);
            $table->index(['group_id', 'is_active']);
            $table->index('guest_identifier');
            $table->unique(['group_id', 'user_id'], 'unique_group_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
