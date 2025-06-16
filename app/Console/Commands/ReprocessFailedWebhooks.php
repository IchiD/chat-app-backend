<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebhookLog;
use App\Services\StripeService;
use Exception;

class ReprocessFailedWebhooks extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'webhook:reprocess-failed {--id= : Specific webhook ID to reprocess} {--limit=10 : Maximum number of webhooks to reprocess}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Reprocess failed webhook logs';

  /**
   * Execute the console command.
   */
  public function handle(StripeService $stripeService)
  {
    $webhookId = $this->option('id');
    $limit = (int) $this->option('limit');

    if ($webhookId) {
      // 特定のWebhookを再処理
      $webhook = WebhookLog::find($webhookId);
      if (!$webhook) {
        $this->error("Webhook with ID {$webhookId} not found.");
        return Command::FAILURE;
      }

      $this->info("Reprocessing webhook ID: {$webhookId}");
      $this->reprocessWebhook($webhook, $stripeService);
    } else {
      // 失敗したWebhookを一括再処理
      $failedWebhooks = WebhookLog::where('status', 'failed')
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();

      if ($failedWebhooks->isEmpty()) {
        $this->info('No failed webhooks found.');
        return Command::SUCCESS;
      }

      $this->info("Found {$failedWebhooks->count()} failed webhooks. Reprocessing...");

      foreach ($failedWebhooks as $webhook) {
        $this->reprocessWebhook($webhook, $stripeService);
      }
    }

    return Command::SUCCESS;
  }

  private function reprocessWebhook(WebhookLog $webhook, StripeService $stripeService)
  {
    try {
      $this->line("Processing webhook {$webhook->id} ({$webhook->event_type})...");

      $stripeService->handleWebhook($webhook->payload);

      $this->info("✓ Successfully reprocessed webhook {$webhook->id}");
    } catch (Exception $e) {
      $this->error("✗ Failed to reprocess webhook {$webhook->id}: {$e->getMessage()}");
    }
  }
}
