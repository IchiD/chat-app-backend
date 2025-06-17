<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('subscription_histories', function (Blueprint $table) {
      $table->string('webhook_event_id')->nullable()->after('stripe_customer_id');
      $table->index('webhook_event_id');
    });
  }

  public function down(): void
  {
    Schema::table('subscription_histories', function (Blueprint $table) {
      $table->dropIndex(['webhook_event_id']);
      $table->dropColumn('webhook_event_id');
    });
  }
};
