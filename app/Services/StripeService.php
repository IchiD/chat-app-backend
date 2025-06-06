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
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Checkout セッションを作成しURLを返す
     */
    public function createCheckoutSession(User $user, string $plan): array
    {
        try {
            $priceId = config("services.stripe.prices.$plan");
            $session = $this->client->checkout->sessions->create([
                'customer_email' => $user->email,
                'mode' => 'subscription',
                'line_items' => [
                    ['price' => $priceId, 'quantity' => 1],
                ],
                'success_url' => config('app.frontend_url') . '/payment/success',
                'cancel_url' => config('app.frontend_url') . '/payment/cancel',
            ]);
            return $this->successResponse('session_created', ['url' => $session->url]);
        } catch (Exception $e) {
            Log::error('Stripe create session error: '.$e->getMessage());
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
