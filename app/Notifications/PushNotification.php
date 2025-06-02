<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PushNotification extends Notification implements ShouldQueue
{
  use Queueable;

  private $title;
  private $body;
  private $data;
  private $options;

  /**
   * PushNotificationコンストラクタ
   *
   * @param string $title 通知のタイトル
   * @param string $body 通知の本文
   * @param array $data 通知に添付するカスタムデータ
   * @param array $options その他のオプション（アイコン、バッジなど）
   */
  public function __construct(string $title, string $body, array $data = [], array $options = [])
  {
    $this->title = $title;
    $this->body = $body;
    $this->data = $data;
    $this->options = $options;
  }

  /**
   * 通知の配信チャンネルを取得
   *
   * @param mixed $notifiable
   * @return array
   */
  public function via($notifiable)
  {
    return [WebPushChannel::class];
  }

  /**
   * WebPush通知としてのプレゼンテーションを取得
   *
   * @param mixed $notifiable
   * @return \NotificationChannels\WebPush\WebPushMessage
   */
  public function toWebPush($notifiable)
  {
    $message = (new WebPushMessage)
      ->title($this->title)
      ->body($this->body)
      ->data($this->data);

    // オプションの設定
    if (!empty($this->options['icon'])) {
      $message->icon($this->options['icon']);
    }

    if (!empty($this->options['badge'])) {
      $message->badge($this->options['badge']);
    }

    if (!empty($this->options['action'])) {
      $message->action($this->options['action']['title'], $this->options['action']['action']);
    }

    if (!empty($this->options['tag'])) {
      $message->tag($this->options['tag']);
    }

    if (isset($this->options['requireInteraction'])) {
      $message->requireInteraction($this->options['requireInteraction']);
    }

    if (isset($this->options['renotify'])) {
      $message->renotify($this->options['renotify']);
    }

    if (isset($this->options['silent'])) {
      $message->silent($this->options['silent']);
    }

    return $message;
  }
}
