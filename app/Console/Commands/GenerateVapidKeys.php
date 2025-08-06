<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';
    protected $description = 'Generate VAPID keys for web push notifications';

    public function handle()
    {
        if (!class_exists('Minishlink\WebPush\VAPID')) {
            $this->error('web-push-php library is required. Run: composer require minishlink/web-push');
            return 1;
        }

        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        
        $this->info('VAPID keys generated successfully:');
        $this->line('');
        $this->line('VAPID_SUBJECT=mailto:your-email@example.com');
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->line('');
        $this->info('Add these to your Railway environment variables.');
        
        return 0;
    }
}