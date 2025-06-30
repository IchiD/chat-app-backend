<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PaymentTransaction;

class Subscription extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'stripe_subscription_id',
    'stripe_customer_id',
    'plan',
    'status',
    'current_period_end',
    'cancel_at_period_end',
  ];

  protected $casts = [
    'current_period_end' => 'datetime',
    'cancel_at_period_end' => 'boolean',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function paymentTransactions(): HasMany
  {
    return $this->hasMany(PaymentTransaction::class);
  }

  /**
   * サブスクリプションがアクティブかどうかを判定
   * active, trialing状態のみをアクティブと見なす
   */
  public function isActive(): bool
  {
    return in_array($this->status, ['active', 'trialing']);
  }

  /**
   * サブスクリプションが期間終了時にキャンセル予定かどうかを判定
   */
  public function willCancelAtPeriodEnd(): bool
  {
    return (bool) $this->cancel_at_period_end;
  }

  /**
   * サブスクリプションが実質的に利用可能かどうかを判定
   * active, trialing, past_due状態を利用可能と見なす
   */
  public function isUsable(): bool
  {
    return in_array($this->status, ['active', 'trialing', 'past_due']);
  }
}
