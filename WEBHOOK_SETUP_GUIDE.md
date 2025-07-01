# Webhook 設定ガイド - 決済が反映されない問題の解決

## 問題の概要

Stripe で決済が成功してもアプリケーション内の subscriptions テーブルにレコードが追加されない問題の解決方法。

## 原因

ローカル開発環境で Stripe Webhook イベントがアプリケーションの Webhook エンドポイント（`/api/stripe/webhook`）に届いていない。

## 解決手順

### 1. Stripe CLI のインストール

```bash
# macOSの場合
brew install stripe/stripe-cli/stripe

# または、公式サイトからダウンロード
# https://stripe.com/docs/stripe-cli
```

### 2. Stripe アカウントにログイン

```bash
stripe login
```

ブラウザが開くので、Stripe アカウントでログインしてください。

### 3. Webhook イベントの転送開始

```bash
stripe listen --forward-to localhost/api/stripe/webhook
```

このコマンドを実行すると：

-   Stripe のテストイベントがローカルエンドポイントに転送される
-   新しい Webhook Secret が表示される（`whsec_`で始まる）

### 4. 新しい Webhook Secret の設定

表示された Webhook Secret を`.env`ファイルに設定：

```bash
# backend/.env
STRIPE_WEBHOOK_SECRET=whsec_新しく表示されたシークレット
```

### 5. アプリケーションの設定更新

```bash
cd backend
php artisan config:clear
php artisan config:cache
```

### 6. テスト実行

1. `stripe listen`を実行状態にする
2. 別のターミナルで決済テストを実行
3. `stripe listen`のターミナルで Webhook イベントの受信を確認

## 確認方法

### Webhook イベントが届いているか確認

```bash
# ログでWebhook処理を確認
tail -f backend/storage/logs/laravel.log | grep webhook

# または管理画面でWebhookログを確認
# http://localhost/admin/billing/webhooks
```

### データベースにレコードが作成されているか確認

```bash
cd backend
php artisan tinker --execute="
echo 'Subscriptions: ' . App\Models\Subscription::count() . PHP_EOL;
echo 'Webhook Logs: ' . App\Models\WebhookLog::count() . PHP_EOL;
"
```

## トラブルシューティング

### 1. データベース接続エラーが発生する場合

```bash
# データベース設定を確認
grep -E "DB_" backend/.env

# データベースサービスを起動
cd backend
docker compose up -d mysql
```

### 2. Webhook エンドポイントにアクセスできない場合

```bash
# ウェブサーバーが起動しているか確認
curl -I http://localhost/api/stripe/webhook

# 期待される応答: HTTP/1.1 405 Method Not Allowed（POSTメソッドのみ受け付け）
```

### 3. Webhook Secret が正しく設定されているか確認

```bash
cd backend
php artisan stripe:config-check
```

## 重要なポイント

1. **`stripe listen`は常に起動している必要があります** - ターミナルを閉じると Webhook が届かなくなります
2. **本番環境では**、Stripe ダッシュボードで Webhook エンドポイントを設定する必要があります
3. **テスト環境**では、必ずテストモードの Stripe 設定を使用してください

## 完了確認

以下を確認できれば設定完了です：

1. ✅ `stripe listen`で Webhook イベントが受信される
2. ✅ 決済後に subscriptions テーブルにレコードが作成される
3. ✅ 管理画面（`/admin/billing/webhooks`）で Webhook ログが確認できる
4. ✅ ユーザーのプランが正しく更新される
