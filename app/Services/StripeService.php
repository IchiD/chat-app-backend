<?php

namespace App\Services;

use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\WebhookLog;
use App\Models\PaymentTransaction;
use App\Services\Billing\PaymentService;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\WebhookProcessors\CheckoutSessionCompletedProcessor;
use App\Services\Billing\WebhookProcessors\SubscriptionUpdatedProcessor;
use App\Services\Billing\WebhookProcessors\SubscriptionDeletedProcessor;
use App\Services\Billing\WebhookProcessors\InvoicePaymentProcessor;
use Carbon\Carbon;
use Exception;

class StripeService extends BaseService
{
    private ?StripeClient $client;
    private PaymentService $paymentService;
    private SubscriptionService $subscriptionService;

    public function __construct()
    {
        $apiKey = config('services.stripe.secret');

        if (empty($apiKey)) {
            throw new Exception('Stripe API key is not configured. Please set STRIPE_SECRET_KEY in your .env file.');
        }

        $this->client = new StripeClient($apiKey);
        $this->paymentService = new PaymentService($this->client);
        $this->subscriptionService = new SubscriptionService($this->client);
    }

    public function createCheckoutSession(User $user, string $plan): array
    {
        return $this->paymentService->createCheckoutSession($user, $plan);
    }

    public function handleWebhook(array $payload): void
    {
        $event = $payload['type'] ?? null;
        $eventId = $payload['id'] ?? 'unknown';

        $webhookLog = WebhookLog::where('stripe_event_id', $eventId)->first();
        if (!$webhookLog) {
            $webhookLog = WebhookLog::create([
                'stripe_event_id' => $eventId,
                'event_type' => $event ?? 'unknown',
                'payload' => $payload,
                'status' => 'pending'
            ]);
        } else {
            $webhookLog->update([
                'status' => 'pending',
                'error_message' => null,
                'processed_at' => null
            ]);
        }

        try {
            $processor = $this->getWebhookProcessor($event, $webhookLog, $payload);
            if ($processor) {
                $processor->process();
            } else {
                Log::warning('Unhandled webhook event type', ['event_type' => $event]);
                $webhookLog->update([
                    'status' => 'processed',
                    'processed_at' => now()
                ]);
            }
        } catch (Exception $e) {
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

    private function getWebhookProcessor(string $eventType, WebhookLog $webhookLog, array $payload): ?object
    {
        return match($eventType) {
            'checkout.session.completed' => new CheckoutSessionCompletedProcessor($webhookLog, $payload),
            'customer.subscription.updated' => new SubscriptionUpdatedProcessor($webhookLog, $payload),
            'customer.subscription.deleted' => new SubscriptionDeletedProcessor($webhookLog, $payload),
            'invoice.payment_succeeded', 'invoice.payment_failed' => new InvoicePaymentProcessor($webhookLog, $payload),
            default => null,
        };

    }

    /**
     * ユーザーのサブスクリプション詳細を取得
     */
    public function getSubscriptionDetails(User $user): array
    {
        try {
            $subscription = $user->activeSubscription();

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

            $stripeSubscription = null;
            $actualPlan = $subscription->plan;
            $actualStatus = $subscription->status;

            if ($subscription->stripe_subscription_id && $this->client) {
                try {
                    $stripeSubscription = $this->client->subscriptions->retrieve($subscription->stripe_subscription_id);

                    if ($stripeSubscription) {
                        $actualStatus = $stripeSubscription->status;
                        $priceId = $stripeSubscription->items->data[0]->price->id ?? null;
                        if ($priceId) {
                            $planFromPrice = $this->getPlanFromPriceId($priceId);
                            if ($planFromPrice !== null) {
                                $actualPlan = $planFromPrice;
                            }
                        }

                        $subscription->update([
                            'plan' => $actualPlan,
                            'status' => $actualStatus,
                            'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
                        ]);

                        if ($stripeSubscription->cancel_at_period_end) {
                            $user->update([
                                'plan' => $actualPlan,
                                'subscription_status' => 'will_cancel',
                            ]);
                        } else {
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

            if ($stripeSubscription) {
                $nextBillingDate = Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                $canCancel = in_array($stripeSubscription->status, ['active', 'trialing']);
            }

            $willCancelAtPeriodEnd = $user->subscription_status === 'will_cancel' ||
                ($stripeSubscription && $stripeSubscription->cancel_at_period_end) ||
                $subscription->cancel_at_period_end ?? false;

            return $this->successResponse('subscription_found', [
                'has_subscription' => true,
                'plan' => $actualPlan,
                'subscription_status' => $user->subscription_status ?? $actualStatus,
                'current_period_end' => $nextBillingDate,
                'next_billing_date' => $nextBillingDate,
                'can_cancel' => $canCancel && !$willCancelAtPeriodEnd,
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

            $subscription->update([
                'cancel_at_period_end' => true,
            ]);

            $user->update([
                'subscription_status' => 'will_cancel',
            ]);

            $currentPeriodEnd = $stripeSubscription ?
                Carbon::createFromTimestamp($stripeSubscription->current_period_end) :
                $subscription->current_period_end;

            $this->recordSubscriptionHistory(
                $user,
                SubscriptionHistory::ACTION_CANCELED,
                $subscription->plan,
                $subscription->plan,
                $subscription->stripe_subscription_id,
                $subscription->stripe_customer_id,
                null,
                'ユーザーによるキャンセル（期間終了時に有効）',
                [
                    'current_period_end' => $currentPeriodEnd ? $currentPeriodEnd->toISOString() : null,
                    'cancel_source' => 'user',
                ]
            );

            Log::info("Subscription canceled", [
                'user_id' => $user->id,
                'subscription_id' => $subscription->stripe_subscription_id,
                'cancel_at_period_end' => $stripeSubscription ? $stripeSubscription->cancel_at_period_end : true,
            ]);

            return $this->successResponse('subscription_canceled', [
                'message' => 'サブスクリプションをキャンセルしました。現在の期間終了まで利用可能です。',
                'cancel_at_period_end' => $stripeSubscription ? $stripeSubscription->cancel_at_period_end : true,
                'current_period_end' => $currentPeriodEnd,
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
   * アカウント削除時のサブスクリプションキャンセル
   * 重複チェックをバイパスして必ず履歴を記録
   */
  public function cancelSubscriptionForAccountDeletion(User $user): array
  {
    try {
      $subscription = $user->activeSubscription();

      if (!$subscription) {
        return $this->errorResponse('no_subscription', 'アクティブなサブスクリプションがありません');
      }

      if (!in_array($subscription->status, ['active', 'trialing', 'will_cancel'])) {
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
          Log::warning('Stripe subscription cancellation failed for account deletion', [
            'subscription_id' => $subscription->stripe_subscription_id,
            'error' => $e->getMessage(),
          ]);
        }
      }

      // ローカルデータベース更新
      $subscription->update([
        'cancel_at_period_end' => true,
      ]);

      $user->update([
        'subscription_status' => 'will_cancel',
      ]);

      // 利用可能期限を取得
      $currentPeriodEnd = $stripeSubscription ?
        \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end) :
        $subscription->current_period_end;

      // 退会処理専用の履歴記録（重複チェックをバイパス）
      $this->recordSubscriptionHistoryForAccountDeletion(
        $user,
        $subscription->plan,
        $subscription->stripe_subscription_id,
        $subscription->stripe_customer_id,
        $currentPeriodEnd
      );

      Log::info("Subscription canceled for account deletion", [
        'user_id' => $user->id,
        'subscription_id' => $subscription->stripe_subscription_id,
        'cancel_at_period_end' => true,
      ]);

      return $this->successResponse('subscription_canceled', [
        'message' => 'アカウント削除に伴いサブスクリプションをキャンセルしました。',
        'cancel_at_period_end' => true,
        'current_period_end' => $currentPeriodEnd,
      ]);
    } catch (Exception $e) {
      Log::error('Cancel subscription for account deletion error: ' . $e->getMessage(), [
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

      // キャンセルアクションの場合の特別な重複チェック
      if ($action === SubscriptionHistory::ACTION_CANCELED) {
        // 同じサブスクリプションIDで24時間以内のキャンセルログがあるかチェック
        $existingCancelHistory = SubscriptionHistory::where('user_id', $user->id)
          ->where('action', SubscriptionHistory::ACTION_CANCELED)
          ->where('stripe_subscription_id', $stripeSubscriptionId)
          ->where('created_at', '>=', now()->subHours(24))
          ->orderBy('created_at', 'desc')
          ->first();

        if ($existingCancelHistory) {
          // メタデータが異なる場合のみ、既存レコードを更新
          if ($metadata && $metadata !== $existingCancelHistory->metadata) {
            $existingCancelHistory->update([
              'metadata' => array_merge($existingCancelHistory->metadata ?? [], $metadata),
              'notes' => $notes ?? $existingCancelHistory->notes,
            ]);
            Log::info('Updated existing cancel history with new metadata', [
              'user_id' => $user->id,
              'history_id' => $existingCancelHistory->id,
            ]);
          } else {
            Log::info('Cancel history already exists within 24 hours, skipping creation', [
              'user_id' => $user->id,
              'existing_id' => $existingCancelHistory->id,
            ]);
          }
          return;
        }
      }

      // フォールバック: 業務ロジックベースの重複チェック
      $existingHistory = SubscriptionHistory::where('user_id', $user->id)
        ->where('action', $action)
        ->where('from_plan', $fromPlan)
        ->where('to_plan', $toPlan)
        ->where('created_at', '>=', now()->subMinutes(5))
        ->first();

      if ($existingHistory) {
        Log::info('Subscription history already exists for this action, skipping creation', [
          'user_id' => $user->id,
          'action' => $action,
          'existing_id' => $existingHistory->id,
        ]);
        return;
      }

      // 履歴レコードを作成
      SubscriptionHistory::create([
        'user_id' => $user->id,
        'action' => $action,
        'from_plan' => $fromPlan,
        'to_plan' => $toPlan,
        'amount' => $amount,
        'currency' => 'jpy',
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_customer_id' => $stripeCustomerId,
        'notes' => $notes,
        'metadata' => $metadata,
        'webhook_event_id' => $webhookEventId,
      ]);

      Log::info('Subscription history recorded', [
        'user_id' => $user->id,
        'action' => $action,
        'from_plan' => $fromPlan,
        'to_plan' => $toPlan,
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
   * アカウント削除時のサブスクリプション履歴を記録（重複チェックなし）
   */
  private function recordSubscriptionHistoryForAccountDeletion(
    User $user,
    string $plan,
    ?string $stripeSubscriptionId,
    ?string $stripeCustomerId,
    ?\Carbon\Carbon $currentPeriodEnd
  ): void {
    try {
      // 退会処理時は重複チェックをバイパスして必ず履歴を記録
      SubscriptionHistory::create([
        'user_id' => $user->id,
        'action' => SubscriptionHistory::ACTION_CANCELED,
        'from_plan' => $plan,
        'to_plan' => $plan, // キャンセル時はプラン変更なし
        'amount' => null,
        'currency' => 'jpy',
        'stripe_subscription_id' => $stripeSubscriptionId,
        'stripe_customer_id' => $stripeCustomerId,
        'notes' => 'アカウント削除に伴うキャンセル（期間終了時に有効）',
        'metadata' => [
          'current_period_end' => $currentPeriodEnd ? $currentPeriodEnd->toISOString() : null,
          'cancel_source' => 'account_deletion',
        ],
      ]);

      Log::info('Account deletion subscription history recorded', [
        'user_id' => $user->id,
        'action' => SubscriptionHistory::ACTION_CANCELED,
        'plan' => $plan,
        'current_period_end' => $currentPeriodEnd,
      ]);
    } catch (Exception $e) {
      Log::error('Failed to record account deletion subscription history', [
        'user_id' => $user->id,
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
   * Customer Portal セッションを作成
   */
  public function createCustomerPortalSession(string $customerId, string $returnUrl)
  {
    return $this->client->billingPortal->sessions->create([
      'customer' => $customerId,
      'return_url' => $returnUrl,
    ]);
  }
}
