<?php

namespace App\Services;

use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Subscription;
use Exception;

class StripeService extends BaseService
{
  private StripeClient $client;

  public function __construct()
  {
    $apiKey = config('services.stripe.secret');
    if (empty($apiKey)) {
      $apiKey = 'sk_test_dummy'; // 一時的なダミーキー
    }
    $this->client = new StripeClient($apiKey);
  }

  /**
   * Checkout セッションを作成しURLを返す
   */
  public function createCheckoutSession(User $user, string $plan): array
  {
    try {
      // 1. 既存のアクティブなサブスクリプションをチェック
      $activeSubscription = Subscription::where('user_id', $user->id)
        ->whereIn('status', ['active', 'trialing', 'past_due'])
        ->first();

      if ($activeSubscription) {
        // 既に同じプランの場合
        if ($activeSubscription->plan === $plan) {
          return $this->errorResponse(
            'subscription_exists',
            '既に同じプランのサブスクリプションがアクティブです'
          );
        }

        // アップグレード（standard → premium）のみ許可
        if ($activeSubscription->plan === 'standard' && $plan === 'premium') {
          // アップグレード処理（将来の実装で詳細化）
          Log::info("User {$user->id} attempting to upgrade from {$activeSubscription->plan} to {$plan}");
        } else {
          // ダウングレードや不正な変更は拒否
          return $this->errorResponse(
            'invalid_plan_change',
            'プラン変更については、サポートまでお問い合わせください'
          );
        }
      }

      // 2. ユーザーの現在のプラン状態をチェック
      if ($user->plan && $user->plan !== 'free') {
        if ($user->plan === $plan) {
          return $this->errorResponse(
            'same_plan',
            "既に{$plan}プランをご利用中です"
          );
        }

        // ダウングレード防止
        if ($user->plan === 'premium' && $plan === 'standard') {
          return $this->errorResponse(
            'downgrade_not_allowed',
            'ダウングレードはサポート経由でのみ可能です'
          );
        }
      }

      // 3. プランの有効性チェック
      $priceId = config("services.stripe.prices.$plan");
      if (empty($priceId)) {
        return $this->errorResponse('invalid_plan', '指定されたプランは存在しません');
      }

      // 4. Stripe Checkoutセッション作成
      $session = $this->client->checkout->sessions->create([
        'customer_email' => $user->email,
        'mode' => 'subscription',
        'line_items' => [
          ['price' => $priceId, 'quantity' => 1],
        ],
        'metadata' => [
          'user_id' => $user->id,
          'plan' => $plan,
          'upgrade_from' => $user->plan ?? 'free',
        ],
        'success_url' => config('app.frontend_url') . '/payment/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => config('app.frontend_url') . '/payment/cancel',
        'allow_promotion_codes' => true, // プロモーションコード対応
      ]);

      // 5. ログ記録
      Log::info("Stripe checkout session created", [
        'user_id' => $user->id,
        'plan' => $plan,
        'session_id' => $session->id,
        'previous_plan' => $user->plan ?? 'free',
      ]);

      return $this->successResponse('session_created', ['url' => $session->url]);
    } catch (Exception $e) {
      Log::error('Stripe create session error: ' . $e->getMessage(), [
        'user_id' => $user->id,
        'plan' => $plan,
        'error' => $e->getMessage(),
      ]);
      return $this->errorResponse('stripe_error', '決済セッションの作成に失敗しました');
    }
  }

  /**
   * Webhook イベント処理
   */
  public function handleWebhook(array $payload): void
  {
    $event = $payload['type'] ?? null;
    $data = $payload['data']['object'] ?? [];

    if ($event === 'checkout.session.completed') {
      if (!empty($data['subscription']) && !empty($data['customer_email'])) {
        $user = User::where('email', $data['customer_email'])->first();
        if ($user) {
          Subscription::updateOrCreate([
            'stripe_subscription_id' => $data['subscription'],
          ], [
            'user_id' => $user->id,
            'stripe_customer_id' => $data['customer'],
            'plan' => $data['metadata']['plan'] ?? 'standard',
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
          ]);
          $user->update([
            'plan' => $data['metadata']['plan'] ?? 'standard',
            'subscription_status' => 'active',
          ]);
        }
      }
    } elseif ($event === 'customer.subscription.updated') {
      $subscription = Subscription::where('stripe_subscription_id', $data['id'])->first();
      if ($subscription) {
        $subscription->update([
          'status' => $data['status'],
          'current_period_end' => now()->setTimestamp($data['current_period_end']),
        ]);
        $subscription->user->update(['subscription_status' => $data['status']]);
      }
    } elseif ($event === 'customer.subscription.deleted') {
      $subscription = Subscription::where('stripe_subscription_id', $data['id'])->first();
      if ($subscription) {
        $subscription->update(['status' => 'canceled']);
        $subscription->user->update(['subscription_status' => 'canceled', 'plan' => 'free']);
      }
    }
  }
}
