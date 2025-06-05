<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      if (!Schema::hasColumn('users', 'new_email')) {
        $table->string('new_email')->nullable()->after('email');
      }
      if (!Schema::hasColumn('users', 'email_change_token')) {
        $table->string('email_change_token')->nullable()->after('new_email');
      }
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      if (Schema::hasColumn('users', 'new_email')) {
        $table->dropColumn('new_email');
      }
      if (Schema::hasColumn('users', 'email_change_token')) {
        $table->dropColumn('email_change_token');
      }
    });
  }
};
