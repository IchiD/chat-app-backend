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
-   **AuthorizationTest.php** - 認可・セキュリティ機能の包括的テスト
-   **FriendshipTest.php** - 友達機能の API 全体
-   **ConversationTest.php** - チャット機能の API 全体
-   **MessageTest.php** - メッセージ機能の API 全体
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

## 🛡️ **認可・セキュリティテストの詳細**

### **テスト対象機能**

`AuthorizationTest.php` で以下のセキュリティ機能を包括的にテスト：

✅ **基本アクセス制御**

-   **未認証アクセス防止**: 認証が必要な API への未認証アクセスを拒否
-   **他人の操作制限**: 他人の友達申請を承認・拒否できない
-   **会話参加権限**: 参加していない会話にアクセスできない
-   **メッセージ削除権限**: 他人のメッセージを削除できない

✅ **アカウント状態別セキュリティ**

-   **削除済みユーザー制限**: 削除されたアカウントは API アクセス不可
-   **バンされたユーザー制限**: バンされたアカウントは API アクセス不可
-   **トークン自動無効化**: 削除・バンされたユーザーのトークンを自動削除

✅ **友達関係変更後のセキュリティ**

-   **友達解除後のメッセージ制限**: 友達関係解除後はメッセージ送信・閲覧不可
-   **リアルタイム権限チェック**: 会話アクセス時に現在の友達関係を確認

✅ **削除リソースへのアクセス制御**

-   **削除済み会話アクセス制限**: 管理者により削除された会話にアクセス不可
-   **削除済み会話メッセージ制限**: 削除された会話にメッセージ送信不可

✅ **削除・バンユーザーとの交流制限**

-   **友達申請制限**: 削除・バンされたユーザーへの友達申請不可
-   **検索制限**: 削除・バンされたユーザーはフレンド ID 検索に表示されない

### **認可・セキュリティテストの実行**

```bash
# セキュリティテストのみ実行
./vendor/bin/phpunit tests/Feature/AuthorizationTest.php --testdox

# 特定のセキュリティテストのみ実行
./vendor/bin/phpunit --filter test_deleted_user_cannot_access_api

# 認可関連のテストのみ実行
./vendor/bin/phpunit --filter "authorization|access|permission"
```

**期待される出力:**

```
Authorization (Tests\Feature\Authorization)
 ✔ Unauthenticated user cannot access protected api
 ✔ User cannot accept friend request of others
 ✔ User cannot access conversation they do not participate in
 ✔ User cannot delete message of other user
 ✔ Deleted user cannot access api
 ✔ Banned user cannot access api
 ✔ User cannot send message to unfriended conversation
 ✔ User cannot access messages from unfriended conversation
 ✔ User cannot send friend request to deleted user
 ✔ User cannot send friend request to banned user
 ✔ User cannot search deleted or banned users by friend id
 ✔ User cannot access deleted conversation
 ✔ User cannot send message to deleted conversation
 ✔ Token revocation for deleted user
 ✔ Token revocation for banned user

OK (15 tests, 26 assertions)
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

---

## 💬 **メッセージ機能テストの詳細**

### **テスト対象機能**

`MessageTest.php` で以下のメッセージ機能 API をテスト：

✅ **基本機能**

-   メッセージ送信 (`POST /api/conversations/room/{room_token}/messages`)
-   メッセージ一覧取得 (`GET /api/conversations/room/{room_token}/messages`)
-   管理者によるメッセージ削除 (`DELETE /admin/users/{userId}/conversations/{conversationId}/messages/{messageId}`)

✅ **セキュリティ機能**

-   権限のない会話へのメッセージ送信を拒否
-   参加していない会話のメッセージ閲覧を拒否

### **メッセージ機能テストの実行**

```bash
# メッセージ機能テストのみ実行
./vendor/bin/phpunit tests/Feature/MessageTest.php --testdox

# 特定のテストメソッドのみ実行
./vendor/bin/phpunit --filter test_send_message

# メッセージ関連のテストのみ実行
./vendor/bin/phpunit --filter "message"
```

**期待される出力:**

```
Message (Tests\Feature\Message)
 ✔ Send message
 ✔ Get messages
 ✔ Cannot send message to unauthorized conversation
 ✔ Admin can delete message

OK (4 tests, 8 assertions)
```

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

...........................................                       43 / 43 (100%)

Time: 00:00.913, Memory: 50.50 MB

OK (43 tests, 90 assertions)
```

### **失敗時の表示**

失敗した場合はエラーメッセージが表示されます。内容を確認して修正を行ってください。

---

## 💡 **開発時のテスト活用法**

### **開発フロー**

1. **機能開発中**: Unit テストで小刻みに検証
2. **API 実装後**: Feature テストで統合確認
3. **セキュリティ確認**: Authorization テストで権限チェック
4. **リリース前**: 全テスト実行で総合チェック

### **効率的なテスト実行**

```bash
# 開発中（高速チェック）
./vendor/bin/phpunit tests/Unit --testdox

# 機能完成時（統合チェック）
./vendor/bin/phpunit tests/Feature/FriendshipTest.php --testdox

# セキュリティ確認（権限チェック）
./vendor/bin/phpunit tests/Feature/AuthorizationTest.php --testdox

# 最終確認（全体チェック）
./vendor/bin/phpunit --testdox
```

### **セキュリティ重視の開発時**

```bash
# 認証・認可関連の変更時
./vendor/bin/phpunit tests/Feature/AuthTest.php tests/Feature/AuthorizationTest.php --testdox

# 新機能追加時のセキュリティチェック
./vendor/bin/phpunit --filter "auth|authorization|permission|access" --testdox
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

## 📊 **最新のテスト実行結果サマリー**

### **総合テスト結果（2025 年 6 月時点）**

```
PHPUnit 11.5.6 by Sebastian Bergmann and contributors.

...........................................                       43 / 43 (100%)

Time: 00:00.913, Memory: 50.50 MB

OK (43 tests, 90 assertions)
```

### **テストスイート別結果**

| テストスイート        | テスト数 | アサーション数 | ステータス |
| --------------------- | -------- | -------------- | ---------- |
| **AppConfigTest**     | 1        | 1              | ✅ 全成功  |
| **AuthTest**          | 8        | 21             | ✅ 全成功  |
| **AuthorizationTest** | 15       | 26             | ✅ 全成功  |
| **ConversationTest**  | 5        | 11             | ✅ 全成功  |
| **FriendshipTest**    | 9        | 21             | ✅ 全成功  |
| **MessageTest**       | 4        | 8              | ✅ 全成功  |
| **ExampleTest**       | 1        | 2              | ✅ 全成功  |

### **セキュリティテストの重要性**

**AuthorizationTest** では以下の重要なセキュリティ要件をテスト：

-   **15 個のテストケース**で 26 のアサーション
-   **未認証アクセス防止**から**削除済みリソース制御**まで包括的にカバー
-   **リアルタイム権限チェック**により動的な権限変更に対応
-   **トークン自動無効化**によりセキュリティ侵害を防止

このテストスイートにより、アプリケーションのセキュリティが本番環境レベルで保証されています。
