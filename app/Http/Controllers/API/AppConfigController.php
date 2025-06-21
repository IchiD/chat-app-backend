<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Exception;

class AppConfigController extends Controller
{
  /**
   * アプリケーションの公開設定情報を取得
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPublicConfig(): JsonResponse
  {
    return response()->json([
      'vapid' => [
        'publicKey' => config('webpush.vapid.public_key')
      ],
      'env' => [
        'app_name' => config('app.name'),
        'app_url' => config('app.url'),
        'app_env' => config('app.env')
      ]
    ]);
  }

  /**
   * アプリケーション設定情報を取得
   */
  public function index(): JsonResponse
  {
    try {
      $config = [
        'app_name' => config('app.name', 'LumoChat'),
        'app_version' => config('app.version', '1.0.0'),
        'features' => [
          'stripe_enabled' => !empty(config('services.stripe.secret')),
          'google_auth_enabled' => !empty(config('services.google.client_id')),
        ],
        'pricing' => $this->getPricingConfig(),
      ];

      return response()->json([
        'status' => 'success',
        'data' => $config,
      ]);
    } catch (Exception $e) {
      Log::error('Failed to get app config', [
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'アプリケーション設定の取得に失敗しました',
      ], 500);
    }
  }

  /**
   * 価格設定情報を取得
   */
  public function getPricing(): JsonResponse
  {
    try {
      $pricing = $this->getPricingConfig();

      return response()->json([
        'status' => 'success',
        'data' => $pricing,
      ]);
    } catch (Exception $e) {
      Log::error('Failed to get pricing config', [
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'status' => 'error',
        'message' => '価格情報の取得に失敗しました',
      ], 500);
    }
  }

  /**
   * 価格設定情報を構築（キャッシュ付き）
   */
  private function getPricingConfig(): array
  {
    return Cache::remember('app_pricing_config', 300, function () {
      $pricing = [
        'plans' => [
          'free' => [
            'name' => 'FREE',
            'display_name' => 'フリー',
            'price' => 0,
            'currency' => 'JPY',
            'formatted_price' => '¥0',
            'billing_interval' => null,
            'features' => [
              '基本チャット機能',
              'QRコード参加',
              'メッセージ履歴（2年間）',
              'チャットサポート',
            ],
            'limits' => [
              'group_members' => 0,
              'groups' => 0,
            ],
          ],
          'standard' => [
            'name' => 'STANDARD',
            'display_name' => 'スタンダード',
            'price' => 2980,
            'currency' => 'JPY',
            'formatted_price' => '¥2,980',
            'billing_interval' => 'month',
            'stripe_price_id' => config('services.stripe.prices.standard'),
            'features' => [
              'フリープランの全機能',
              'グループチャット（最大50名）',
              'メンバー管理機能',
              'メッセージ履歴（5年間）',
              '優先サポート',
            ],
            'limits' => [
              'group_members' => 50,
              'groups' => -1, // 無制限
            ],
          ],
          'premium' => [
            'name' => 'PREMIUM',
            'display_name' => 'プレミアム',
            'price' => 5980,
            'currency' => 'JPY',
            'formatted_price' => '¥5,980',
            'billing_interval' => 'month',
            'stripe_price_id' => config('services.stripe.prices.premium'),
            'features' => [
              'スタンダードプランの全機能',
              'グループチャット（最大200名）',
              '一括配信機能',
              'メッセージ履歴（5年間）',
              '優先サポート',
            ],
            'limits' => [
              'group_members' => 200,
              'groups' => -1, // 無制限
            ],
          ],
        ],
        'stripe_enabled' => !empty(config('services.stripe.secret')),
        'test_mode' => str_starts_with(config('services.stripe.secret', ''), 'sk_test_'),
      ];

      // Stripeが有効な場合、実際の価格情報を取得
      if ($pricing['stripe_enabled']) {
        $pricing = $this->enrichWithStripePricing($pricing);
      }

      return $pricing;
    });
  }

  /**
   * Stripeから実際の価格情報を取得して設定を補強
   */
  private function enrichWithStripePricing(array $pricing): array
  {
    try {
      $stripe = new StripeClient(config('services.stripe.secret'));

      foreach (['standard', 'premium'] as $plan) {
        $priceId = $pricing['plans'][$plan]['stripe_price_id'] ?? null;

        if ($priceId) {
          try {
            $stripePrice = $stripe->prices->retrieve($priceId);

            // Stripeから取得した価格で上書き（通貨に応じた変換）
            $amount = $this->convertStripeAmount($stripePrice->unit_amount, $stripePrice->currency);
            $pricing['plans'][$plan]['price'] = $amount;
            $pricing['plans'][$plan]['currency'] = strtoupper($stripePrice->currency);
            $pricing['plans'][$plan]['formatted_price'] = $this->formatPrice($amount, $stripePrice->currency);
            $pricing['plans'][$plan]['billing_interval'] = $stripePrice->recurring->interval ?? 'month';
            $pricing['plans'][$plan]['stripe_verified'] = true;
          } catch (Exception $e) {
            Log::warning("Failed to retrieve Stripe price for {$plan}", [
              'price_id' => $priceId,
              'error' => $e->getMessage(),
            ]);
            $pricing['plans'][$plan]['stripe_verified'] = false;
          }
        }
      }
    } catch (Exception $e) {
      Log::warning('Failed to enrich pricing with Stripe data', [
        'error' => $e->getMessage(),
      ]);
    }

    return $pricing;
  }

  /**
   * 価格設定キャッシュをクリア
   */
  public function clearPricingCache(): JsonResponse
  {
    try {
      Cache::forget('app_pricing_config');

      return response()->json([
        'status' => 'success',
        'message' => '価格設定キャッシュをクリアしました',
      ]);
    } catch (Exception $e) {
      Log::error('Failed to clear pricing cache', [
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'キャッシュクリアに失敗しました',
      ], 500);
    }
  }

  /**
   * Stripeの価格を適切な通貨単位に変換
   */
  private function convertStripeAmount(int $unitAmount, string $currency): float
  {
    // ゼロ小数点通貨（日本円、韓国ウォンなど）
    $zeroDecimalCurrencies = ['jpy', 'krw', 'pyg', 'vnd', 'xaf', 'xof', 'bif', 'clp', 'djf', 'gnf', 'kmf', 'mga', 'rwf', 'vuv', 'xpf'];
    
    if (in_array(strtolower($currency), $zeroDecimalCurrencies)) {
      return (float) $unitAmount; // そのまま返す
    }
    
    return $unitAmount / 100; // セントから主要通貨単位に変換
  }

  /**
   * 価格を適切な形式でフォーマット
   */
  private function formatPrice(float $amount, string $currency): string
  {
    $currency = strtolower($currency);
    
    switch ($currency) {
      case 'jpy':
        return '¥' . number_format($amount, 0);
      case 'usd':
        return '$' . number_format($amount, 2);
      case 'eur':
        return '€' . number_format($amount, 2);
      default:
        return strtoupper($currency) . ' ' . number_format($amount, 2);
    }
  }
}
