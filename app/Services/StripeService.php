<?php

namespace App\Services;

use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\WebhookLog;
use App\Models\PaymentTransaction;
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

        if ($isUpgrade) {
          Log::info("User {$user->id} attempting to upgrade from {$activeSubscription->plan} to {$plan}");
          // アップグレードの場合は既存サブスクリプションを更新
          return $this->upgradeSubscription($user, $activeSubscription, $plan);
        } elseif ($isDowngrade) {
          Log::info("User {$user->id} attempting to downgrade from {$activeSubscription->plan} to {$plan}");
          // ダウングレードも既存サブスクリプションを更新
          return $this->upgradeSubscription($user, $activeSubscription, $plan);
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

        // ダウングレードを許可
        // 以前はテスト環境のみの制限がありましたが、本番環境でも許可します
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
   * 既存サブスクリプションのアップグレード/ダウングレード
   */
  private function upgradeSubscription(User $user, Subscription $subscription, string $newPlan): array
  {
    try {
      // 新しいプランのプライスIDを取得
      $newPriceId = config("services.stripe.prices.$newPlan");
      if (empty($newPriceId)) {
        return $this->errorResponse('invalid_plan', '指定されたプランは存在しません');
      }

      // Stripeサブスクリプションを取得
      $stripeSubscription = $this->client->subscriptions->retrieve($subscription->stripe_subscription_id);

      if (!$stripeSubscription) {
        return $this->errorResponse('subscription_not_found', 'Stripeサブスクリプションが見つかりません');
      }

      // サブスクリプションアイテムの更新（差額請求あり）
      $this->client->subscriptions->update($subscription->stripe_subscription_id, [
        'items' => [
          [
            'id' => $stripeSubscription->items->data[0]->id,
            'price' => $newPriceId,
          ],
        ],
        'proration_behavior' => 'always_invoice', // 差額を即座に請求
      ]);

      // 履歴記録（更新前にプランを記録）
      $oldPlan = $subscription->plan;

      $this->recordSubscriptionHistory(
        $user,
        SubscriptionHistory::ACTION_UPGRADED,
        $oldPlan,
        $newPlan,
        $subscription->stripe_subscription_id,
        $subscription->stripe_customer_id,
        null, // 金額は後でinvoice.payment_succeededで記録
        'プランアップグレード - 差額請求'
      );

      // ローカルデータベースの更新
      $subscription->update([
        'plan' => $newPlan,
      ]);

      $user->update([
        'plan' => $newPlan,
      ]);

      Log::info("Subscription upgraded successfully", [
        'user_id' => $user->id,
        'subscription_id' => $subscription->stripe_subscription_id,
        'from_plan' => $subscription->plan,
        'to_plan' => $newPlan,
      ]);

      return $this->successResponse('subscription_upgraded', [
        'message' => 'プランが正常に変更されました。差額はすぐに請求されます。',
        'new_plan' => $newPlan,
      ]);
    } catch (Exception $e) {
      Log::error('Subscription upgrade failed', [
        'user_id' => $user->id,
        'subscription_id' => $subscription->stripe_subscription_id,
        'new_plan' => $newPlan,
        'error' => $e->getMessage(),
      ]);
      return $this->errorResponse('upgrade_failed', 'プラン変更に失敗しました: ' . $e->getMessage());
    }
  }

  /**
   * Webhook イベント処理
   */
  public function handleWebhook(array $payload): void
  {
    $event = $payload['type'] ?? null;
    $data = $payload['data']['object'] ?? [];
    $eventId = $payload['id'] ?? 'unknown';

    // 既存のWebhookログを確認、なければ作成
    $webhookLog = WebhookLog::where('stripe_event_id', $eventId)->first();

    if (!$webhookLog) {
      $webhookLog = WebhookLog::create([
        'stripe_event_id' => $eventId,
        'event_type' => $event ?? 'unknown',
        'payload' => $payload,
        'status' => 'pending'
      ]);
    } else {
      // 既存のログを再処理用に更新
      $webhookLog->update([
        'status' => 'pending',
        'error_message' => null,
        'processed_at' => null
      ]);
    }

    try {
      if ($event === 'checkout.session.completed') {
        if (!empty($data['subscription']) && !empty($data['customer_email'])) {
          $user = User::where('email', $data['customer_email'])->first();
          if ($user) {
            $plan = $data['metadata']['plan'] ?? 'standard';
            $previousPlan = $data['metadata']['upgrade_from'] ?? 'free';

            // サブスクリプションの作成・更新
            $subscription = Subscription::updateOrCreate([
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

            // PaymentTransaction の記録
            $paymentIntentId = $data['payment_intent'] ?? null;
            $sessionId = $data['id'];

            // サブスクリプションの場合、payment_intentがnullになることがあるので
            // セッションIDをベースにしたユニークなIDを作成
            $uniquePaymentId = $paymentIntentId ?: 'session_' . $sessionId;

            // 既存のPaymentTransactionが存在するかチェック
            $existingTransaction = PaymentTransaction::where('stripe_payment_intent_id', $uniquePaymentId)
              ->orWhere('metadata->session_id', $sessionId)
              ->first();

            if (!$existingTransaction) {
              PaymentTransaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $uniquePaymentId,
                'stripe_charge_id' => $data['charges']['data'][0]['id'] ?? null,
                'amount' => ($data['amount_total'] ?? 0) / 100, // Stripeはcents単位
                'currency' => $data['currency'] ?? 'jpy',
                'status' => 'succeeded',
                'type' => 'subscription',
                'paid_at' => now(),
                'metadata' => [
                  'session_id' => $sessionId,
                  'customer_email' => $data['customer_email'],
                  'plan' => $plan,
                  'stripe_subscription_id' => $data['subscription'],
                  'is_subscription_payment' => true
                ]
              ]);
            } else {
              Log::info('PaymentTransaction already exists, skipping creation', [
                'session_id' => $sessionId,
                'existing_id' => $existingTransaction->id,
                'user_id' => $user->id
              ]);
            }

            // 履歴記録
            $action = $previousPlan === 'free' ? SubscriptionHistory::ACTION_CREATED : SubscriptionHistory::ACTION_UPGRADED;
            $this->recordSubscriptionHistory(
              $user,
              $action,
              $previousPlan !== 'free' ? $previousPlan : null,
              $plan,
              $data['subscription'],
              $data['customer'],
              ($data['amount_total'] ?? 0) / 100,
              'Stripe決済完了による' . ($action === SubscriptionHistory::ACTION_CREATED ? 'プラン開始' : 'プラン変更'),
              null,
              $eventId // Webhook Event IDを渡す
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
            'Stripeでのサブスクリプションキャンセル',
            null,
            $eventId // Webhook Event IDを渡す
          );
        }
      } elseif ($event === 'invoice.payment_succeeded') {
        // アップグレード時の差額請求を処理
        $invoiceId = $data['id'] ?? null;
        $subscriptionId = $data['subscription'] ?? null;
        $customerEmail = $data['customer_email'] ?? null;
        // 日割り計算が適用された場合、totalの方が正確な請求額
        $amountPaid = ($data['total'] ?? $data['amount_paid'] ?? 0) / 100; // セントから円に変換
        $billingReason = $data['billing_reason'] ?? null;

        if ($subscriptionId && $customerEmail && in_array($billingReason, ['subscription_cycle', 'subscription_update'])) {
          // 月次請求、アップグレード時の請求を処理（新規作成はcheckout.session.completedで処理済み）
          $user = User::where('email', $customerEmail)->first();
          $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

          if ($user && $subscription) {
            // 重複チェック
            $existingTransaction = PaymentTransaction::where('stripe_payment_intent_id', 'invoice_' . $invoiceId)
              ->orWhere('metadata->invoice_id', $invoiceId)
              ->first();

            if (!$existingTransaction) {
              PaymentTransaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => 'invoice_' . $invoiceId,
                'stripe_charge_id' => $data['charge'] ?? null,
                'amount' => $amountPaid,
                'currency' => $data['currency'] ?? 'jpy',
                'status' => 'succeeded',
                'type' => 'subscription',
                'paid_at' => now(),
                'metadata' => [
                  'invoice_id' => $invoiceId,
                  'customer_email' => $customerEmail,
                  'plan' => $subscription->plan,
                  'stripe_subscription_id' => $subscriptionId,
                  'billing_reason' => $billingReason,
                  'is_upgrade_payment' => true
                ]
              ]);

              Log::info('PaymentTransaction created for invoice payment', [
                'invoice_id' => $invoiceId,
                'user_id' => $user->id,
                'amount' => $amountPaid,
                'billing_reason' => $billingReason
              ]);
            }
          }
        }
      }

      // 成功時のログ更新
      $webhookLog->update([
        'status' => 'processed',
        'processed_at' => now()
      ]);
    } catch (Exception $e) {
      // エラー時のログ更新
      $webhookLog->update([
        'status' => 'failed',
        'error_message' => $e->getMessage()
      ]);

      Log::error('Webhook processing failed', [
        'event_type' => $event,
        'error' => $e->getMessage(),
        'payload' => $payload
      ]);

      throw $e;
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
    ?array $metadata = null,
    ?string $webhookEventId = null
  ): void {
    try {
      // Webhook Event IDベースの冪等性チェック（最も確実）
      if ($webhookEventId) {
        $existingHistory = SubscriptionHistory::where('webhook_event_id', $webhookEventId)->first();
        if ($existingHistory) {
          Log::info('Subscription history already exists for webhook event, skipping creation', [
            'webhook_event_id' => $webhookEventId,
            'existing_id' => $existingHistory->id,
            'user_id' => $user->id,
          ]);
          return;
        }
      }

      // フォールバック: 業務ロジックベースの重複チェック
      $existingHistory = SubscriptionHistory::where('user_id', $user->id)
        ->where('action', $action)
        ->where('to_plan', $toPlan)
        ->where('stripe_subscription_id', $stripeSubscriptionId)
        ->where('created_at', '>=', now()->subMinutes(5)) // 緊急時のフォールバック
        ->first();

      if ($existingHistory) {
        Log::info('Subscription history already exists (fallback check), skipping creation', [
          'user_id' => $user->id,
          'action' => $action,
          'existing_id' => $existingHistory->id,
        ]);
        return;
      }

      SubscriptionHistory::create([
        'user_id' => $user->id,
        'action' => $action,
        'from_plan' => $fromPlan,
        'to_plan' => $toPlan,
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_customer_id' => $stripeCustomerId,
        'webhook_event_id' => $webhookEventId,
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

  /**
   * 管理者によるStripeサブスクリプションのキャンセル
   */
  public function cancelSubscriptionAdmin(string $subscriptionId)
  {
    return $this->client->subscriptions->cancel($subscriptionId);
  }

  /**
   * 管理者によるStripeサブスクリプションの再開
   */
  public function resumeSubscriptionAdmin(string $subscriptionId)
  {
    return $this->client->subscriptions->update($subscriptionId, [
      'cancel_at_period_end' => false,
    ]);
  }

  /**
   * 支払いの返金を実行
   */
  public function refundPayment(string $chargeId, ?int $amount = null)
  {
    $params = ['charge' => $chargeId];

    if ($amount) {
      $params['amount'] = $amount;
    }

    return $this->client->refunds->create($params);
  }
}
