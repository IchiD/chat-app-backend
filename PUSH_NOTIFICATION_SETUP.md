# プッシュ通知設定手順

## 問題
本番環境でプッシュ通知が来ない原因と解決方法

## 原因
1. VAPID鍵が本番環境で未設定
2. キューワーカーが動いていない  
3. フロントエンドでサービスワーカー未登録

## 解決手順

### 1. VAPID鍵の生成（ローカル環境で実行）

```bash
# VAPID鍵ペアを生成
php artisan webpush:vapid
```

実行例：
```
VAPID keys generated successfully:

VAPID_SUBJECT=mailto:your-email@example.com
VAPID_PUBLIC_KEY=BNxqz...（公開鍵）
VAPID_PRIVATE_KEY=abc123...（秘密鍵）
```

### 2. Railway環境変数設定

Railway Dashboard → プロジェクト → バックエンドサービス → Variables に追加：

```
VAPID_SUBJECT=mailto:your-email@example.com
VAPID_PUBLIC_KEY=BNxqz...（生成された公開鍵）
VAPID_PRIVATE_KEY=abc123...（生成された秘密鍵）
```

### 3. キューワーカーの起動確認

```bash
# Railway SSH接続後、キューワーカーを起動
php artisan queue:work --verbose --tries=3 --timeout=60 --sleep=3

# または、バックグラウンドで継続実行
nohup php artisan queue:work --verbose --tries=3 --timeout=60 --sleep=3 > /tmp/queue.log 2>&1 &
```

### 4. フロントエンドのサービスワーカー確認

`/Users/ichikawadaishi/Desktop/chat-app_nuxt/frontend/public/sw.js` が必要：

```javascript
// サービスワーカー（sw.js）の基本実装
self.addEventListener('install', (event) => {
  console.log('Service Worker installed');
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  console.log('Service Worker activated');
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  if (!event.data) return;
  
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    data: data.data || {},
    requireInteraction: data.requireInteraction || false,
    tag: data.tag || 'default',
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  
  const data = event.notification.data;
  const url = data.url || '/';
  
  event.waitUntil(
    clients.openWindow(url)
  );
});
```

## 動作確認手順

### 1. 設定確認

```bash
# Railway SSH接続後
php artisan tinker
config('webpush.vapid.public_key'); // 公開鍵が表示されること
config('webpush.vapid.private_key'); // 秘密鍵が表示されること
exit
```

### 2. テスト通知送信

```bash
# フロントエンドで通知許可を取得後
curl -X POST https://your-railway-domain.com/api/notifications/test \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### 3. ログ確認

```bash
# キューワーカーのログ確認
tail -f /tmp/queue.log

# Laravel ログ確認
tail -f storage/logs/laravel.log
```

## トラブルシューティング

### VAPID鍵が生成されない場合

```bash
# 手動でVAPID鍵を生成
composer require web-push-libs/web-push
php artisan webpush:vapid
```

### キューが処理されない場合

```bash
# キュー確認
php artisan queue:monitor

# 失敗したジョブ確認
php artisan queue:failed

# キューテーブル確認
php artisan tinker
DB::table('jobs')->count();
```

### フロントエンドでエラーが発生する場合

1. ブラウザの開発者ツール → Application → Service Workers で登録確認
2. Network タブで `/api/config` のレスポンス確認
3. Console でエラーメッセージ確認

## セキュリティ注意事項

- VAPID秘密鍵は厳重に管理
- 本番環境ではHTTPS必須
- プッシュ通知は適切な頻度で送信