<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\StripeService;
use Symfony\Component\HttpFoundation\Response;
use Stripe\Webhook;

class StripeController extends Controller
{
    private StripeService $service;

    public function __construct(StripeService $service)
    {
        $this->service = $service;
    }

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:standard,premium',
        ]);
        $user = Auth::user();
        $result = $this->service->createCheckoutSession($user, $request->plan);
        $status = $result['status'] === StripeService::STATUS_SUCCESS ? 200 : 500;
        return response()->json($result, $status);
    }

    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
            $this->service->handleWebhook($event->toArray());
            return response('success', 200);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid payload');
            return response('invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Signature verification failed');
            return response('invalid signature', 400);
        }
    }
}
