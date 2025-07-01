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

  /**
   * Stripe Customer Portal URLを生成
   */
  public function createCustomerPortalSession(Request $request): JsonResponse
  {
    try {
      $user = $request->user();
      
      // アクティブなサブスクリプションを取得
      $activeSubscription = $user->activeSubscription();
      
      if (!$activeSubscription) {
        return response()->json([
          'status' => 'error',
          'message' => 'アクティブなサブスクリプションが必要です'
        ], 403);
      }

      $customerId = $activeSubscription->stripe_customer_id;
      $returnUrl = $request->input('return_url', config('app.frontend_url') . '/user/subscription');

      $session = $this->service->createCustomerPortalSession($customerId, $returnUrl);

      return response()->json([
        'status' => 'success',
        'data' => [
          'url' => $session->url
        ]
      ]);

    } catch (\Stripe\Exception\InvalidRequestException $e) {
      \Log::error('Stripe customer portal session creation error: ' . $e->getMessage(), [
        'customer_id' => $customerId ?? null,
        'return_url' => $returnUrl ?? null,
        'error' => $e->getMessage()
      ]);
      
      // Customer Portal設定エラーの場合
      if (str_contains($e->getMessage(), 'configuration')) {
        return response()->json([
          'status' => 'error',
          'message' => '請求書機能は現在準備中です。Stripe Customer Portal設定を完了する必要があります。',
          'error_type' => 'configuration_required'
        ], 500);
      }
      
      return response()->json([
        'status' => 'error',
        'message' => '請求書ページの作成に失敗しました: ' . $e->getMessage()
      ], 500);
      
    } catch (\Exception $e) {
      \Log::error('Customer portal session creation failed: ' . $e->getMessage(), [
        'customer_id' => $customerId ?? null,
        'return_url' => $returnUrl ?? null,
        'error' => $e->getMessage()
      ]);

      return response()->json([
        'status' => 'error',
        'message' => '内部サーバーエラーが発生しました'
      ], 500);
    }
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
