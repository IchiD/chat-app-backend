<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $senderName;
    private $messagePreview;
    private $chatUrl;

    public function __construct(string $senderName, string $messagePreview, string $chatUrl)
    {
        $this->senderName = $senderName;
        $this->messagePreview = $messagePreview;
        $this->chatUrl = $chatUrl;
    }

    /**
     * 通知の配信チャンネルを取得
     */
    public function via($notifiable)
    {
        return ['mail', WebPushChannel::class];
    }

    /**
     * メール通知の構築
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject($this->senderName . 'からメッセージが届きました')
            ->greeting('新しいメッセージが届きました')
            ->line($this->senderName . 'からメッセージが届きました。')
            ->line('メッセージ内容: ' . $this->messagePreview)
            ->action('メッセージを確認', $this->chatUrl)
            ->line('チャットアプリをご利用いただき、ありがとうございます。');
    }

    /**
     * WebPush通知の構築
     */
    public function toWebPush($notifiable, $notification)
    {
        return WebPushMessage::create()
            ->title($this->senderName . 'からメッセージが届きました')
            ->body($this->messagePreview)
            ->icon('/favicon.ico')
            ->badge('/favicon.ico')
            ->data([
                'url' => $this->chatUrl,
                'sender' => $this->senderName,
                'message' => $this->messagePreview
            ])
            ->requireInteraction(false)
            ->tag('new-message');
    }
}