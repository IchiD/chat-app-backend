<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stripe\StripeClient;
use Exception;

class StripeConfigCheck extends Command
{
  protected $signature = 'stripe:config-check';
  protected $description = 'Check Stripe configuration and connectivity';

  public function handle()
  {
    $this->info('🔍 Checking Stripe configuration...');
    $this->newLine();

    // 1. 環境変数チェック
    $this->checkEnvironmentVariables();
    $this->newLine();

    // 2. API接続チェック
    $this->checkApiConnection();
    $this->newLine();

    // 3. 価格設定チェック
    $this->checkPriceConfiguration();
    $this->newLine();

    // 4. 価格API動作チェック
    $this->checkPricingAPI();
    $this->newLine();

    $this->info('✅ Stripe configuration check completed!');
  }

  private function checkEnvironmentVariables()
  {
    $this->info('📋 Environment Variables:');

    $variables = [
      'STRIPE_SECRET_KEY' => config('services.stripe.secret'),
      'STRIPE_WEBHOOK_SECRET' => config('services.stripe.webhook_secret'),
      'STRIPE_PRICE_STANDARD' => config('services.stripe.prices.standard'),
      'STRIPE_PRICE_PREMIUM' => config('services.stripe.prices.premium'),
    ];

    foreach ($variables as $name => $value) {
      if (empty($value)) {
        $this->error("  ❌ {$name}: Not set");
      } else {
        $masked = $this->maskValue($value);
        $this->info("  ✅ {$name}: {$masked}");
      }
    }
  }

  private function checkApiConnection()
  {
    $this->info('🔗 API Connection:');

    $secretKey = config('services.stripe.secret');

    if (empty($secretKey)) {
      $this->error('  ❌ Cannot test connection: STRIPE_SECRET_KEY not set');
      return;
    }

    try {
      $stripe = new StripeClient($secretKey);
      $account = $stripe->accounts->retrieve();

      $mode = str_starts_with($secretKey, 'sk_test_') ? 'Test Mode' : 'Live Mode';
      $this->info("  ✅ Connection successful");
      $this->info("  📊 Account ID: {$account->id}");
      $this->info("  🔧 Mode: {$mode}");

      if (!str_starts_with($secretKey, 'sk_test_')) {
        $this->warn('  ⚠️  Warning: Using live mode keys in development!');
      }
    } catch (Exception $e) {
      $this->error("  ❌ Connection failed: {$e->getMessage()}");
    }
  }

  private function checkPriceConfiguration()
  {
    $this->info('💰 Price Configuration:');

    $secretKey = config('services.stripe.secret');

    if (empty($secretKey)) {
      $this->error('  ❌ Cannot check prices: STRIPE_SECRET_KEY not set');
      return;
    }

    $prices = [
      'standard' => config('services.stripe.prices.standard'),
      'premium' => config('services.stripe.prices.premium'),
    ];

    try {
      $stripe = new StripeClient($secretKey);

      foreach ($prices as $plan => $priceId) {
        if (empty($priceId)) {
          $this->error("  ❌ {$plan}: Price ID not set");
          continue;
        }

        try {
          $price = $stripe->prices->retrieve($priceId);
          $amount = $this->convertStripeAmount($price->unit_amount, $price->currency);
          $formattedAmount = $this->formatPrice($amount, $price->currency);
          $currency = strtoupper($price->currency);
          $interval = $price->recurring->interval ?? 'one-time';

          $this->info("  ✅ {$plan}: {$formattedAmount} per {$interval} (ID: {$priceId})");
        } catch (Exception $e) {
          $this->error("  ❌ {$plan}: Invalid price ID ({$priceId}) - {$e->getMessage()}");
        }
      }
    } catch (Exception $e) {
      $this->error("  ❌ Failed to check prices: {$e->getMessage()}");
    }
  }

  private function checkPricingAPI()
  {
    $this->info('🏷️ Pricing API Check:');

    try {
      $controller = new \App\Http\Controllers\API\AppConfigController();
      $response = $controller->getPricing();
      $data = json_decode($response->getContent(), true);

      if ($data['status'] === 'success') {
        $this->info('  ✅ Pricing API working correctly');
        
        $plans = $data['data']['plans'];
        foreach (['free', 'standard', 'premium'] as $plan) {
          if (isset($plans[$plan])) {
            $planData = $plans[$plan];
            $verified = $planData['stripe_verified'] ?? false;
            $verifiedIcon = $verified ? '✅' : '⚠️';
            $this->info("  {$verifiedIcon} {$plan}: {$planData['formatted_price']} ({$planData['name']})");
          }
        }

        $stripeEnabled = $data['data']['stripe_enabled'] ? 'Enabled' : 'Disabled';
        $testMode = $data['data']['test_mode'] ? 'Test Mode' : 'Live Mode';
        $this->info("  📊 Stripe: {$stripeEnabled} ({$testMode})");
      } else {
        $this->error('  ❌ Pricing API failed: ' . ($data['message'] ?? 'Unknown error'));
      }
    } catch (Exception $e) {
      $this->error('  ❌ Pricing API error: ' . $e->getMessage());
    }
  }

  private function maskValue(string $value): string
  {
    if (strlen($value) <= 8) {
      return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
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
