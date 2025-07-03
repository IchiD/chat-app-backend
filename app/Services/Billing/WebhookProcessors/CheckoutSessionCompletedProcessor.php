<?php

namespace App\Services\Billing\WebhookProcessors;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class CheckoutSessionCompletedProcessor extends BaseWebhookProcessor
{
    public function process(): void
    {
        try {
            $sessionId = $this->data['id'] ?? null;
            $customerId = $this->data['customer'] ?? null;
            $email = $this->data['customer_details']['email'] ?? null;
            $subscriptionId = $this->data['subscription'] ?? null;
            $paymentIntentId = $this->data['payment_intent'] ?? null;

            if (!$email || !$subscriptionId) {
                Log::warning('Missing required data in checkout.session.completed', [
                    'session_id' => $sessionId,
                    'has_email' => !empty($email),
                    'has_subscription' => !empty($subscriptionId)
                ]);
                $this->markAsProcessed();
                return;
            }

            $user = User::where('email', $email)->first();
            if (!$user) {
                Log::warning('User not found for checkout session', [
                    'session_id' => $sessionId,
                    'email' => $email
                ]);
                $this->markAsProcessed();
                return;
            }

            if ($user->isDeleted()) {
                Log::info('Skipping checkout session processing for deleted user', [
                    'session_id' => $sessionId,
                    'user_id' => $user->id,
                    'user_deleted_at' => $user->deleted_at
                ]);
                $this->markAsProcessed();
                return;
            }

            $metadata = $this->data['metadata'] ?? [];
            $plan = $metadata['plan'] ?? 'standard';
            $previousPlan = $metadata['upgrade_from'] ?? 'free';

            $subscription = Subscription::updateOrCreate(
                ['stripe_subscription_id' => $subscriptionId],
                [
                    'user_id' => $user->id,
                    'stripe_customer_id' => $customerId,
                    'plan' => $plan,
                    'status' => 'active',
                    'current_period_end' => now()->addMonth(),
                    'cancel_at_period_end' => false,
                ]
            );

            $user->update([
                'plan' => $plan,
                'subscription_status' => 'active'
            ]);

            $maxMembers = $plan === 'premium' ? 200 : 50;
            \App\Models\Group::where('owner_user_id', $user->id)
                ->update(['max_members' => $maxMembers]);

            $this->createPaymentTransaction($user, $subscription, $paymentIntentId, $sessionId);

            $action = $previousPlan === 'free' ? SubscriptionHistory::ACTION_CREATED : SubscriptionHistory::ACTION_UPGRADED;
            app(\App\Services\Billing\SubscriptionService::class)->recordSubscriptionHistory(
                $user,
                $action,
                $previousPlan !== 'free' ? $previousPlan : null,
                $plan,
                $subscriptionId,
                $customerId,
                ($this->data['amount_total'] ?? 0) / 100,
                'Stripe決済完了による' . ($action === SubscriptionHistory::ACTION_CREATED ? 'プラン開始' : 'プラン変更'),
                null,
                $this->eventId
            );

            $this->markAsProcessed();
        } catch (Exception $e) {
            $this->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function createPaymentTransaction(User $user, Subscription $subscription, ?string $paymentIntentId, string $sessionId): void
    {
        $uniquePaymentId = $paymentIntentId ?: 'session_' . $sessionId;
        $existingTransaction = PaymentTransaction::where('stripe_payment_intent_id', $uniquePaymentId)->first();

        if (!$existingTransaction) {
            $status = $paymentIntentId ? 'requires_action' : 'succeeded';

            PaymentTransaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $uniquePaymentId,
                'stripe_charge_id' => $this->data['charges']['data'][0]['id'] ?? null,
                'amount' => ($this->data['amount_total'] ?? 0) / 100,
                'currency' => $this->data['currency'] ?? 'jpy',
                'status' => $status,
                'type' => 'subscription',
                'paid_at' => now(),
                'metadata' => [
                    'session_id' => $sessionId,
                    'plan' => $subscription->plan,
                    'requires_3ds' => !empty($paymentIntentId)
                ]
            ]);

            Log::info('PaymentTransaction created from checkout.session.completed', [
                'session_id' => $sessionId,
                'payment_intent_id' => $paymentIntentId,
                'unique_payment_id' => $uniquePaymentId,
                'user_id' => $user->id,
                'requires_3ds' => !empty($paymentIntentId)
            ]);
        }
    }
}
