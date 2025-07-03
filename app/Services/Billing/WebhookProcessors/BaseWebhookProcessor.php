<?php

namespace App\Services\Billing\WebhookProcessors;

use App\Services\BaseService;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Log;

abstract class BaseWebhookProcessor extends BaseService
{
    protected WebhookLog $webhookLog;
    protected array $payload;
    protected array $data;
    protected string $eventId;

    public function __construct(WebhookLog $webhookLog, array $payload)
    {
        $this->webhookLog = $webhookLog;
        $this->payload = $payload;
        $this->data = $payload['data']['object'] ?? [];
        $this->eventId = $payload['id'] ?? 'unknown';
    }

    abstract public function process(): void;

    protected function markAsProcessed(): void
    {
        $this->webhookLog->update([
            'status' => 'processed',
            'processed_at' => now()
        ]);
    }

    protected function markAsFailed(string $errorMessage): void
    {
        $this->webhookLog->update([
            'status' => 'failed',
            'error_message' => $errorMessage
        ]);
    }
}
