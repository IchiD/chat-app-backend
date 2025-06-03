# Railway メール送信トラブルシューティング

## 🚨 問題: メールが送信されない

### 確認すべき項目

#### 1. Railway 環境変数の設定確認

Railway.app → プロジェクト → Variables で以下が設定されているか確認：

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-actual-email@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-actual-email@gmail.com
MAIL_FROM_NAME=チャットアプリ
```

#### 2. Railway Logs でエラー確認

1. Railway.app → プロジェクト → Deployments
2. 最新のデプロイをクリック
3. "View Logs" でログを確認
4. メール送信時のエラーメッセージを探す

#### 3. よくあるエラーと解決方法

**エラー 1: "Authentication failed"**

```
Swift_TransportException: Authentication failed
```

→ アプリパスワードが間違っている可能性
→ 2 段階認証が無効になっている可能性

**エラー 2: "Connection timeout"**

```
Connection could not be established with host smtp.gmail.com
```

→ Railway 環境変数が正しく設定されていない
→ ファイアウォールの問題

**エラー 3: "Invalid credentials"**

```
Invalid credentials for smtp.gmail.com
```

→ MAIL_USERNAME が間違っている
→ アプリパスワードの形式が間違っている（スペースを含む等）

#### 4. 設定検証手順

**Step 1: Railway 環境でのデバッグ**

Railway Logs で以下のコマンドの出力を確認：

```bash
# これは実際には実行できませんが、ログで確認すべき内容
php artisan email:debug
```

**Step 2: Gmail アプリパスワードの再生成**

1. [Google アカウント セキュリティ](https://myaccount.google.com/security)
2. 既存のアプリパスワードを削除
3. 新しいアプリパスワードを生成
4. Railway の環境変数を更新

**Step 3: テスト用メール送信**

Railway 環境で以下のようなテストを実行：

```bash
php artisan email:test test@example.com
```

#### 5. デプロイの確認

環境変数を変更した後：

1. Railway が自動でリデプロイされるまで待つ
2. または手動で "Redeploy" を実行
3. ログで新しい設定が反映されていることを確認

#### 6. キュー処理の確認

メールがキューに入っている場合：

1. Railway 環境でキューワーカーが動作しているか確認
2. ログでキュー処理のエラーがないか確認

```bash
# キューの状況確認（ログで確認）
php artisan queue:work --once
```

### 🔧 緊急対処法

#### 方法 1: 同期送信に変更

`app/Services/AuthService.php` で `.queue()` を `.send()` に変更：

```php
// 変更前
Mail::to($user->email)->queue(new PreRegistrationEmail($user));

// 変更後（一時的）
Mail::to($user->email)->send(new PreRegistrationEmail($user));
```

#### 方法 2: ローカル環境でのテスト

1. ローカルの `.env` に Gmail 設定を追加
2. ローカルでメール送信をテスト
3. 成功すれば Railway 環境変数に問題がある

### 📞 確認のためのチェックリスト

-   [ ] Railway 環境変数が 8 項目すべて設定されている
-   [ ] Gmail 2 段階認証が有効
-   [ ] アプリパスワードが 16 文字（スペース含む）
-   [ ] MAIL_USERNAME と MAIL_FROM_ADDRESS が同じ
-   [ ] Railway 環境でリデプロイが完了している
-   [ ] Railway Logs でエラーメッセージを確認済み

### 🚀 次のステップ

問題が解決しない場合：

1. SendGrid 等の専用メールサービスへの移行を検討
2. Railway Marketplace でメールアドオンの利用を検討
