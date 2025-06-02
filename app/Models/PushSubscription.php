<?php

namespace App\Models;

use NotificationChannels\WebPush\HasPushSubscriptions;

class PushSubscription extends \NotificationChannels\WebPush\PushSubscription
{
  // このクラスは基本的なPushSubscriptionモデルを拡張します
  // カスタムの処理が必要な場合はここに追加します
}
