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

    // 成功の場合は200を返す
    if ($result['status'] === StripeService::STATUS_SUCCESS) {
      return response()->json($result, 200);
    }

    return response()->json($result, 500);
  }

  public function getSubscriptionDetails(): JsonResponse
  {
    $user = Auth::user();
    $result = $this->service->getSubscriptionDetails($user);

    // 成功レスポンスの場合は200を返す
    if ($result['status'] === StripeService::STATUS_SUCCESS) {
      return response()->json($result, 200);
    }

    return response()->json($result, 500);
  }

  public function cancelSubscription(): JsonResponse
  {
    $user = Auth::user();
    $result = $this->service->cancelSubscription($user);
    $status = $result['status'] === StripeService::STATUS_SUCCESS ? 200 : 500;
    return response()->json($result, $status);
  }

  public function resumeSubscription(): JsonResponse
  {
    $user = Auth::user();
    $result = $this->service->resumeSubscription($user);
    $status = $result['status'] === StripeService::STATUS_SUCCESS ? 200 : 500;
    return response()->json($result, $status);
  }

  public function getSubscriptionHistory(): JsonResponse
  {
    $user = Auth::user();
    $histories = $user->subscriptionHistories()
      ->with('user:id,name,email')
      ->paginate(10);

    // formatted_amountアクセサを含む形でデータを変換
    $transformedData = $histories->getCollection()->map(function ($history) {
      $historyArray = $history->toArray();
      $historyArray['formatted_amount'] = $history->formatted_amount;
      // 金額も×100して正しい円金額にする
      if ($history->amount) {
        $historyArray['amount'] = $history->amount * 100;
      }
      return $historyArray;
    });

    return response()->json([
      'status' => 'success',
      'data' => $transformedData,
      'pagination' => [
        'current_page' => $histories->currentPage(),
        'last_page' => $histories->lastPage(),
        'per_page' => $histories->perPage(),
        'total' => $histories->total(),
      ],
    ]);
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
