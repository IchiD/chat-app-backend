<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\NewMessageNotification;

class TestPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:test {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'プッシュ通知のテスト送信';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("ユーザーID {$userId} が見つかりません。");
                return 1;
            }
        } else {
            $user = User::first();
            if (!$user) {
                $this->error('ユーザーが見つかりません。');
                return 1;
            }
        }

        $this->info("プッシュ通知をテスト送信中... (ユーザー: {$user->name})");
        
        try {
            $user->notify(new NewMessageNotification(
                'システム',
                'プッシュ通知のテストメッセージです。',
                'http://localhost:3000/chat/1'
            ));
            
            $this->info('✅ プッシュ通知が正常にキューに追加されました。');
            $this->info('キューワーカーが動作していることを確認してください：');
            $this->line('  php artisan queue:work --verbose');
            
        } catch (\Exception $e) {
            $this->error('❌ プッシュ通知の送信に失敗しました：');
            $this->error($e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
