# 本番環境デプロイガイド（Railway）

## 📱 プッシュ通知とメール通知の完全動作設定

このガイドでは、Railway環境でプッシュ通知とメール通知を非同期で処理できるようにする手順を説明します。

## 前提条件

- Railway CLIがインストール済み
- Railwayプロジェクトが作成済み
- バックエンドサービスがデプロイ済み

## 1. 環境変数の設定

Railwayダッシュボードで以下の環境変数を設定してください：

### 必須環境変数

```bash
# アプリケーション基本設定
APP_NAME=LumoChat
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-backend-domain.railway.app
APP_FRONTEND_URL=https://chat-app-frontend-sigma-puce.vercel.app

# データベース（Railwayが自動設定）
DATABASE_URL=mysql://...（自動設定）

# キュー設定
QUEUE_CONNECTION=database

# メール設定（Gmail）
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-specific-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

# VAPID鍵（プッシュ通知用）
VAPID_SUBJECT=mailto:admin@example.com
VAPID_PUBLIC_KEY=BDCwu-L2JVFI_CSxL7qRltNepRbaxJpMWB17glancRkE_DbCQdd7qD4DKiFHabVMn_u6wWryrQI-X1tg-Umc5pI
VAPID_PRIVATE_KEY=mkEgvjsURyzZwI3xlH5woShCWbliyvSsPXgFZ2TH65w
```

### オプション環境変数（設定済みの場合）

```bash
# Stripe（決済機能）
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...

# Google OAuth
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
```

## 2. キューワーカーの起動設定

### 方法1: Procfileを使用（推奨）

既にProcfileが設定されています：

```
web: php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-jobs=1000 --max-time=3600
```

Railwayでworkerプロセスを有効化：

1. Railwayダッシュボードでプロジェクトを開く
2. バックエンドサービスを選択
3. 「Settings」タブへ移動
4. 「Deploy」セクションで「Start Command」を確認
5. 新しいサービスを追加してworkerプロセスを実行

### 方法2: 別サービスとしてWorkerを起動

1. Railwayダッシュボードで「New Service」をクリック
2. 同じGitHubリポジトリを選択
3. 環境変数を共有（Database URLなど）
4. Start Commandを設定：
   ```bash
   php artisan queue:work database --sleep=3 --tries=3 --timeout=90
   ```

## 3. デプロイ手順

### ローカルからデプロイ

```bash
# 変更をコミット
git add .
git commit -m "feat: プッシュ通知とメール通知を実装"

# Railwayにプッシュ
git push origin main

# または Railway CLIを使用
railway up
```

### デプロイ後の確認

```bash
# Railway SSH接続
railway ssh

# マイグレーション実行
php artisan migrate

# キューテーブル確認
php artisan queue:table
php artisan migrate

# VAPID鍵の確認
php artisan tinker
>>> config('webpush.vapid.public_key')
>>> config('webpush.vapid.private_key')
>>> exit

# キューワーカー手動テスト
php artisan queue:work --once

# プッシュ通知テスト
php artisan push:test
```

## 4. 動作確認

### フロントエンド側の確認事項

1. **サービスワーカー登録**
   - ブラウザの開発者ツール → Application → Service Workers
   - `sw.js`が登録されていることを確認

2. **通知許可**
   - 通知の許可ダイアログが表示される
   - 許可後、プッシュサブスクリプションが登録される

3. **VAPID公開鍵取得**
   - Network タブで`/api/config`へのリクエストを確認
   - レスポンスに`vapid.publicKey`が含まれていること

### バックエンド側の確認事項

1. **キューワーカー動作確認**
   ```bash
   railway ssh
   
   # キューの状態確認
   php artisan queue:monitor default
   
   # 失敗ジョブ確認
   php artisan queue:failed
   
   # ログ確認
   tail -f storage/logs/laravel.log
   ```

2. **テスト通知送信**
   ```bash
   # プッシュ通知テスト
   php artisan push:test
   
   # または Tinkerで手動送信
   php artisan tinker
   >>> $user = App\Models\User::first();
   >>> $user->notify(new App\Notifications\PushNotification('テスト', 'これはテストです'));
   ```

## 5. トラブルシューティング

### プッシュ通知が届かない場合

1. **VAPID鍵の確認**
   ```bash
   railway ssh
   php artisan config:cache
   php artisan config:clear
   ```

2. **プッシュサブスクリプション確認**
   ```bash
   php artisan tinker
   >>> App\Models\User::first()->pushSubscriptions
   ```

3. **キューワーカー再起動**
   ```bash
   railway restart
   ```

### メール通知が届かない場合

1. **Gmail設定確認**
   - 2段階認証が有効
   - アプリパスワードが正しく設定されている
   - 送信制限に達していない

2. **メールログ確認**
   ```bash
   railway ssh
   grep -i "mail\|email" storage/logs/laravel.log | tail -20
   ```

### キューが処理されない場合

1. **キューワーカーが起動しているか確認**
   ```bash
   railway ssh
   ps aux | grep queue:work
   ```

2. **データベース接続確認**
   ```bash
   php artisan db:show
   ```

3. **手動でキュー処理**
   ```bash
   php artisan queue:work --once --verbose
   ```

## 6. パフォーマンス最適化

### キューワーカーの調整

Procfileの設定を環境に応じて調整：

```
# 軽量版（メモリ節約）
worker: php artisan queue:work database --sleep=5 --tries=2 --timeout=60 --max-jobs=100

# 標準版（バランス型）
worker: php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-jobs=1000 --max-time=3600

# 高負荷版（パフォーマンス重視）
worker: php artisan queue:work database --sleep=1 --tries=5 --timeout=120 --max-jobs=5000 --max-time=7200
```

### スケーリング

複数のワーカーインスタンスを起動：

1. Railwayダッシュボードで「Scale」セクション
2. Workerサービスのインスタンス数を増やす
3. または複数のWorkerサービスを作成

## 7. 監視とログ

### Railway Logs

```bash
# CLIでログ確認
railway logs

# 特定サービスのログ
railway logs --service=worker
```

### カスタムログ

```php
// 通知送信時のログ
Log::channel('notifications')->info('Push notification sent', [
    'user_id' => $user->id,
    'title' => $title,
    'timestamp' => now()
]);
```

## 8. セキュリティ注意事項

- VAPID秘密鍵は環境変数で管理（コードにハードコーディングしない）
- 本番環境では必ずHTTPSを使用
- プッシュ通知のペイロードサイズは4KB以下に制限
- センシティブな情報は通知本文に含めない

## 完了チェックリスト

- [ ] 環境変数（VAPID鍵、メール設定）を設定
- [ ] Procfileにworkerプロセスを定義
- [ ] Railwayでworkerサービスを起動
- [ ] データベースマイグレーション実行
- [ ] フロントエンドでサービスワーカー登録確認
- [ ] テスト通知の送信成功
- [ ] キューワーカーのログ確認
- [ ] 本番環境でのHTTPS確認

以上で、Railwayでプッシュ通知とメール通知が非同期で動作するようになります！