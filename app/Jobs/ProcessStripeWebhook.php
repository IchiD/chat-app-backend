<?php

namespace App\Jobs;

use App\Services\StripeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessStripeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(StripeService $stripeService): void
    {
        try {
            Log::info('Processing Stripe webhook job', [
                'event_id' => $this->payload['id'] ?? 'unknown',
                'event_type' => $this->payload['type'] ?? 'unknown'
            ]);

            $stripeService->handleWebhook($this->payload);

            Log::info('Stripe webhook job completed successfully', [
                'event_id' => $this->payload['id'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            Log::error('Stripe webhook job failed', [
                'event_id' => $this->payload['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('Stripe webhook job permanently failed', [
            'event_id' => $this->payload['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
