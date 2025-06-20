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
        'cancel_at_period_end' => false, // プラン変更時は必ずキャンセル予定を解除
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
        'cancel_at_period_end' => false, // プラン変更時はキャンセル予定を解除
      ]);

      $user->update([
        'plan' => $newPlan,
        'subscription_status' => 'active', // プラン変更時は必ずactiveに戻す
      ]);

      Log::info("Subscription upgraded successfully", [
        'user_id' => $user->id,
        'subscription_id' => $subscription->stripe_subscription_id,
        'from_plan' => $oldPlan,
        'to_plan' => $newPlan,
        'previous_subscription_status' => $user->subscription_status,
        'new_subscription_status' => 'active',
        'cancel_at_period_end_set_to_false' => true,
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

            // 重複チェックを強化（3Dセキュア対応）
            $existingTransaction = PaymentTransaction::where(function ($query) use ($uniquePaymentId, $sessionId, $paymentIntentId) {
              $query->where('stripe_payment_intent_id', $uniquePaymentId)
                ->orWhere('metadata->session_id', $sessionId);

              // PaymentIntentIDが存在する場合（3Dセキュア等）、そのIDでも検索
              if ($paymentIntentId) {
                $query->orWhere('stripe_payment_intent_id', $paymentIntentId)
                  ->orWhere('metadata->payment_intent_id', $paymentIntentId);
              }
            })->first();

            if (!$existingTransaction) {
              // 3Dセキュアの場合、payment_intent.succeededで更新されることを想定してstatusを適切に設定
              $status = $paymentIntentId ? 'succeeded' : 'succeeded'; // 3Dセキュアでも基本的にはsucceeded

              $transactionMetadata = [
                'session_id' => $sessionId,
                'customer_email' => $data['customer_email'],
                'plan' => $plan,
                'stripe_subscription_id' => $data['subscription'],
                'is_subscription_payment' => true,
                'checkout_session_completed' => true
              ];

              // PaymentIntentIDがある場合（3Dセキュア等）、メタデータに追加
              if ($paymentIntentId) {
                $transactionMetadata['payment_intent_id'] = $paymentIntentId;
                $transactionMetadata['requires_3ds_confirmation'] = true;
              }

              PaymentTransaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $uniquePaymentId,
                'stripe_charge_id' => $data['charges']['data'][0]['id'] ?? null,
                'amount' => ($data['amount_total'] ?? 0) / 100, // Stripeはcents単位
                'currency' => $data['currency'] ?? 'jpy',
                'status' => $status,
                'type' => 'subscription',
                'paid_at' => now(),
                'metadata' => $transactionMetadata
              ]);

              Log::info('PaymentTransaction created from checkout.session.completed', [
                'session_id' => $sessionId,
                'payment_intent_id' => $paymentIntentId,
                'unique_payment_id' => $uniquePaymentId,
                'user_id' => $user->id,
                'requires_3ds' => !empty($paymentIntentId)
              ]);
            } else {
              Log::info('PaymentTransaction already exists, skipping creation', [
                'session_id' => $sessionId,
                'existing_id' => $existingTransaction->id,
                'existing_payment_intent_id' => $existingTransaction->stripe_payment_intent_id,
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
          // Stripeからのcancel_at_period_endフラグ
          $cancelAtPeriodEnd = $data['cancel_at_period_end'] ?? false;

          // サブスクリプションの基本情報を更新
          $subscription->update([
            'status' => $data['status'],
            'current_period_end' => now()->setTimestamp($data['current_period_end']),
            'cancel_at_period_end' => $cancelAtPeriodEnd,
          ]);

          // Stripeのプライス情報からプラン変更を検出
          $priceId = $data['items']['data'][0]['price']['id'] ?? null;
          if ($priceId) {
            $newPlan = $this->getPlanFromPriceId($priceId);
            if ($newPlan && $newPlan !== $subscription->plan) {
              // プラン変更が検出された場合
              Log::info('Plan change detected in webhook', [
                'subscription_id' => $subscription->stripe_subscription_id,
                'old_plan' => $subscription->plan,
                'new_plan' => $newPlan,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
              ]);
              $subscription->update(['plan' => $newPlan]);
            }
          }

          // ユーザーステータスの更新ロジック
          if ($cancelAtPeriodEnd) {
            // キャンセル予定の場合はwill_cancelに設定
            Log::info('Setting user to will_cancel status', [
              'user_id' => $subscription->user->id,
              'subscription_id' => $subscription->stripe_subscription_id,
              'cancel_at_period_end' => $cancelAtPeriodEnd,
            ]);
            $subscription->user->update([
              'plan' => $subscription->plan,
              'subscription_status' => 'will_cancel',
            ]);
          } else {
            // キャンセルが解除された場合（プラン変更等）はactiveに戻す
            Log::info('Setting user to active status', [
              'user_id' => $subscription->user->id,
              'subscription_id' => $subscription->stripe_subscription_id,
              'cancel_at_period_end' => $cancelAtPeriodEnd,
              'previous_status' => $subscription->user->subscription_status,
            ]);
            $subscription->user->update([
              'plan' => $subscription->plan,
              'subscription_status' => $data['status'], // activeになる
            ]);
          }
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

        if ($subscriptionId && $customerEmail) {
          $user = User::where('email', $customerEmail)->first();
          $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

          if ($user && $subscription) {
            // より包括的な重複チェック（3Dセキュア対応）
            $existingTransaction = PaymentTransaction::where(function ($query) use ($invoiceId, $subscriptionId, $user, $amountPaid) {
              // 1. invoice ID ベースのチェック
              $query->where('stripe_payment_intent_id', 'invoice_' . $invoiceId)
                ->orWhere('metadata->invoice_id', $invoiceId);

              // 2. 同じユーザー・金額・時間範囲での重複チェック（3Dセキュア等）
              $query->orWhere(function ($subQuery) use ($user, $amountPaid, $subscriptionId) {
                $subQuery->where('user_id', $user->id)
                  ->where('amount', $amountPaid)
                  ->where('status', 'succeeded')
                  ->where('created_at', '>=', now()->subMinutes(5)) // 直近5分以内
                  ->where(function ($metaQuery) use ($subscriptionId) {
                    $metaQuery->where('metadata->stripe_subscription_id', $subscriptionId)
                      ->orWhereJsonContains('metadata->stripe_subscription_id', $subscriptionId);
                  });
              });
            })->first();

            if ($existingTransaction) {
              Log::info('PaymentTransaction already exists for invoice payment, skipping creation', [
                'invoice_id' => $invoiceId,
                'existing_transaction_id' => $existingTransaction->id,
                'existing_payment_intent_id' => $existingTransaction->stripe_payment_intent_id,
                'user_id' => $user->id,
                'billing_reason' => $billingReason,
                'subscription_creation_detected' => $billingReason === 'subscription_create'
              ]);
            } elseif (in_array($billingReason, ['subscription_cycle', 'subscription_update'])) {
              // 月次請求、アップグレード時の請求のみを処理（新規作成は除外）
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
            } else {
              Log::info('Skipping PaymentTransaction creation for invoice payment - not a recurring payment', [
                'invoice_id' => $invoiceId,
                'user_id' => $user->id,
                'billing_reason' => $billingReason,
                'skip_reason' => 'Initial subscription payment already handled by checkout.session.completed'
              ]);
            }
          }
        }
      } elseif ($event === 'invoice.payment_failed') {
        // 決済失敗時の処理
        $invoiceId = $data['id'] ?? null;
        $subscriptionId = $data['subscription'] ?? null;
        $customerEmail = $data['customer_email'] ?? null;
        $attemptCount = $data['attempt_count'] ?? 1;
        $amountDue = ($data['amount_due'] ?? 0) / 100; // セントから円に変換

        if ($subscriptionId && $customerEmail) {
          $user = User::where('email', $customerEmail)->first();
          $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

          if ($user && $subscription) {
            // 決済失敗のトランザクション記録
            $existingTransaction = PaymentTransaction::where('stripe_payment_intent_id', 'failed_invoice_' . $invoiceId)->first();

            if (!$existingTransaction) {
              PaymentTransaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => 'failed_invoice_' . $invoiceId,
                'stripe_charge_id' => null,
                'amount' => $amountDue,
                'currency' => $data['currency'] ?? 'jpy',
                'status' => 'failed',
                'type' => 'subscription',
                'paid_at' => null,
                'metadata' => [
                  'invoice_id' => $invoiceId,
                  'customer_email' => $customerEmail,
                  'plan' => $subscription->plan,
                  'stripe_subscription_id' => $subscriptionId,
                  'attempt_count' => $attemptCount,
                  'payment_failure' => true,
                  'failure_reason' => 'invoice_payment_failed'
                ]
              ]);

              Log::warning('Payment failed for invoice', [
                'invoice_id' => $invoiceId,
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'amount' => $amountDue,
                'attempt_count' => $attemptCount
              ]);
            }

            // サブスクリプション履歴記録
            $this->recordSubscriptionHistory(
              $user,
              SubscriptionHistory::ACTION_PAYMENT_FAILED,
              $subscription->plan,
              $subscription->plan, // プランは変更されない
              $subscription->stripe_subscription_id,
              $subscription->stripe_customer_id,
              $amountDue,
              "決済失敗 (試行回数: {$attemptCount}回目)",
              [
                'invoice_id' => $invoiceId,
                'attempt_count' => $attemptCount,
                'failure_type' => 'invoice_payment_failed'
              ],
              $eventId
            );
          }
        }
      } elseif ($event === 'payment_intent.payment_failed') {
        // PaymentIntent決済失敗時の処理
        $paymentIntentId = $data['id'] ?? null;
        $amount = ($data['amount'] ?? 0) / 100; // セントから円に変換
        $currency = $data['currency'] ?? 'jpy';
        $lastPaymentError = $data['last_payment_error'] ?? null;

        if ($paymentIntentId) {
          // メタデータからユーザー情報を取得（checkout sessionで設定されている場合）
          $metadata = $data['metadata'] ?? [];
          $userId = $metadata['user_id'] ?? null;

          if ($userId) {
            $user = User::find($userId);
            if ($user) {
              // 決済失敗のトランザクション記録
              $existingTransaction = PaymentTransaction::where('stripe_payment_intent_id', $paymentIntentId)->first();

              if (!$existingTransaction) {
                PaymentTransaction::create([
                  'user_id' => $user->id,
                  'subscription_id' => null, // PaymentIntentの段階ではサブスクリプションはまだ未確定
                  'stripe_payment_intent_id' => $paymentIntentId,
                  'stripe_charge_id' => null,
                  'amount' => $amount,
                  'currency' => $currency,
                  'status' => 'failed',
                  'type' => 'subscription',
                  'paid_at' => null,
                  'metadata' => [
                    'user_id' => $userId,
                    'payment_failure' => true,
                    'failure_reason' => 'payment_intent_failed',
                    'error_code' => $lastPaymentError['code'] ?? null,
                    'error_message' => $lastPaymentError['message'] ?? null,
                    'error_type' => $lastPaymentError['type'] ?? null
                  ]
                ]);

                Log::warning('PaymentIntent failed', [
                  'payment_intent_id' => $paymentIntentId,
                  'user_id' => $userId,
                  'amount' => $amount,
                  'error_code' => $lastPaymentError['code'] ?? null,
                  'error_message' => $lastPaymentError['message'] ?? null
                ]);
              }
            }
          }
        }
      } elseif ($event === 'charge.failed') {
        // Charge失敗時の処理
        $chargeId = $data['id'] ?? null;
        $paymentIntentId = $data['payment_intent'] ?? null;
        $amount = ($data['amount'] ?? 0) / 100; // セントから円に変換
        $currency = $data['currency'] ?? 'jpy';
        $failureCode = $data['failure_code'] ?? null;
        $failureMessage = $data['failure_message'] ?? null;

        if ($chargeId && $paymentIntentId) {
          // 既存のPaymentTransactionがあれば更新、なければ新規作成
          $transaction = PaymentTransaction::where('stripe_payment_intent_id', $paymentIntentId)->first();

          if ($transaction) {
            // 既存トランザクションを失敗状態に更新
            $transaction->update([
              'status' => 'failed',
              'stripe_charge_id' => $chargeId,
              'metadata' => array_merge($transaction->metadata ?? [], [
                'charge_failure' => true,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'charge_failed_at' => now()->toISOString()
              ])
            ]);

            Log::warning('Charge failed - updated existing transaction', [
              'charge_id' => $chargeId,
              'payment_intent_id' => $paymentIntentId,
              'transaction_id' => $transaction->id,
              'failure_code' => $failureCode,
              'failure_message' => $failureMessage
            ]);
          } else {
            Log::warning('Charge failed but no corresponding PaymentTransaction found', [
              'charge_id' => $chargeId,
              'payment_intent_id' => $paymentIntentId,
              'failure_code' => $failureCode,
              'failure_message' => $failureMessage
            ]);
          }
        }
      } elseif ($event === 'payment_intent.succeeded') {
        // 3Dセキュア決済成功時などのPaymentIntent成功処理
        $paymentIntentId = $data['id'] ?? null;
        $amount = ($data['amount'] ?? 0) / 100; // セントから円に変換
        $currency = $data['currency'] ?? 'jpy';
        $charges = $data['charges']['data'] ?? [];
        $latestCharge = $charges[0] ?? null;

        if ($paymentIntentId) {
          // メタデータからユーザー情報やサブスクリプション情報を取得
          $metadata = $data['metadata'] ?? [];
          $userId = $metadata['user_id'] ?? null;
          $plan = $metadata['plan'] ?? null;

          // invoice経由でサブスクリプション情報を特定する方法も試す
          $invoiceId = $data['invoice'] ?? null;
          $subscription = null;
          $user = null;

          if ($userId) {
            $user = User::find($userId);
          }

          // invoice経由でサブスクリプション情報を取得する試み
          if ($invoiceId && !$user) {
            try {
              $invoice = $this->client->invoices->retrieve($invoiceId);
              if ($invoice->subscription && $invoice->customer_email) {
                $user = User::where('email', $invoice->customer_email)->first();
                $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();
              }
            } catch (Exception $e) {
              Log::warning('Failed to retrieve invoice for payment_intent.succeeded', [
                'payment_intent_id' => $paymentIntentId,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
              ]);
            }
          }

          if ($user) {
            // 3Dセキュア決済では、checkout.session.completedで既にトランザクションが作成されているため、
            // payment_intent.succeededでは既存トランザクションの更新のみを行う

            // より包括的な重複チェック（3Dセキュア対応）
            $existingTransaction = PaymentTransaction::where(function ($query) use ($paymentIntentId, $user, $amount) {
              // 1. Payment IntentIDによる完全一致検索
              $query->where('stripe_payment_intent_id', $paymentIntentId);

              // 2. メタデータ内のPaymentIntentIDで検索
              $query->orWhere('metadata->payment_intent_id', $paymentIntentId);

              // 3. 同じユーザー・金額・時間範囲での重複チェック（実際の金額で確認）
              $query->orWhere(function ($subQuery) use ($user, $amount, $paymentIntentId) {
                $subQuery->where('user_id', $user->id)
                  ->where('amount', $amount)
                  ->where('status', 'succeeded')
                  ->where('created_at', '>=', now()->subMinutes(10)) // 10分以内の範囲を拡大
                  ->where(function ($metaQuery) use ($paymentIntentId) {
                    // メタデータ内にPaymentIntentIDが含まれている、または含まれていない場合
                    $metaQuery->where('metadata->payment_intent_id', $paymentIntentId)
                      ->orWhereNull('metadata->payment_intent_id');
                  });
              });
            })->first();

            if ($existingTransaction) {
              // 既存の記録を成功状態に更新（3Dセキュア認証完了）
              $existingTransaction->update([
                'status' => 'succeeded',
                'stripe_charge_id' => $latestCharge['id'] ?? null,
                'stripe_payment_intent_id' => $paymentIntentId, // 正確なPayment Intent IDに更新
                'paid_at' => now(),
                'metadata' => array_merge($existingTransaction->metadata ?? [], [
                  'payment_intent_succeeded_at' => now()->toISOString(),
                  'requires_action_completed' => true,
                  '3ds_authentication' => true,
                  'original_payment_intent_id' => $paymentIntentId
                ])
              ]);

              Log::info('PaymentIntent succeeded - updated existing transaction for 3DS', [
                'payment_intent_id' => $paymentIntentId,
                'transaction_id' => $existingTransaction->id,
                'user_id' => $user->id,
                '3ds_completed' => true,
                'original_stripe_payment_intent_id' => $existingTransaction->getOriginal('stripe_payment_intent_id')
              ]);
            } else {
              // checkout.session.completedが発生しなかった稀なケースでのみ新規作成
              Log::warning('Creating new transaction for payment_intent.succeeded - checkout.session.completed not processed', [
                'payment_intent_id' => $paymentIntentId,
                'user_id' => $user->id,
                'metadata' => $metadata
              ]);

              $transactionData = [
                'user_id' => $user->id,
                'subscription_id' => $subscription ? $subscription->id : null,
                'stripe_payment_intent_id' => $paymentIntentId,
                'stripe_charge_id' => $latestCharge['id'] ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'succeeded',
                'type' => 'subscription',
                'paid_at' => now(),
                'metadata' => [
                  'user_id' => $userId,
                  'plan' => $plan,
                  'payment_intent_succeeded' => true,
                  'requires_action_completed' => true,
                  '3ds_authentication' => true,
                  'invoice_id' => $invoiceId,
                  'no_checkout_session_completed' => true
                ]
              ];

              PaymentTransaction::create($transactionData);

              Log::info('PaymentIntent succeeded - created new transaction (fallback)', [
                'payment_intent_id' => $paymentIntentId,
                'user_id' => $user->id,
                'amount' => $amount,
                '3ds_completed' => true
              ]);

              // サブスクリプションとユーザーステータスの更新（新規作成の場合のみ）
              if ($subscription && $plan) {
                $subscription->update(['status' => 'active']);
                $user->update([
                  'plan' => $plan,
                  'subscription_status' => 'active'
                ]);

                // 履歴記録
                $this->recordSubscriptionHistory(
                  $user,
                  SubscriptionHistory::ACTION_CREATED,
                  'free',
                  $plan,
                  $subscription->stripe_subscription_id,
                  $subscription->stripe_customer_id,
                  $amount,
                  '3Dセキュア認証完了による決済成功（フォールバック）',
                  [
                    'payment_intent_id' => $paymentIntentId,
                    '3ds_authentication' => true,
                    'requires_action_completed' => true,
                    'fallback_creation' => true
                  ],
                  $eventId
                );
              }
            }
          } else {
            Log::warning('PaymentIntent succeeded but user not found', [
              'payment_intent_id' => $paymentIntentId,
              'metadata' => $metadata,
              'invoice_id' => $invoiceId
            ]);
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
              'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
            ]);

            // ユーザーステータスの更新
            if ($stripeSubscription->cancel_at_period_end) {
              // キャンセル予定の場合はwill_cancelを維持
              $user->update([
                'plan' => $actualPlan,
                'subscription_status' => 'will_cancel',
              ]);
            } else {
              // キャンセルが解除された場合は通常のactiveに戻す
              $user->update([
                'plan' => $actualPlan,
                'subscription_status' => $actualStatus,
              ]);
            }
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

      // キャンセル予定かどうかの判定
      $willCancelAtPeriodEnd = $user->subscription_status === 'will_cancel' ||
        ($stripeSubscription && $stripeSubscription->cancel_at_period_end) ||
        $subscription->cancel_at_period_end ?? false;

      return $this->successResponse('subscription_found', [
        'has_subscription' => true,
        'plan' => $actualPlan, // 実際のプランを返す
        'subscription_status' => $user->subscription_status ?? $actualStatus, // ユーザーの状態を優先
        'current_period_end' => $nextBillingDate,
        'next_billing_date' => $nextBillingDate,
        'can_cancel' => $canCancel && !$willCancelAtPeriodEnd, // キャンセル予定の場合はキャンセルできない
        'will_cancel_at_period_end' => $willCancelAtPeriodEnd,
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

      // Stripeでキャンセル実行（期間終了時キャンセル）
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

      // ローカルデータベース更新（期間終了まではアクティブ状態を維持）
      $subscription->update([
        'cancel_at_period_end' => true,
      ]);

      $user->update([
        'subscription_status' => 'will_cancel', // 期間終了時にキャンセル予定
      ]);

      // 履歴記録
      $this->recordSubscriptionHistory(
        $user,
        SubscriptionHistory::ACTION_CANCELED,
        $subscription->plan,
        $subscription->plan, // キャンセル予約時はプランは変更されない
        $subscription->stripe_subscription_id,
        $subscription->stripe_customer_id,
        null,
        'ユーザーによるキャンセル（期間終了時に有効）'
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
   * ユーザーによるサブスクリプション解約取り消し（再開）
   */
  public function resumeSubscription(User $user): array
  {
    try {
      $subscription = $user->activeSubscription();

      if (!$subscription) {
        return $this->errorResponse('no_subscription', 'アクティブなサブスクリプションがありません');
      }

      // キャンセル予定でない場合はエラー
      if ($user->subscription_status !== 'will_cancel' && !$subscription->cancel_at_period_end) {
        return $this->errorResponse('not_cancelable', 'このサブスクリプションは解約予定ではありません');
      }

      // 既に期間終了している場合はエラー
      if ($subscription->current_period_end && $subscription->current_period_end->isPast()) {
        return $this->errorResponse('period_ended', 'このサブスクリプションは既に期間が終了しています');
      }

      // Stripeで解約取り消し実行
      $stripeSubscription = null;
      if ($this->client && $subscription->stripe_subscription_id) {
        try {
          $stripeSubscription = $this->client->subscriptions->update(
            $subscription->stripe_subscription_id,
            ['cancel_at_period_end' => false]
          );
        } catch (Exception $e) {
          Log::warning('Stripe subscription resume failed', [
            'subscription_id' => $subscription->stripe_subscription_id,
            'error' => $e->getMessage(),
          ]);
          return $this->errorResponse('stripe_error', 'Stripeでの処理に失敗しました');
        }
      }

      // ローカルデータベース更新
      $subscription->update([
        'cancel_at_period_end' => false,
      ]);

      $user->update([
        'subscription_status' => 'active', // アクティブ状態に戻す
      ]);

      // 履歴記録
      $this->recordSubscriptionHistory(
        $user,
        SubscriptionHistory::ACTION_REACTIVATED,
        $subscription->plan,
        $subscription->plan, // プランは変更されない
        $subscription->stripe_subscription_id,
        $subscription->stripe_customer_id,
        null,
        'ユーザーによる解約取り消し（継続利用）'
      );

      Log::info("Subscription resumed", [
        'user_id' => $user->id,
        'subscription_id' => $subscription->stripe_subscription_id,
        'cancel_at_period_end' => false,
      ]);

      return $this->successResponse('subscription_resumed', [
        'message' => '解約を取り消しました。サブスクリプションは継続されます。',
        'cancel_at_period_end' => false,
        'current_period_end' => $stripeSubscription ?
          \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end) :
          $subscription->current_period_end,
      ]);
    } catch (Exception $e) {
      Log::error('Resume subscription error: ' . $e->getMessage(), [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
      ]);
      return $this->errorResponse('resume_error', 'サブスクリプションの再開に失敗しました');
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
}
