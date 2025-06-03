# Railway 環境での Gmail SMTP 設定ガイド

## 設定手順

### 1. Gmail でアプリパスワードを生成

1. [Google アカウント セキュリティ](https://myaccount.google.com/security) にアクセス
2. 2 段階認証を有効にする
3. アプリパスワードを生成する
4. 16 文字のパスワードをコピー

### 2. Railway 環境変数の設定

Railway.app のプロジェクトページで以下の環境変数を設定：

| Variable Name       | Value                  | 備考                     |
| ------------------- | ---------------------- | ------------------------ |
| `MAIL_MAILER`       | `smtp`                 |                          |
| `MAIL_HOST`         | `smtp.gmail.com`       |                          |
| `MAIL_PORT`         | `587`                  |                          |
| `MAIL_USERNAME`     | `your-email@gmail.com` | 実際の Gmail アドレス    |
| `MAIL_PASSWORD`     | `abcd efgh ijkl mnop`  | 生成したアプリパスワード |
| `MAIL_ENCRYPTION`   | `tls`                  |                          |
| `MAIL_FROM_ADDRESS` | `your-email@gmail.com` | MAIL_USERNAME と同じ     |
| `MAIL_FROM_NAME`    | `チャットアプリ`       | アプリ名                 |

### 3. 設定後の確認

1. **デプロイの実行**

    - 環境変数を設定後、Railway が自動でリデプロイします
    - または手動で Redeploy を実行

2. **動作確認**
    - ユーザー登録でメール認証が送信されるかテスト
    - パスワードリセット機能をテスト

## 重要な注意事項

### ⚠️ セキュリティ

-   **アプリパスワードは絶対に公開しないでください**
-   GitHub やコード内には含めないでください
-   Railway 環境変数は暗号化されて保存されます

### 📊 送信制限

-   Gmail: 1 日 500 通の送信制限
-   大量送信が必要な場合は専用サービス（SendGrid、Mailgun 等）を検討

### 🔍 トラブルシューティング

#### メールが送信されない場合

1. Railway Logs でエラーを確認
2. 環境変数が正しく設定されているか確認
3. アプリパスワードが正しいか確認

#### ログの確認方法

```bash
# Railwayダッシュボードでプロジェクトを選択
# 「Deployments」→「View Logs」で確認
```

### 🚀 代替案（推奨）

本番環境では、より信頼性の高いメールサービスの使用を検討：

1. **SendGrid** (Railway Marketplace で利用可能)
2. **Mailgun**
3. **Amazon SES**
4. **Resend** (開発者向け、無料枠あり)

これらのサービスは大量送信、配信率、分析機能に優れています。

## 設定完了後のテスト

### 1. メール送信機能の確認

-   新規ユーザー登録
-   パスワードリセット
-   メールアドレス変更

### 2. ログ監視

Railway ダッシュボードでメール送信のログを定期的に確認してください。
