# テストの実行方法

開発環境および本番環境では MySQL を利用していますが、テストでは既存のデータベース
を汚さないようにメモリ上の SQLite を使用します。

## 📋 **クイックスタート**

1. **依存パッケージのインストール**

    ```bash
    composer install
    ```

2. **環境設定**
   テストではメモリ上の SQLite データベースを使用します。`phpunit.xml` に設定済みのため、追加設定は不要です。必要に応じて `.env` ファイルを作成し `APP_KEY` を生成してください。

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3. **全テスト実行**

    ```bash
    ./vendor/bin/phpunit
    ```

4. **読みやすい形式でテスト実行**
    ```bash
    ./vendor/bin/phpunit --testdox
    ```

---

## 🧪 **テストスイート構成**

### **Feature Tests（機能テスト）** - `tests/Feature/`

API エンドポイント全体の動作を統合的にテスト

-   **AuthTest.php** - 認証機能の API 全体
-   **FriendshipTest.php** - 友達機能の API 全体
-   **ConversationTest.php** - チャット機能の API 全体
-   **AppConfigTest.php** - アプリ設定 API

### **Unit Tests（単体テスト）** - `tests/Unit/`

個々のモデルやクラスの機能を独立してテスト

-   **UserModelTest.php** - User モデルの個別機能
-   **ConversationModelTest.php** - Conversation モデルの個別機能

---

## 🔐 **認証機能テストの詳細**

### **テスト対象機能**

`AuthTest.php` で以下の認証機能 API をテスト：

✅ **基本認証機能**

-   ユーザー登録 (`POST /api/register`)
-   メール認証 (`GET /api/verify`)
-   ログイン (`POST /api/login`)
-   ログアウト (`POST /api/logout`)
-   パスワードリセット (`POST /api/password/reset`)

✅ **エラーハンドリング**

-   不正な認証情報でのログイン失敗
-   未認証ユーザーのログイン制限
-   無効なメール認証トークン

### **認証機能テストの実行**

```bash
# 認証機能テストのみ実行
./vendor/bin/phpunit tests/Feature/AuthTest.php --testdox

# 特定のテストメソッドのみ実行
./vendor/bin/phpunit --filter test_user_can_login

# 認証関連のテストのみ実行
./vendor/bin/phpunit --filter "auth|login|register"
```

**期待される出力:**

```
Auth (Tests\Feature\Auth)
 ✔ User can register
 ✔ Email verification
 ✔ User can login
 ✔ Login fails with invalid credentials
 ✔ User can logout
 ✔ Password reset
 ✔ Unverified user cannot login
 ✔ Invalid verification token

OK (8 tests, 21 assertions)
```

---

## 🎯 **友達機能テストの詳細**

### **テスト対象機能**

`FriendshipTest.php` で以下の友達機能 API をテスト：

✅ **基本機能**

-   友達申請の送信 (`POST /api/friends/requests`)
-   友達申請の承認 (`POST /api/friends/requests/accept`)
-   友達申請の拒否 (`POST /api/friends/requests/reject`)
-   友達申請のキャンセル (`DELETE /api/friends/requests/cancel/{id}`)
-   友達関係の解除 (`DELETE /api/friends/unfriend`)
-   友達一覧の取得 (`GET /api/friends`)

✅ **エラーハンドリング**

-   重複申請の防止
-   自分自身への申請防止
-   存在しないユーザーへの申請エラー

### **友達機能テストの実行**

```bash
# 友達機能テストのみ実行
./vendor/bin/phpunit tests/Feature/FriendshipTest.php --testdox

# 特定のテストメソッドのみ実行
./vendor/bin/phpunit --filter test_send_friend_request

# 友達申請関連のテストのみ実行
./vendor/bin/phpunit --filter "friend_request"
```

**期待される出力:**

```
Friendship (Tests\Feature\Friendship)
 ✔ Send friend request
 ✔ Accept friend request
 ✔ Reject friend request
 ✔ Cancel friend request
 ✔ Unfriend
 ✔ Get friends list
 ✔ Prevent duplicate requests
 ✔ Cannot send request to self
 ✔ Error when user not found

OK (9 tests, 21 assertions)
```

---

## 💬 **チャット機能テストの詳細**

### **テスト対象機能**

`ConversationTest.php` で以下のチャット機能 API をテスト：

✅ **基本機能**

-   会話の作成 (`POST /api/conversations`)
-   会話一覧の取得 (`GET /api/conversations`)
-   友達間での既存会話の再利用
-   管理者による会話削除 (`DELETE /admin/users/{userId}/conversations/{conversationId}`)

✅ **セキュリティ機能**

-   友達でない相手との会話作成を拒否
-   重複会話の防止（既存の会話がある場合は同じ会話を返す）

### **チャット機能テストの実行**

```bash
# チャット機能テストのみ実行
./vendor/bin/phpunit tests/Feature/ConversationTest.php --testdox

# 特定のテストメソッドのみ実行
./vendor/bin/phpunit --filter test_create_conversation

# 会話作成関連のテストのみ実行
./vendor/bin/phpunit --filter "conversation"
```

**期待される出力:**

```
Conversation (Tests\Feature\Conversation)
 ✔ Create conversation
 ✔ Create conversation between friends returns existing conversation
 ✔ Cannot create conversation with non friend
 ✔ Get conversation list
 ✔ Admin can delete conversation

OK (5 tests, 11 assertions)
```

### **🚨 注意：メッセージ機能のテストが未実装**

現在、**メッセージ送信・取得機能のテストは未実装**です。以下の機能についてテストの追加が推奨されます：

**未テスト機能:**

-   メッセージ送信 (`POST /api/conversations/room/{room_token}/messages`)
-   メッセージ一覧取得 (`GET /api/conversations/room/{room_token}/messages`)
-   会話の既読処理 (`POST /api/conversations/{conversation}/read`)
-   room_token による会話情報取得 (`GET /api/conversations/token/{room_token}`)

**今後のテスト実装項目:**

1. MessageTest.php の作成
2. メッセージ送信・取得のテストケース
3. 友達関係変更時のメッセージアクセス制御テスト
4. プッシュ通知機能のテスト

---

## 🔧 **詳細なテスト実行オプション**

### **1. テストスイート別実行**

```bash
# Unitテストのみ実行（高速）
./vendor/bin/phpunit tests/Unit --testdox

# Featureテストのみ実行（統合テスト）
./vendor/bin/phpunit tests/Feature --testdox
```

### **2. 特定テストの実行**

```bash
# 特定のテストファイルを実行
./vendor/bin/phpunit tests/Feature/FriendshipTest.php
./vendor/bin/phpunit tests/Unit/UserModelTest.php

# 特定のテストメソッドのみ実行
./vendor/bin/phpunit --filter test_friend_id_is_generated_and_unique
```

### **3. テスト情報の確認**

```bash
# 利用可能なテストの一覧を表示
./vendor/bin/phpunit --list-tests

# 利用可能なテストスイートの一覧を表示
./vendor/bin/phpunit --list-suites
```

### **4. デバッグ・詳細表示**

```bash
# デバッグ情報を含めてテスト実行
./vendor/bin/phpunit --debug

# 警告やエラーの詳細を表示
./vendor/bin/phpunit --display-warnings --display-errors

# 非推奨機能の使用箇所を表示
./vendor/bin/phpunit --display-deprecations
```

### **5. エラー時の動作制御**

```bash
# エラー発生時に即座に停止
./vendor/bin/phpunit --stop-on-error

# 失敗発生時に即座に停止
./vendor/bin/phpunit --stop-on-failure
```

### **6. コードカバレッジレポート（xdebug が必要）**

```bash
# HTMLでカバレッジレポートを生成
./vendor/bin/phpunit --coverage-html coverage

# テキスト形式でカバレッジを表示
./vendor/bin/phpunit --coverage-text
```

### **7. 結果出力オプション**

```bash
# テスト結果をJUnit XML形式で出力
./vendor/bin/phpunit --log-junit results.xml

# テスト結果をJSON形式で出力
./vendor/bin/phpunit --log-json results.json
```

---

## 📊 **テスト結果の読み方**

### **成功時の表示**

```
PHPUnit 11.5.6 by Sebastian Bergmann and contributors.

.........                                                           9 / 9 (100%)

Time: 00:00.467, Memory: 44.50 MB

OK (9 tests, 21 assertions)
```

### **失敗時の表示**

失敗した場合はエラーメッセージが表示されます。内容を確認して修正を行ってください。

---

## 💡 **開発時のテスト活用法**

### **開発フロー**

1. **機能開発中**: Unit テストで小刻みに検証
2. **API 実装後**: Feature テストで統合確認
3. **リリース前**: 全テスト実行で総合チェック

### **効率的なテスト実行**

```bash
# 開発中（高速チェック）
./vendor/bin/phpunit tests/Unit --testdox

# 機能完成時（統合チェック）
./vendor/bin/phpunit tests/Feature/FriendshipTest.php --testdox

# 最終確認（全体チェック）
./vendor/bin/phpunit --testdox
```

これらのオプションを組み合わせることで、効率的にテストの開発・デバッグを行うことができます。

---

## 📧 **メール送信処理について**

### **現在の実装状況（2025 年 6 月時点）**

アプリケーション内のメール送信は**全て同期処理**で統一されています。

### **📋 統一されたメール送信箇所**

以下の 4 箇所で`Mail::to()->send()`を使用:

1. **新規登録時の確認メール** (`AuthService.php:60`)

    ```php
    Mail::to($user->email)->send(new PreRegistrationEmail($user));
    ```

2. **確認メール再送信** (`AuthController.php:484`)

    ```php
    Mail::to($user->email)->send(new PreRegistrationEmail($user));
    ```

3. **メールアドレス変更確認** (`AuthService.php:415`)

    ```php
    Mail::to($newEmail)->send(new EmailChangeVerification($user, $token));
    ```

4. **パスワードリセット完了通知** (`SendPasswordResetSuccessNotification.php:22`)
    ```php
    Mail::to($user->email)->send(new PasswordResetSuccess($user));
    ```

### **🧪 テスト環境での動作**

-   **テスト実行時**: メール送信は実際には行われません（Laravel のデフォルト動作）
-   **同期処理**: テスト中でも認証フローが正常に動作
-   **検証方法**: データベースの状態変更（is_verified フラグ等）で確認

### **⚡ パフォーマンスへの影響**

**同期処理のため以下の特徴があります:**

✅ **メリット**

-   実装がシンプルで確実
-   エラーハンドリングが直接的
-   デバッグが容易

⚠️ **注意点**

-   ユーザー登録・パスワードリセット時のレスポンス時間が若干長くなる
-   メール送信に失敗した場合、ユーザーに直接エラーが返される

### **🔮 将来的な非同期化への準備**

非同期化が必要になった場合の準備は完了しています:

-   **キューシステム設定済み**: `config/queue.php` で database ドライバー設定
-   **Procfile 準備済み**: キューワーカー起動設定が存在
-   **一括変更可能**: 4 箇所の`send()`を`queue()`に変更するだけ

```bash
# 将来的な非同期化時の変更例
- Mail::to($user->email)->send(new PreRegistrationEmail($user));
+ Mail::to($user->email)->queue(new PreRegistrationEmail($user));
```

---
