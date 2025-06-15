<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'action',
    'from_plan',
    'to_plan',
    'stripe_subscription_id',
    'stripe_customer_id',
    'amount',
    'currency',
    'notes',
    'metadata',
  ];

  protected $casts = [
    'amount' => 'decimal:2',
    'metadata' => 'array',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  // アクション定数
  const ACTION_CREATED = 'created';
  const ACTION_UPGRADED = 'upgraded';
  const ACTION_DOWNGRADED = 'downgraded';
  const ACTION_CANCELED = 'canceled';
  const ACTION_RENEWED = 'renewed';
  const ACTION_REACTIVATED = 'reactivated';

  // プラン定数
  const PLAN_FREE = 'free';
  const PLAN_STANDARD = 'standard';
  const PLAN_PREMIUM = 'premium';

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * アクションの日本語表示名を取得
   */
  public function getActionDisplayAttribute(): string
  {
    return match ($this->action) {
      self::ACTION_CREATED => 'プラン開始',
      self::ACTION_UPGRADED => 'アップグレード',
      self::ACTION_DOWNGRADED => 'ダウングレード',
      self::ACTION_CANCELED => 'キャンセル',
      self::ACTION_RENEWED => '更新',
      self::ACTION_REACTIVATED => '再開',
      default => $this->action,
    };
  }

  /**
   * プランの日本語表示名を取得
   */
  public function getPlanDisplayName(string $plan): string
  {
    return match ($plan) {
      self::PLAN_FREE => 'FREE',
      self::PLAN_STANDARD => 'STANDARD',
      self::PLAN_PREMIUM => 'PREMIUM',
      default => strtoupper($plan),
    };
  }

  /**
   * 金額をフォーマットして取得
   */
  public function getFormattedAmountAttribute(): string
  {
    if (!$this->amount) {
      return '¥0';
    }

    return '¥' . number_format($this->amount);
  }

  /**
   * プラン変更の詳細説明を取得
   */
  public function getDescriptionAttribute(): string
  {
    $fromPlan = $this->from_plan ? $this->getPlanDisplayName($this->from_plan) : null;
    $toPlan = $this->getPlanDisplayName($this->to_plan);

    return match ($this->action) {
      self::ACTION_CREATED => "{$toPlan}プランを開始しました",
      self::ACTION_UPGRADED => "{$fromPlan}プランから{$toPlan}プランにアップグレードしました",
      self::ACTION_DOWNGRADED => "{$fromPlan}プランから{$toPlan}プランにダウングレードしました",
      self::ACTION_CANCELED => "{$fromPlan}プランをキャンセルしました",
      self::ACTION_RENEWED => "{$toPlan}プランを更新しました",
      self::ACTION_REACTIVATED => "{$toPlan}プランを再開しました",
      default => "プランを{$toPlan}に変更しました",
    };
  }
}
