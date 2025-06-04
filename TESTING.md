# テストの実行方法

開発環境および本番環境では MySQL を利用していますが、テストでは既存のデータベース
を汚さないようにメモリ上の SQLite を使用します。

1. **依存パッケージのインストール**
   ```bash
   composer install
   ```

2. **環境設定**
   テストではメモリ上のSQLiteデータベースを使用します。`phpunit.xml` に設定済みのため、追加設定は不要です。必要に応じて `.env` ファイルを作成し `APP_KEY` を生成してください。
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
