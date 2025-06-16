<?php

namespace App\Services;

use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Exception;

class StripeService extends BaseService
{
  private ?StripeClient $client;

  public function __construct()
  {
    $apiKey = config('services.stripe.secret');

    if (empty($apiKey)) {
      throw new Exception('Stripe API key is not configured. Please set STRIPE_SECRET_KEY in your .env file.');
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
      $activeSubscription = $user->activeSubscription();

      if ($activeSubscription) {
        // 既に同じプランの場合
        if ($activeSubscription->plan === $plan) {
          return $this->errorResponse(
            'subscription_exists',
            '既に同じプランのサブスクリプションがアクティブです'
          );
        }

        // プラン変更の許可チェック
        $isUpgrade = ($activeSubscription->plan === 'standard' && $plan === 'premium') ||
          ($activeSubscription->plan === 'free' && in_array($plan, ['standard', 'premium']));
        $isDowngrade = ($activeSubscription->plan === 'premium' && $plan === 'standard');

        // テスト環境ではダウングレードも許可
        $isTestMode = str_starts_with(config('services.stripe.secret'), 'sk_test_');

        if ($isUpgrade) {
          Log::info("User {$user->id} attempting to upgrade from {$activeSubscription->plan} to {$plan}");
        } elseif ($isDowngrade && $isTestMode) {
          Log::info("User {$user->id} attempting to downgrade from {$activeSubscription->plan} to {$plan} (test mode)");
        } else {
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

        // テスト環境ではダウングレードを許可
        if ($user->plan === 'premium' && $plan === 'standard') {
          $isTestMode = str_starts_with(config('services.stripe.secret'), 'sk_test_');
          if (!$isTestMode) {
            return $this->errorResponse(
              'downgrade_not_allowed',
              'ダウングレードはサポート経由でのみ可能です'
            );
          }
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
        'allow_promotion_codes' => true,
      ]);

      // 5. ログ記録
      Log::info("Stripe checkout session created", [
        'user_id' => $user->id,
        'plan' => $plan,
        'session_id' => $session->id,
        'previous_plan' => $user->plan ?? 'free',
        'test_mode' => str_starts_with(config('services.stripe.secret'), 'sk_test_'),
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
          $plan = $data['metadata']['plan'] ?? 'standard';
          $previousPlan = $data['metadata']['upgrade_from'] ?? 'free';

          Subscription::updateOrCreate([
            'stripe_subscription_id' => $data['subscription'],
          ], [
            'user_id' => $user->id,
            'stripe_customer_id' => $data['customer'],
            'plan' => $plan,
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
          ]);

          $user->update([
            'plan' => $plan,
            'subscription_status' => 'active',
          ]);

          // 履歴記録
          $action = $previousPlan === 'free' ? SubscriptionHistory::ACTION_CREATED : SubscriptionHistory::ACTION_UPGRADED;
          $this->recordSubscriptionHistory(
            $user,
            $action,
            $previousPlan !== 'free' ? $previousPlan : null,
            $plan,
            $data['subscription'],
            $data['customer'],
            null, // 金額は後でStripeから取得可能
            'Stripe決済完了による' . ($action === SubscriptionHistory::ACTION_CREATED ? 'プラン開始' : 'アップグレード')
          );
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

        // 履歴記録
        $this->recordSubscriptionHistory(
          $subscription->user,
          SubscriptionHistory::ACTION_CANCELED,
          $subscription->plan,
          'free',
          $subscription->stripe_subscription_id,
          $subscription->stripe_customer_id,
          null,
          'Stripeでのサブスクリプションキャンセル'
        );
      }
    }
  }

  /**
   * ユーザーのサブスクリプション詳細を取得
   */
  public function getSubscriptionDetails(User $user): array
  {
    try {
      $subscription = $user->activeSubscription();

      // サブスクリプションが存在しない場合
      if (!$subscription) {
        return $this->successResponse('no_subscription', [
          'has_subscription' => false,
          'plan' => $user->plan ?? 'free',
          'subscription_status' => $user->subscription_status,
          'current_period_end' => null,
          'next_billing_date' => null,
          'can_cancel' => false,
        ]);
      }

      // Stripeから最新情報を取得
      $stripeSubscription = null;
      $actualPlan = $subscription->plan; // デフォルトはローカルDBの値
      $actualStatus = $subscription->status; // デフォルトはローカルDBの値

      if ($subscription->stripe_subscription_id && $this->client) {
        try {
          $stripeSubscription = $this->client->subscriptions->retrieve($subscription->stripe_subscription_id);

          // Stripeから取得した情報でローカル情報を更新
          if ($stripeSubscription) {
            $actualStatus = $stripeSubscription->status;

            // Stripeのプライス情報からプランを判定
            $priceId = $stripeSubscription->items->data[0]->price->id ?? null;
            if ($priceId) {
              $planFromPrice = $this->getPlanFromPriceId($priceId);
              if ($planFromPrice !== null) {
                $actualPlan = $planFromPrice;
              }
            }

            // ローカルデータベースの情報も更新
            $subscription->update([
              'plan' => $actualPlan,
              'status' => $actualStatus,
              'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]);

            // ユーザーの情報も更新
            $user->update([
              'plan' => $actualPlan,
              'subscription_status' => $actualStatus,
            ]);
          }
        } catch (Exception $e) {
          Log::warning('Stripe subscription retrieval failed', [
            'subscription_id' => $subscription->stripe_subscription_id,
            'error' => $e->getMessage(),
          ]);
        }
      }

      $nextBillingDate = $subscription->current_period_end;
      $canCancel = in_array($actualStatus, ['active', 'trialing']);

      // Stripeから取得した情報で更新
      if ($stripeSubscription) {
        $nextBillingDate = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
        $canCancel = in_array($stripeSubscription->status, ['active', 'trialing']);
      }

      return $this->successResponse('subscription_found', [
        'has_subscription' => true,
        'plan' => $actualPlan, // 実際のプランを返す
        'subscription_status' => $actualStatus, // 実際のステータスを返す
        'current_period_end' => $nextBillingDate,
        'next_billing_date' => $nextBillingDate,
        'can_cancel' => $canCancel,
        'stripe_subscription_id' => $subscription->stripe_subscription_id,
        'stripe_customer_id' => $subscription->stripe_customer_id,
      ]);
    } catch (Exception $e) {
      Log::error('Get subscription details error: ' . $e->getMessage(), [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
      ]);
      return $this->errorResponse('subscription_error', 'サブスクリプション情報の取得に失敗しました');
    }
  }

  /**
   * サブスクリプションをキャンセル
   */
  public function cancelSubscription(User $user): array
  {
    try {
      $subscription = $user->activeSubscription();

      if (!$subscription) {
        return $this->errorResponse('no_subscription', 'アクティブなサブスクリプションがありません');
      }

      if (!in_array($subscription->status, ['active', 'trialing'])) {
        return $this->errorResponse('cannot_cancel', 'このサブスクリプションはキャンセルできません');
      }

      // Stripeでキャンセル実行
      $stripeSubscription = null;
      if ($this->client && $subscription->stripe_subscription_id) {
        try {
          $stripeSubscription = $this->client->subscriptions->update(
            $subscription->stripe_subscription_id,
            ['cancel_at_period_end' => true]
          );
        } catch (Exception $e) {
          Log::warning('Stripe subscription cancellation failed', [
            'subscription_id' => $subscription->stripe_subscription_id,
            'error' => $e->getMessage(),
          ]);
        }
      }

      // ローカルデータベース更新
      $subscription->update([
        'status' => 'canceled',
      ]);

      $user->update([
        'subscription_status' => 'canceled',
      ]);

      // 履歴記録
      $this->recordSubscriptionHistory(
        $user,
        SubscriptionHistory::ACTION_CANCELED,
        $subscription->plan,
        $subscription->plan, // キャンセル時は同じプラン
        $subscription->stripe_subscription_id,
        $subscription->stripe_customer_id,
        null,
        'ユーザーによるキャンセル'
      );

      Log::info("Subscription canceled", [
        'user_id' => $user->id,
        'subscription_id' => $subscription->stripe_subscription_id,
        'cancel_at_period_end' => $stripeSubscription ? $stripeSubscription->cancel_at_period_end : true,
      ]);

      return $this->successResponse('subscription_canceled', [
        'message' => 'サブスクリプションをキャンセルしました。現在の期間終了まで利用可能です。',
        'cancel_at_period_end' => $stripeSubscription ? $stripeSubscription->cancel_at_period_end : true,
        'current_period_end' => $stripeSubscription ?
          \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end) :
          $subscription->current_period_end,
      ]);
    } catch (Exception $e) {
      Log::error('Cancel subscription error: ' . $e->getMessage(), [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
      ]);
      return $this->errorResponse('cancel_error', 'サブスクリプションのキャンセルに失敗しました');
    }
  }

  /**
   * Stripe Price IDからプラン名を取得
   */
  private function getPlanFromPriceId(string $priceId): ?string
  {
    // 設定ファイルから価格IDを取得
    $priceMapping = [
      config('services.stripe.prices.standard') => 'standard',
      config('services.stripe.prices.premium') => 'premium',
    ];

    return $priceMapping[$priceId] ?? null;
  }

  /**
   * サブスクリプション履歴を記録
   */
  private function recordSubscriptionHistory(
    User $user,
    string $action,
    ?string $fromPlan,
    string $toPlan,
    ?string $stripeSubscriptionId = null,
    ?string $stripeCustomerId = null,
    ?float $amount = null,
    ?string $notes = null,
    ?array $metadata = null
  ): void {
    try {
      SubscriptionHistory::create([
        'user_id' => $user->id,
        'action' => $action,
        'from_plan' => $fromPlan,
        'to_plan' => $toPlan,
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_customer_id' => $stripeCustomerId,
        'amount' => $amount,
        'currency' => 'jpy',
        'notes' => $notes,
        'metadata' => $metadata,
      ]);
    } catch (Exception $e) {
      Log::error('Failed to record subscription history', [
        'user_id' => $user->id,
        'action' => $action,
        'error' => $e->getMessage(),
      ]);
    }
  }
}
