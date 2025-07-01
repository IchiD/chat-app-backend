<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'subscription_id',
    'stripe_payment_intent_id',
    'stripe_charge_id',
    'amount',
    'currency',
    'status',
    'type',
    'refund_amount',
    'metadata',
    'paid_at',
  ];

  protected $casts = [
    'amount' => 'decimal:2',
    'refund_amount' => 'decimal:2',
    'metadata' => 'array',
    'paid_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function subscription()
  {
    return $this->belongsTo(Subscription::class);
  }

  public function scopeSucceeded($query)
  {
    return $query->where('status', 'succeeded');
  }

  public function scopeThisMonth($query)
  {
    return $query->whereYear('created_at', now()->year)
      ->whereMonth('created_at', now()->month);
  }

  public function getFormattedAmountAttribute()
  {
    // 日本円の場合、現在decimal保存なので×100して正しい円金額にする
    if (strtolower($this->currency) === 'jpy') {
      $amount = $this->amount * 100;
      return '¥' . number_format($amount, 0);
    }

    // その他の通貨の場合は小数点表示
    return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
  }

  public function getAmountInYenAttribute()
  {
    return $this->amount * 100; // decimal保存なので×100
  }

  public function getNetAmountAttribute()
  {
    return $this->amount - $this->refund_amount;
  }

  /**
   * metadataから決済時のプラン情報を取得
   * この方法で、アップグレード後でも過去の決済時の正しいプランが表示される
   */
  public function getPlanAtPaymentAttribute()
  {
    return $this->metadata['plan'] ?? ($this->subscription ? $this->subscription->plan : null);
  }
}
