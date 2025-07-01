<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\ChatRoom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateSupportChatRooms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:create-chat-rooms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '既存ユーザーのサポートチャットルームを作成します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('既存ユーザーのサポートチャットルーム作成を開始します...');

        // 削除されていない、バンされていないユーザーを取得
        $users = User::where('is_banned', false)
            ->whereNull('deleted_at')
            ->whereNotNull('email_verified_at')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // 既存のサポートチャットルームがあるかチェック
            $existingSupport = ChatRoom::where('type', 'support_chat')
                ->where('participant1_id', $user->id)
                ->first();

            if ($existingSupport) {
                $skipped++;
                continue;
            }

            try {
                ChatRoom::create([
                    'type' => 'support_chat',
                    'participant1_id' => $user->id,
                    'participant2_id' => null,
                ]);

                $created++;
                $this->line("✓ ユーザー {$user->name} (ID: {$user->id}) のサポートチャットルームを作成しました");
            } catch (\Exception $e) {
                $this->error("✗ ユーザー {$user->name} (ID: {$user->id}) のサポートチャットルーム作成に失敗しました: " . $e->getMessage());
                Log::error('サポートチャットルーム作成エラー', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("完了: {$created}個のサポートチャットルームを作成しました");
        $this->info("スキップ: {$skipped}個のユーザーは既にサポートチャットルームを持っています");

        return Command::SUCCESS;
    }
}