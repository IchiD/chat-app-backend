# Laravel Sail 起動ガイド

## 問題の永続的解決

Sail 起動時のエラーを防ぐための設定が完了しました。

### 1. 自動起動スクリプトの使用

```bash
# Sailを起動する際は以下のコマンドを使用
./sail-startup.sh
```

このスクリプトは以下を自動的に実行します：

-   MySQL が完全に起動するまで待機
-   すべてのキャッシュをクリア
-   設定を再読み込み
-   マイグレーションを実行

### 2. エイリアスの設定（推奨）

`.zshrc`または`.bashrc`に以下を追加：

```bash
# Laravel Sail エイリアス
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
alias sail-start='cd /Users/ichikawadaishi/Desktop/chat-app_nuxt/backend && ./sail-startup.sh'
alias sail-clean='sail down && sail up -d && sleep 30 && sail artisan optimize:clear && sail artisan config:cache'
```

設定後、ターミナルを再起動するか以下を実行：

```bash
source ~/.zshrc
```

### 3. Docker Compose の改善点

`docker-compose.yml`に以下の改善を実装済み：

-   MySQL のヘルスチェックを強化
-   Laravel コンテナは MySQL が健全になるまで待機
-   ヘルスチェックの間隔とタイムアウトを調整

### 4. トラブルシューティング

#### エラーが発生した場合の手動対処

```bash
# 1. コンテナを完全に停止
./vendor/bin/sail down -v

# 2. Dockerのキャッシュをクリア
docker system prune -a

# 3. 再起動
./sail-startup.sh
```

#### よくある問題と解決策

1. **APP_KEY エラー**

    ```bash
    ./vendor/bin/sail artisan key:generate
    ./vendor/bin/sail artisan config:cache
    ```

2. **MySQL 接続エラー**

    ```bash
    # MySQLが起動しているか確認
    ./vendor/bin/sail ps
    # ヘルスチェック
    ./vendor/bin/sail exec mysql mysqladmin ping -h "localhost" -u root
    ```

3. **キャッシュの問題**
    ```bash
    ./vendor/bin/sail artisan optimize:clear
    ```

### 5. 日常的な使用方法

```bash
# 開始（エイリアス設定済みの場合）
sail-start

# 停止
sail down

# 再起動（問題がある場合）
sail-clean
```

### 6. 環境変数の確認

起動前に必ず以下を確認：

-   `.env`ファイルが存在する
-   `APP_KEY`が設定されている
-   `DB_*`の設定が正しい

これらの設定により、Sail 起動時のエラーが大幅に減少します。
