<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class MessageSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // 既存のチャットルームを取得
    $chatRooms = ChatRoom::whereNull('deleted_at')->get();

    if ($chatRooms->isEmpty()) {
      $this->command->warn('メッセージを作成するチャットルームが見つかりません。');
      return;
    }

    $totalMessages = 0;

    foreach ($chatRooms as $chatRoom) {
      $messageCount = $this->createMessagesForChatRoom($chatRoom);
      $totalMessages += $messageCount;
    }

    $this->command->info("MessageSeeder: {$totalMessages}個のメッセージを作成しました。");
  }

  /**
   * 特定のチャットルームにメッセージを作成
   */
  private function createMessagesForChatRoom(ChatRoom $chatRoom): int
  {
    $participants = $this->getChatRoomParticipants($chatRoom);

    if ($participants->isEmpty()) {
      return 0;
    }

    // チャットルームのタイプに応じてメッセージ数を決定
    $messageCount = $chatRoom->isGroupChat() ? rand(15, 30) : rand(5, 15);
    $createdMessages = 0;

    // 様々な時間帯でメッセージを作成
    $baseTime = Carbon::now()->subDays(rand(1, 7));

    for ($i = 0; $i < $messageCount; $i++) {
      $sender = $participants->random();

      // ランダムな時間間隔でメッセージを送信
      $sentAt = $baseTime->copy()->addMinutes(rand(1, 180));

      $messageContent = $this->generateMessageContent($chatRoom, $i);

      Message::create([
        'chat_room_id' => $chatRoom->id,
        'sender_id' => $sender->id,
        'admin_sender_id' => null,
        'content_type' => 'text',
        'text_content' => $messageContent,
        'sent_at' => $sentAt,
      ]);

      $createdMessages++;
      $baseTime = $sentAt; // 次のメッセージの基準時間を更新
    }

    // いくつかのメッセージに管理者メッセージも追加
    if (rand(1, 100) <= 30) { // 30%の確率
      $this->createAdminMessage($chatRoom, $baseTime->addMinutes(rand(30, 120)));
      $createdMessages++;
    }

    return $createdMessages;
  }

  /**
   * チャットルームの参加者を取得
   */
  private function getChatRoomParticipants(ChatRoom $chatRoom)
  {
    if ($chatRoom->isGroupChat() && $chatRoom->group) {
      return $chatRoom->group->activeMembers()->with('user')->get()->pluck('user');
    }

    // メンバーチャットの場合
    $participants = collect();
    if ($chatRoom->participant1 && $this->isActiveUser($chatRoom->participant1)) {
      $participants->push($chatRoom->participant1);
    }
    if ($chatRoom->participant2 && $this->isActiveUser($chatRoom->participant2)) {
      $participants->push($chatRoom->participant2);
    }

    return $participants;
  }

  /**
   * メッセージ内容を生成
   */
  private function generateMessageContent(ChatRoom $chatRoom, int $messageIndex): string
  {
    if ($chatRoom->isGroupChat()) {
      return $this->getGroupChatMessage($messageIndex);
    } elseif ($chatRoom->type === 'support_chat') {
      return $this->getSupportChatMessage($messageIndex);
    } else {
      return $this->getFriendChatMessage($messageIndex);
    }
  }

  /**
   * グループチャット用のメッセージを生成
   */
  private function getGroupChatMessage(int $index): string
  {
    $groupMessages = [
      'お疲れ様です！',
      '今日も一日よろしくお願いします',
      'プロジェクトの進捗はいかがですか？',
      'ランチの時間ですね',
      '今度の会議の件ですが...',
      'いい天気ですね！',
      'みなさん、体調はいかがですか？',
      '新しい企画のアイデアがあります',
      '来週の予定を確認させてください',
      'お疲れ様でした！',
      'おはようございます！',
      '会議室の予約を取りました',
      'コーヒーブレイクしませんか？',
      '資料の共有ありがとうございました',
      'データの分析結果が出ました',
      '質問があります',
      'すばらしいアイデアですね！',
      '確認しました、ありがとうございます',
      'スケジュール調整をお願いします',
      '詳細な情報を共有します',
    ];

    return $groupMessages[array_rand($groupMessages)];
  }

  /**
   * 友達チャット用のメッセージを生成
   */
  private function getFriendChatMessage(int $index): string
  {
    $friendMessages = [
      'こんにちは！',
      'お疲れ様！',
      '今度会いませんか？',
      'ありがとう！',
      'どうだった？',
      'また後で連絡します',
      '了解！',
      '楽しかったです',
      'よろしくお願いします',
      'お疲れ様でした',
      'おはよう！',
      'これどう思う？',
      'いいですね！',
      'また今度',
      'ありがとうございます',
      'そうですね',
      'すごいですね！',
      'わかりました',
      'お気をつけて',
      'また明日',
    ];

    return $friendMessages[array_rand($friendMessages)];
  }

  /**
   * サポートチャット用のメッセージを生成
   */
  private function getSupportChatMessage(int $index): string
  {
    $supportMessages = [
      'お世話になっております',
      'ログインできないのですが、どうすればよいでしょうか？',
      'パスワードをリセットしたいです',
      'アカウントの設定について教えてください',
      'エラーが発生しています',
      '機能の使い方がわからないです',
      'アップデートについて質問があります',
      'サービスが利用できません',
      'データが表示されないのですが',
      '支払いについて相談があります',
      'アカウントを削除したいです',
      'セキュリティについて心配があります',
      '新しい機能について知りたいです',
      'バグを見つけました',
      'お問い合わせありがとうございます',
      '解決しました、ありがとうございました',
      '引き続きよろしくお願いします',
      'サポートを受けたいです',
      '詳細を教えてください',
      'ご対応いただきありがとうございます',
    ];

    return $supportMessages[array_rand($supportMessages)];
  }

  /**
   * 管理者メッセージを作成
   */
  private function createAdminMessage(ChatRoom $chatRoom, Carbon $sentAt): void
  {
    $adminMessages = [
      'システムメンテナンスのお知らせです',
      '新機能が追加されました',
      'アップデートが完了しました',
      'ご利用ありがとうございます',
      'サービス向上のため、アンケートにご協力ください',
    ];

    Message::create([
      'chat_room_id' => $chatRoom->id,
      'sender_id' => null,
      'admin_sender_id' => 1, // AdminSeederで作成された管理者を想定
      'content_type' => 'text',
      'text_content' => $adminMessages[array_rand($adminMessages)],
      'sent_at' => $sentAt,
    ]);
  }

  /**
   * ユーザーがアクティブかどうかをチェック
   */
  private function isActiveUser(User $user): bool
  {
    return $user->is_verified && !$user->is_banned && is_null($user->deleted_at);
  }
}
