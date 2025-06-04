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

-   **FriendshipTest.php** - 友達機能の API 全体
-   **AppConfigTest.php** - アプリ設定 API

### **Unit Tests（単体テスト）** - `tests/Unit/`

個々のモデルやクラスの機能を独立してテスト

-   **UserModelTest.php** - User モデルの個別機能
-   **ConversationModelTest.php** - Conversation モデルの個別機能

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
