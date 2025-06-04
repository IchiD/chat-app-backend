# テストの実行方法

開発環境および本番環境では MySQL を利用していますが、テストでは既存のデータベース
を汚さないようにメモリ上の SQLite を使用します。

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

3. **テスト実行**
    ```bash
    ./vendor/bin/phpunit
    ```

# テスト結果の確認

テストが成功すると次のように表示されます。

```
PHPUnit ...
OK (5 tests, 9 assertions)
```

失敗した場合はエラーメッセージが表示されます。内容を確認して修正を行ってください。

# 詳細なテスト実行オプション

## 1. テスト内容を詳しく確認

```bash
# TestDox形式でテスト内容を分かりやすく表示
./vendor/bin/phpunit --testdox
```

**出力例:**

```
App Config (Tests\Feature\AppConfig)
 ✔ Get public config returns expected structure

User Model (Tests\Unit\UserModel)
 ✔ Friend id is generated and unique
```

## 2. 特定のテストスイートのみ実行

```bash
# Unitテストのみ実行
./vendor/bin/phpunit tests/Unit --testdox

# Featureテストのみ実行
./vendor/bin/phpunit tests/Feature --testdox
```

## 3. 特定のテストファイルのみ実行

```bash
# 特定のテストファイルを実行
./vendor/bin/phpunit tests/Unit/UserModelTest.php

# 特定のテストメソッドのみ実行
./vendor/bin/phpunit --filter test_friend_id_is_generated_and_unique
```

## 4. テストの一覧表示

```bash
# 利用可能なテストの一覧を表示
./vendor/bin/phpunit --list-tests

# 利用可能なテストスイートの一覧を表示
./vendor/bin/phpunit --list-suites
```

## 5. デバッグ情報付きでテスト実行

```bash
# デバッグ情報を含めてテスト実行
./vendor/bin/phpunit --debug
```

## 6. 失敗時に詳細情報を表示

```bash
# 警告やエラーの詳細を表示
./vendor/bin/phpunit --display-warnings --display-errors

# 非推奨機能の使用箇所を表示
./vendor/bin/phpunit --display-deprecations
```

## 7. コードカバレッジレポート（xdebug が必要）

```bash
# HTMLでカバレッジレポートを生成
./vendor/bin/phpunit --coverage-html coverage

# テキスト形式でカバレッジを表示
./vendor/bin/phpunit --coverage-text
```

## 8. その他の有用なオプション

```bash
# エラー発生時に即座に停止
./vendor/bin/phpunit --stop-on-error

# 失敗発生時に即座に停止
./vendor/bin/phpunit --stop-on-failure

# テスト結果をJUnit XML形式で出力
./vendor/bin/phpunit --log-junit results.xml
```

これらのオプションを組み合わせることで、効率的にテストの開発・デバッグを行うことができます。
