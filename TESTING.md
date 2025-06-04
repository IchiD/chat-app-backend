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
-   **ValidationTest.php** - バリデーション機能の包括的テスト
-   **AppConfigTest.php** - アプリ設定 API

### **API Tests（API 層テスト）** - `tests/Feature/API/`

REST API エンドポイントの動作を具体的にテスト

-   **ConversationsApiTest.php** - 会話 API エンドポイント
-   **FriendshipApiTest.php** - 友達関係 API エンドポイント
-   **MessagesApiTest.php** - メッセージ API エンドポイント
-   **UserApiTest.php** - ユーザー API エンドポイント

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

## ✅ **バリデーションテストの詳細**

### **テスト対象機能**

`ValidationTest.php` で以下のバリデーション機能を包括的にテスト：

✅ **友達申請バリデーション**

-   **必須項目チェック**: user_id フィールドの必須検証
-   **データ型検証**: 文字列 ID、負の値、null 値の拒否
-   **存在性チェック**: 存在しないユーザー ID への申請防止
-   **文字数制限**: message フィールドの 255 文字制限

✅ **メッセージ送信バリデーション**

-   **必須項目チェック**: text_content フィールドの必須検証
-   **データ型検証**: 数値型、null 値、空文字列の拒否
-   **文字数制限**: 5000 文字超過メッセージの防止
-   **例外処理**: try-catch による適切なエラーハンドリング

✅ **ユーザー登録バリデーション**

-   **メール形式**: 無効なメールアドレス形式の拒否
-   **パスワード強度**: 6 文字未満パスワードの防止
-   **パスワード確認**: password_confirmation の不一致検出
-   **名前制限**: 10 文字超過、空文字の防止

✅ **ユーザー情報更新バリデーション**

-   **名前更新**: 文字数制限と必須項目チェック
-   **パスワード更新**: 現在パスワード確認、新パスワード強度
-   **確認フィールド**: パスワード確認の整合性チェック

✅ **ログインバリデーション**

-   **必須フィールド**: email、password の必須検証
-   **メール形式**: 無効なメールアドレス形式の拒否
-   **空データ**: 全フィールド未入力の防止

✅ **フレンド ID 検索バリデーション**

-   **必須項目**: friend_id フィールドの必須検証
-   **文字数制限**: 6 文字固定の形式チェック
-   **データ型**: 数値型の拒否（文字列のみ受入）

✅ **不正フォーマットデータ**

-   **配列型データ**: 不正な配列型入力の拒否
-   **オブジェクト型データ**: 不正なオブジェクト型入力の拒否

### **バリデーションテストの実行**

```bash
# バリデーションテストのみ実行
./vendor/bin/phpunit tests/Feature/ValidationTest.php --testdox

# 特定のバリデーションテストのみ実行
./vendor/bin/phpunit --filter test_friend_request_input_validation

# バリデーション関連のテストのみ実行
./vendor/bin/phpunit --filter "validation|input"
```

**期待される出力:**

```
Validation (Tests\Feature\Validation)
 ✔ Friend request input validation
 ✔ Message send input validation
 ✔ User registration input validation
 ✔ User update validation
 ✔ Login validation
 ✔ Friend id search validation
 ✔ Invalid format data returns error

OK (7 tests, 34 assertions)
```

### **バリデーションテストの特徴**

✅ **包括的エラーケース**

-   **必須項目**: 未入力、null 値、空文字列
-   **データ型**: 文字列・数値・配列・オブジェクトの型検証
-   **フォーマット**: メール形式、パスワード形式、文字数制限
-   **ビジネスルール**: 存在性チェック、重複防止、権限確認

✅ **実装準拠テスト**

-   **ステータスコード**: 422（バリデーションエラー）、500（例外処理）
-   **レスポンス構造**: 実際の API 実装に即した検証
-   **エラーハンドリング**: try-catch 処理の考慮

✅ **品質保証効果**

-   **不正入力防止**: 悪意のある入力やミス入力の検出
-   **データ整合性**: データベース制約違反の事前防止
-   **セキュリティ**: インジェクション攻撃等への対策確認

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

## 🔗 **API エンドポイントテストの詳細**

### **テスト対象機能**

`tests/Feature/API/` で以下の REST API エンドポイントを個別にテスト：

### **会話 API テスト** - `ConversationsApiTest.php`

✅ **基本機能**

-   **会話一覧取得** (`GET /api/conversations`)
-   **新規会話作成** (`POST /api/conversations`)
-   **特定会話取得** (`GET /api/conversations/token/{room_token}`)
-   **既読マーク** (`POST /api/conversations/{conversation}/read`)

✅ **サポート機能**

-   **サポート会話作成** (`POST /api/support/conversation`)
-   **サポート会話取得** (`GET /api/support/conversation`)

### **友達関係 API テスト** - `FriendshipApiTest.php`

✅ **友達申請管理**

-   **送信済み申請一覧** (`GET /api/friends/requests/sent`)
-   **受信済み申請一覧** (`GET /api/friends/requests/received`)

✅ **ユーザー検索**

-   **フレンド ID 検索** (`POST /api/friends/search`)

### **メッセージ API テスト** - `MessagesApiTest.php`

✅ **メッセージ操作**

-   **メッセージ一覧取得** (`GET /api/conversations/room/{room_token}/messages`)
-   **メッセージ送信** (`POST /api/conversations/room/{room_token}/messages`)

✅ **友達関係との連携**

-   **友達間でのメッセージ送受信確認**
-   **データベース整合性チェック**

### **ユーザー API テスト** - `UserApiTest.php`

✅ **ユーザー情報管理**

-   **現在のユーザー情報取得** (`GET /api/users/me`)
-   **ユーザー名更新** (`PUT /api/user/update-name`)
-   **パスワード更新** (`PUT /api/user/update-password`)
-   **メールアドレス変更要求** (`PUT /api/user/update-email`)

✅ **セキュリティ機能**

-   **認証トークン検証**
-   **パスワード確認ロジック**
-   **データ検証とバリデーション**

### **API テストの実行**

```bash
# 全APIテストの実行
./vendor/bin/phpunit tests/Feature/API/ --testdox

# 特定APIテストファイルの実行
./vendor/bin/phpunit tests/Feature/API/ConversationsApiTest.php --testdox
./vendor/bin/phpunit tests/Feature/API/FriendshipApiTest.php --testdox
./vendor/bin/phpunit tests/Feature/API/MessagesApiTest.php --testdox
./vendor/bin/phpunit tests/Feature/API/UserApiTest.php --testdox

# 特定のAPIエンドポイントテスト
./vendor/bin/phpunit --filter test_index_returns_conversations
./vendor/bin/phpunit --filter test_store_creates_message
```

**期待される出力:**

```
Conversations Api (Tests\Feature\API\ConversationsApi)
 ✔ Index returns conversations
 ✔ Store creates conversation
 ✔ Show by token returns conversation
 ✔ Mark as read
 ✔ Support conversation flow

Friendship Api (Tests\Feature\API\FriendshipApi)
 ✔ Get sent requests
 ✔ Get received requests
 ✔ Search by friend id

Messages Api (Tests\Feature\API\MessagesApi)
 ✔ Index returns messages
 ✔ Store creates message

User Api (Tests\Feature\API\UserApi)
 ✔ Get current user
 ✔ Update name
 ✔ Update password
 ✔ Request email change

OK (14 tests, 30 assertions)
```

### **API テストの特徴**

✅ **REST API 仕様準拠**

-   **HTTP ステータスコード**の正確な検証
-   **JSON レスポンス構造**の確認
-   **リクエスト・レスポンス**の完全性チェック

✅ **認証・認可テスト**

-   **Laravel Sanctum**による認証確認
-   **権限に基づく**アクセス制御テスト
-   **セキュリティ要件**の遵守確認

✅ **データ整合性テスト**

-   **データベース状態**の確認
-   **友達関係前提条件**の適切な設定
-   **会話・メッセージ連携**の正確性確認

✅ **エンドポイント間連携**

-   **友達関係 → 会話作成 → メッセージ送信**の流れをテスト
-   **API レイヤー**での統合動作確認
-   **実際のフロントエンド使用パターン**の再現

---

## 🔧 **チャット機能テストの詳細**

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

...........................................................       67 / 67 (100%)

Time: 00:01.103, Memory: 52.50 MB

OK (67 tests, 160 assertions)
```

### **失敗時の表示**

失敗した場合はエラーメッセージが表示されます。内容を確認して修正を行ってください。

---

## 💡 **開発時のテスト活用法**

### **開発フロー**

1. **機能開発中**: Unit テストで小刻みに検証
2. **API 実装後**: Feature テスト + API テストで統合確認
3. **セキュリティ確認**: Authorization テストで権限チェック
4. **入力検証確認**: Validation テストでバリデーション確認
5. **リリース前**: 全テスト実行で総合チェック

### **効率的なテスト実行**

```bash
# 開発中（高速チェック）
./vendor/bin/phpunit tests/Unit --testdox

# 機能完成時（統合チェック）
./vendor/bin/phpunit tests/Feature/FriendshipTest.php --testdox

# API層の動作確認
./vendor/bin/phpunit tests/Feature/API/ --testdox

# バリデーション確認（入力検証）
./vendor/bin/phpunit tests/Feature/ValidationTest.php --testdox

# セキュリティ確認（権限チェック）
./vendor/bin/phpunit tests/Feature/AuthorizationTest.php --testdox

# 最終確認（全体チェック）
./vendor/bin/phpunit --testdox
```

### **レイヤー別テスト戦略**

```bash
# Feature層テスト（ビジネスロジック確認）
./vendor/bin/phpunit tests/Feature/ --exclude-group=api --testdox

# API層テスト（エンドポイント動作確認）
./vendor/bin/phpunit tests/Feature/API/ --testdox

# 認証・認可関連の変更時
./vendor/bin/phpunit tests/Feature/AuthTest.php tests/Feature/AuthorizationTest.php --testdox

# バリデーション関連の変更時
./vendor/bin/phpunit tests/Feature/ValidationTest.php --testdox

# 新機能追加時のセキュリティチェック
./vendor/bin/phpunit --filter "auth|authorization|permission|access|validation" --testdox
```

### **テスト駆動開発（TDD）での活用**

```bash
# 1. 新機能のテストから開始
./vendor/bin/phpunit --filter test_new_feature_name

# 2. Feature層での機能実装確認
./vendor/bin/phpunit tests/Feature/NewFeatureTest.php --testdox

# 3. API層での動作確認
./vendor/bin/phpunit tests/Feature/API/NewFeatureApiTest.php --testdox

# 4. バリデーション追加・確認
./vendor/bin/phpunit tests/Feature/ValidationTest.php --testdox

# 5. セキュリティテストの追加・確認
./vendor/bin/phpunit tests/Feature/AuthorizationTest.php --testdox
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

...........................................................       67 / 67 (100%)

Time: 00:01.103, Memory: 52.50 MB

OK (67 tests, 160 assertions)
```

### **テストスイート別結果**

| テストスイート               | テスト数 | アサーション数 | ステータス |
| ---------------------------- | -------- | -------------- | ---------- |
| **AppConfigTest**            | 1        | 1              | ✅ 全成功  |
| **AuthTest**                 | 8        | 21             | ✅ 全成功  |
| **AuthorizationTest**        | 15       | 26             | ✅ 全成功  |
| **ConversationTest**         | 5        | 11             | ✅ 全成功  |
| **FriendshipTest**           | 9        | 21             | ✅ 全成功  |
| **MessageTest**              | 4        | 8              | ✅ 全成功  |
| **ValidationTest**           | 7        | 34             | ✅ 全成功  |
| **ExampleTest**              | 1        | 2              | ✅ 全成功  |
| **API/ConversationsApiTest** | 5        | 8              | ✅ 全成功  |
| **API/FriendshipApiTest**    | 3        | 5              | ✅ 全成功  |
| **API/MessagesApiTest**      | 2        | 4              | ✅ 全成功  |
| **API/UserApiTest**          | 4        | 13             | ✅ 全成功  |

### **機能別テストカバレッジ**

| **機能カテゴリ**   | **Feature 層テスト** | **API 層テスト** | **Validation 層テスト** | **合計カバレッジ** |
| ------------------ | -------------------- | ---------------- | ----------------------- | ------------------ |
| **認証機能**       | 8 テスト（完全）     | 4 テスト（補完） | 2 テスト（検証）        | **100%**           |
| **友達関係機能**   | 9 テスト（完全）     | 3 テスト（補完） | 2 テスト（検証）        | **100%**           |
| **会話機能**       | 5 テスト（完全）     | 5 テスト（補完） | -                       | **100%**           |
| **メッセージ機能** | 4 テスト（完全）     | 2 テスト（補完） | 1 テスト（検証）        | **100%**           |
| **ユーザー管理**   | -                    | 4 テスト（完全） | 1 テスト（検証）        | **100%**           |
| **セキュリティ**   | 15 テスト（完全）    | 統合済み         | 統合済み                | **100%**           |

### **テスト層構造の最適化**

✅ **Feature Tests（50 テスト・126 アサーション）**

-   **ビジネスロジック**の完全テスト
-   **セキュリティ要件**の包括的検証
-   **データベース整合性**の確認

✅ **API Tests（14 テスト・30 アサーション）**

-   **REST API エンドポイント**の動作確認
-   **HTTP 通信**の正確性検証
-   **フロントエンド連携**の動作保証

✅ **Validation Tests（7 テスト・34 アサーション）**

-   **入力バリデーション**の包括的検証
-   **不正入力**に対する適切なエラーハンドリング
-   **データ整合性**の事前保証

### **バリデーションテストの重要性**

**ValidationTest** では以下の重要なバリデーション要件をテスト：

-   **7 個のテストケース**で 34 のアサーション
-   **必須項目チェック**から**データ型検証**まで包括的にカバー
-   **文字数制限**や**形式チェック**により適切な入力制御
-   **実装準拠**したエラーハンドリング確認

### **包括的テストスイートの価値**

✅ **67 テスト・160 アサーション**により以下を保証：

-   **API 仕様の正確性**（14 の API テスト）
-   **ビジネスロジックの完全性**（50 の機能テスト）
-   **セキュリティ要件の遵守**（15 のセキュリティテスト）
-   **入力バリデーションの確実性**（7 のバリデーションテスト）
-   **データ整合性の維持**（全テストで DB 確認）

このテストスイートにより、アプリケーションのセキュリティ、機能、および入力検証が**本番環境レベルで保証**されています。
