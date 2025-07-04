# Laravel Sail 起動ガイド

## 標準的な起動方法

`.env.example`の問題は解決済みです。通常の Laravel Sail コマンドで問題なく起動できます。

### 1. 基本的な起動・停止コマンド

```bash
# Sailを起動
./vendor/bin/sail up -d

# Sailを停止
./vendor/bin/sail down

# 完全にクリーンアップして再起動
./vendor/bin/sail down -v
./vendor/bin/sail up -d
```

### 2. エイリアスの設定（推奨）

`.zshrc`または`.bashrc`に以下を追加：

```bash
# Laravel Sail エイリアス
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
alias sail-up='sail up -d'
alias sail-down='sail down'
alias sail-clean='sail down -v && sail up -d && sleep 30 && sail artisan optimize:clear && sail artisan config:cache'
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
./vendor/bin/sail up -d

# 4. 必要に応じてマイグレーション実行
./vendor/bin/sail artisan migrate
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
sail-up

# 停止
sail-down

# 再起動（問題がある場合）
sail-clean
```

### 6. 環境変数の確認

起動前に必ず以下を確認：

-   `.env`ファイルが存在する
-   `APP_KEY`が設定されている
-   `DB_*`の設定が正しい

これらの設定により、Sail は標準的なコマンドで問題なく起動します。
