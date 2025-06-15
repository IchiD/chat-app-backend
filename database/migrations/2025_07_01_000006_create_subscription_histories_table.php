<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('subscription_histories', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
      $table->string('action'); // 'created', 'upgraded', 'downgraded', 'canceled', 'renewed'
      $table->enum('from_plan', ['free', 'standard', 'premium'])->nullable();
      $table->enum('to_plan', ['free', 'standard', 'premium']);
      $table->string('stripe_subscription_id')->nullable();
      $table->string('stripe_customer_id')->nullable();
      $table->decimal('amount', 10, 2)->nullable(); // 金額
      $table->string('currency', 3)->default('jpy'); // 通貨
      $table->text('notes')->nullable(); // 備考
      $table->json('metadata')->nullable(); // 追加データ
      $table->timestamps();

      $table->index('user_id');
      $table->index(['user_id', 'action']);
      $table->index(['user_id', 'created_at']);
      $table->index('stripe_subscription_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('subscription_histories');
  }
};
