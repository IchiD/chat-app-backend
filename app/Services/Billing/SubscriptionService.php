<?php

namespace App\Services\Billing;

use App\Services\BaseService;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use Exception;

class SubscriptionService extends BaseService
{
    private StripeClient $client;

    public function __construct(?StripeClient $client = null)
    {
        $this->client = $client ?? new StripeClient(config('services.stripe.secret'));
    }

    public function upgradeSubscription(User $user, Subscription $subscription, string $newPlan): array
    {
        try {
            $newPriceId = config("services.stripe.prices.$newPlan");
            if (empty($newPriceId)) {
                return $this->errorResponse('invalid_plan', '指定されたプランは存在しません');
            }

            if ($subscription->plan === 'premium' && $newPlan === 'standard') {
                $largeGroups = \App\Models\Group::where('owner_user_id', $user->id)
                    ->withCount('activeMembers')
                    ->get()
                    ->filter(fn($g) => $g->active_members_count > 50);

                if ($largeGroups->count() > 0) {
                    $groupNames = $largeGroups->pluck('name')->toArray();
                    return $this->errorResponse('downgrade_blocked', [
                        'message' => 'グループ編集ページでメンバーの人数を50人以下にしてください。',
                        'groups' => $groupNames,
                        'link' => '/user/groups'
                    ]);
                }
            }

            $stripeSubscription = $this->client->subscriptions->retrieve($subscription->stripe_subscription_id);
            if (!$stripeSubscription) {
                return $this->errorResponse('subscription_not_found', 'Stripeサブスクリプションが見つかりません');
            }

            $this->client->subscriptions->update($subscription->stripe_subscription_id, [
                'items' => [[
                    'id' => $stripeSubscription->items->data[0]->id,
                    'price' => $newPriceId,
                ]],
                'proration_behavior' => 'always_invoice',
                'cancel_at_period_end' => false,
            ]);

            $oldPlan = $subscription->plan;
            $this->recordSubscriptionHistory(
                $user,
                SubscriptionHistory::ACTION_UPGRADED,
                $oldPlan,
                $newPlan,
                $subscription->stripe_subscription_id,
                $subscription->stripe_customer_id,
                null,
                'プランアップグレード - 差額請求'
            );

            $subscription->update([
                'plan' => $newPlan,
                'cancel_at_period_end' => false,
            ]);

            $user->update([
                'plan' => $newPlan,
                'subscription_status' => 'active',
            ]);

            $newMaxMembers = $newPlan === 'premium' ? 200 : 50;
            \App\Models\Group::where('owner_user_id', $user->id)
                ->update(['max_members' => $newMaxMembers]);

            Log::info('Subscription upgraded successfully', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->stripe_subscription_id,
                'from_plan' => $oldPlan,
                'to_plan' => $newPlan,
            ]);

            return $this->successResponse('subscription_upgraded', [
                'message' => 'プランが正常に変更されました。差額はすぐに請求されます。',
                'new_plan' => $newPlan,
            ]);
        } catch (Exception $e) {
            return $this->handleException('upgradeSubscription', $e, 'upgrade_failed', 'プラン変更に失敗しました');
        }
    }

    // 🔽 追加: サブスクリプション履歴記録メソッド
    public function recordSubscriptionHistory(
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
            // Webhook Event IDベースの冪等性チェック
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
}
