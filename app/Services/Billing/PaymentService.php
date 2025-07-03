<?php

namespace App\Services\Billing;

use App\Services\BaseService;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService extends BaseService
{
    private ?StripeClient $client;

    public function __construct(?StripeClient $client = null)
    {
        $this->client = $client ?? new StripeClient(config('services.stripe.secret'));
    }

    public function createCheckoutSession(User $user, string $plan): array
    {
        try {
            $activeSubscription = $user->activeSubscription();

            if ($activeSubscription) {
                if ($activeSubscription->plan === $plan) {
                    return $this->errorResponse('subscription_exists', '既に同じプランのサブスクリプションがアクティブです');
                }

                $isUpgrade = ($activeSubscription->plan === 'standard' && $plan === 'premium') ||
                    ($activeSubscription->plan === 'free' && in_array($plan, ['standard', 'premium']));
                $isDowngrade = ($activeSubscription->plan === 'premium' && $plan === 'standard');

                if ($isUpgrade || $isDowngrade) {
                    return app(SubscriptionService::class)->upgradeSubscription($user, $activeSubscription, $plan);
                }

                return $this->errorResponse('invalid_plan_change', 'プラン変更については、サポートまでお問い合わせください');
            }

            if ($user->plan && $user->plan !== 'free') {
                if ($user->plan === $plan) {
                    return $this->errorResponse('same_plan', "既に{$plan}プランをご利用中です");
                }
            }

            $priceId = config("services.stripe.prices.$plan");
            if (empty($priceId)) {
                return $this->errorResponse('invalid_plan', '指定されたプランは存在しません');
            }

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

            Log::info('Stripe checkout session created', [
                'user_id' => $user->id,
                'plan' => $plan,
                'session_id' => $session->id,
                'previous_plan' => $user->plan ?? 'free',
                'test_mode' => str_starts_with(config('services.stripe.secret'), 'sk_test_'),
            ]);

            return $this->successResponse('session_created', ['url' => $session->url]);
        } catch (Exception $e) {
            return $this->handleException('createCheckoutSession', $e, 'stripe_error', '決済セッションの作成に失敗しました');
        }
    }
}
