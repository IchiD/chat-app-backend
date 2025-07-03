<?php

namespace App\Services\Billing\WebhookProcessors;

use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SubscriptionUpdatedProcessor extends BaseWebhookProcessor
{
    public function process(): void
    {
        try {
            $subscriptionId = $this->data['id'] ?? null;
            $cancelAtPeriodEnd = $this->data['cancel_at_period_end'] ?? false;
            
            $subscription = Subscription::with(['user' => function ($query) {
                $query->withTrashed();
            }])->where('stripe_subscription_id', $subscriptionId)->first();

            if (!$subscription) {
                $this->markAsProcessed();
                return;
            }

            // サブスクリプション情報を更新
            $subscription->update([
                'status' => $this->data['status'],
                'current_period_end' => now()->setTimestamp($this->data['current_period_end']),
                'cancel_at_period_end' => $cancelAtPeriodEnd,
            ]);

            // ユーザーが削除されている場合は処理終了
            if (!$subscription->user || $subscription->user->isDeleted()) {
                $this->markAsProcessed();
                return;
            }

            // プラン変更の検出
            $priceId = $this->data['items']['data'][0]['price']['id'] ?? null;
            if ($priceId) {
                $newPlan = $this->getPlanFromPriceId($priceId);
                if ($newPlan && $newPlan !== $subscription->plan) {
                    $subscription->update(['plan' => $newPlan]);
                    
                    $newMaxMembers = $newPlan === 'premium' ? 200 : 50;
                    \App\Models\Group::where('owner_user_id', $subscription->user->id)
                        ->update(['max_members' => $newMaxMembers]);
                }
            }

            // ユーザーステータス更新
            if ($cancelAtPeriodEnd) {
                $subscription->user->update([
                    'plan' => $subscription->plan,
                    'subscription_status' => 'will_cancel',
                ]);
            } else {
                $subscription->user->update([
                    'plan' => $subscription->plan,
                    'subscription_status' => $this->data['status'],
                ]);
            }

            $this->markAsProcessed();
        } catch (Exception $e) {
            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function getPlanFromPriceId(string $priceId): ?string
    {
        $priceMapping = [
            config('services.stripe.prices.standard') => 'standard',
            config('services.stripe.prices.premium') => 'premium',
        ];
        return $priceMapping[$priceId] ?? null;
    }
}
