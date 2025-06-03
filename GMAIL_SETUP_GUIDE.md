# Gmail SMTP セットアップガイド

## 1. Gmail でアプリパスワードの設定

### 前提条件

-   Google アカウントが必要
-   2 段階認証が有効である必要があります

### 手順

1. [Google アカウント セキュリティ](https://myaccount.google.com/security) にアクセス
2. 「2 段階認証プロセス」をクリック
3. 2 段階認証が無効の場合は有効にする
4. ページ下部の「アプリパスワード」をクリック
5. アプリの名前を入力（例：Laravel Chat App）
6. 「作成」をクリック
7. 生成された 16 文字のパスワードをコピー

## 2. .env ファイルの設定

backend/.env ファイルで以下の設定を変更してください：

```bash
# メール設定
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

## 3. 設定例

```bash
# 例（実際の値に置き換えてください）
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=myapp@gmail.com
MAIL_PASSWORD=abcd efgh ijkl mnop
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=myapp@gmail.com
MAIL_FROM_NAME="チャットアプリ"
```

## 4. テスト方法

設定完了後、以下のコマンドでテストできます：

```bash
php artisan email:test your-test-email@example.com
```

## 5. トラブルシューティング

### エラー：Authentication failed

-   アプリパスワードが正しく設定されているか確認
-   2 段階認証が有効になっているか確認
-   ユーザー名（メールアドレス）が正しいか確認

### エラー：Connection timeout

-   ファイアウォールで SMTP ポート（587）が開放されているか確認
-   インターネット接続を確認

### エラー：Too many requests

-   Gmail 送信制限（1 日 500 通）に達していないか確認
-   しばらく時間をおいてから再試行

## 6. 本番環境での注意事項

1. **送信制限**: Gmail は 1 日 500 通の送信制限があります
2. **セキュリティ**: アプリパスワードは安全に管理してください
3. **監視**: メール送信のログを定期的に確認してください
4. **代替手段**: 大量送信が必要な場合は SendGrid、Mailgun 等の利用を検討

## 7. 既存メール機能の動作確認

設定完了後、以下の機能が正常に動作することを確認してください：

-   ユーザー登録時の認証メール
-   パスワードリセットメール
-   メールアドレス変更の確認メール
-   プッシュ通知（該当する場合）
