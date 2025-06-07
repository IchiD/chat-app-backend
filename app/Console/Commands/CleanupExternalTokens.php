<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\ExternalApiToken;
use Carbon\Carbon;

class CleanupExternalTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '期限切れの外部APIトークンを削除します';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = ExternalApiToken::whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->delete();

        Log::info('外部トークンのクリーンアップを実行', ['deleted' => $count]);
        $this->info("{$count} 件の期限切れトークンを削除しました");

        return Command::SUCCESS;
    }
}
