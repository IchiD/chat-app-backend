<?php

namespace App\Services\Billing\WebhookProcessors;

use Carbon\Carbon;

class InvoicePaymentProcessor extends BaseWebhookProcessor
{
    public function process(): void
    {
        // Placeholder implementation
        $this->markAsProcessed();
    }
}
