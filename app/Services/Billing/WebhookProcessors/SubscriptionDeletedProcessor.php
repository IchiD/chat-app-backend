<?php

namespace App\Services\Billing\WebhookProcessors;

use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SubscriptionDeletedProcessor extends BaseWebhookProcessor
{
    public function process(): void
    {
        try {
            $subscriptionId = $this->data['id'] ?? null;
            
            $subscription = Subscription::with(['user' => function ($query) {
                $query->withTrashed();
            }])->where('stripe_subscription_id', $subscriptionId)->first();

            if (!$subscription) {
                $this->markAsProcessed();
                return;
            }

            $subscription->update(['status' => 'canceled']);

            $currentPeriodEnd = isset($this->data['current_period_end']) ?
                Carbon::createFromTimestamp($this->data['current_period_end']) :
                $subscription->current_period_end;

            if (!$subscription->user || $subscription->user->isDeleted()) {
                if ($subscription->user) {
                    app(\App\Services\Billing\SubscriptionService::class)->recordSubscriptionHistory(
                        $subscription->user,
                        SubscriptionHistory::ACTION_CANCELED,
                        $subscription->plan,
                        'free',
                        $subscription->stripe_subscription_id,
                        $subscription->stripe_customer_id,
                        null,
                        'Stripeでのサブスクリプションキャンセル（削除済みユーザー）',
                        [
                            'current_period_end' => $currentPeriodEnd ? $currentPeriodEnd->toISOString() : null,
                            'cancel_source' => 'stripe_webhook',
                            'user_deleted' => true,
                        ],
                        $this->eventId
                    );
                }
                $this->markAsProcessed();
                return;
            }

            $subscription->user->update(['subscription_status' => 'canceled', 'plan' => 'free']);

            \App\Models\Group::where('owner_user_id', $subscription->user->id)
                ->update(['max_members' => 50]);

            app(\App\Services\Billing\SubscriptionService::class)->recordSubscriptionHistory(
                $subscription->user,
                SubscriptionHistory::ACTION_CANCELED,
                $subscription->plan,
                'free',
                $subscription->stripe_subscription_id,
                $subscription->stripe_customer_id,
                null,
                'Stripeでのサブスクリプションキャンセル',
                [
                    'current_period_end' => $currentPeriodEnd ? $currentPeriodEnd->toISOString() : null,
                    'cancel_source' => 'stripe_webhook',
                ],
                $this->eventId
            );

            $this->markAsProcessed();
        } catch (Exception $e) {
            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }
}
