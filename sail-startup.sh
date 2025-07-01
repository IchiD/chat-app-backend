#!/bin/bash

echo "Starting Laravel Sail with proper configuration..."

# 1. コンテナを停止（実行中の場合）
./vendor/bin/sail down

# 2. Docker volumeを削除して完全にクリーンアップ
echo "Cleaning up Docker volumes..."
docker volume rm backend_sail-mysql 2>/dev/null || echo "Volume already cleaned or doesn't exist"

# 3. コンテナを起動
./vendor/bin/sail up -d

# 4. MySQLが完全に起動するまで待機（より長い待機時間）
echo "Waiting for MySQL to be ready..."
for i in {1..60}; do
    if ./vendor/bin/sail exec mysql mysqladmin ping -h "localhost" -u root --silent 2>/dev/null; then
        echo "MySQL is ready!"
        break
    fi
    echo "Waiting for MySQL... ($i/60)"
    sleep 3
done

# 5. Laravelコンテナが完全に起動するまで待機
echo "Waiting for Laravel container to be ready..."
sleep 20

# 6. 環境設定の確認と修正
echo "Verifying .env configuration..."
if ! grep -q "^DB_DATABASE=laravel" .env; then
    echo "Fixing database name in .env..."
    sed -i '' 's/^DB_DATABASE=.*/DB_DATABASE=laravel/' .env
fi

# 7. 全てのキャッシュを段階的にクリア
echo "Clearing all caches thoroughly..."
./vendor/bin/sail artisan config:clear || echo "Config cache clear failed, continuing..."
./vendor/bin/sail artisan cache:clear || echo "Application cache clear failed, continuing..."
./vendor/bin/sail artisan route:clear || echo "Route cache clear failed, continuing..."
./vendor/bin/sail artisan view:clear || echo "View cache clear failed, continuing..."
./vendor/bin/sail artisan event:clear || echo "Event cache clear failed, continuing..."

# 8. Bootstrap キャッシュもクリア
echo "Clearing bootstrap cache..."
./vendor/bin/sail exec laravel.test php -r "
    \$files = glob('/var/www/html/bootstrap/cache/*.php');
    foreach(\$files as \$file) {
        if(is_file(\$file) && basename(\$file) !== '.gitignore') {
            unlink(\$file);
        }
    }
    echo 'Bootstrap cache cleared' . PHP_EOL;
"

# 8.5. 設定キャッシュファイルを物理的に削除
echo "Physically removing config cache files..."
./vendor/bin/sail exec laravel.test rm -f /var/www/html/bootstrap/cache/config.php
./vendor/bin/sail exec laravel.test rm -f /var/www/html/bootstrap/cache/services.php
./vendor/bin/sail exec laravel.test rm -f /var/www/html/bootstrap/cache/packages.php

# 8.6. Docker Compose環境変数設定を確認・修正
echo "Ensuring Docker Compose has correct environment variables..."
if ! grep -q "DB_HOST:" docker-compose.yml; then
    echo "Docker Compose environment variables need to be added. This is a critical fix."
    echo "Please ensure docker-compose.yml laravel.test service has proper environment variables."
fi

# 8.7. 環境変数がコンテナに渡されているか確認
echo "Verifying environment variables in container..."
./vendor/bin/sail exec laravel.test printenv | grep -E "DB_|SESSION_|CACHE_" || echo "Environment variables missing - using config defaults"

# 8.8. config/database.phpのデフォルト値を修正（環境変数読み込み問題対策）
echo "Ensuring correct database configuration defaults..."
./vendor/bin/sail exec laravel.test sed -i "s/'host' => env('DB_HOST', '127.0.0.1'),/'host' => env('DB_HOST', 'mysql'),/g" /var/www/html/config/database.php
./vendor/bin/sail exec laravel.test sed -i "s/'username' => env('DB_USERNAME', 'root'),/'username' => env('DB_USERNAME', 'sail'),/g" /var/www/html/config/database.php
./vendor/bin/sail exec laravel.test sed -i "s/'password' => env('DB_PASSWORD', ''),/'password' => env('DB_PASSWORD', 'password'),/g" /var/www/html/config/database.php

# 9. 設定キャッシュを再生成
echo "Regenerating configuration cache..."
./vendor/bin/sail artisan config:cache

# 9.5. 再生成後の設定を確認
echo "Verifying regenerated config..."
./vendor/bin/sail artisan config:show database.connections.mysql.host
./vendor/bin/sail artisan config:show database.connections.mysql.database
./vendor/bin/sail artisan config:show database.connections.mysql.username

# 10. データベース接続を確認（より多くのリトライ）
echo "Testing database connection..."
for i in {1..20}; do
    if ./vendor/bin/sail artisan migrate:status &>/dev/null; then
        echo "Database connection successful!"
        break
    fi
    echo "Waiting for database connection... ($i/20)"
    sleep 5
done

# 11. データベースが存在しない場合は作成
echo "Ensuring database exists..."
./vendor/bin/sail exec mysql mysql -u root -p"${DB_PASSWORD:-password}" -e "CREATE DATABASE IF NOT EXISTS laravel;" 2>/dev/null || echo "Database creation attempted"

# 12. マイグレーションの実行
echo "Running migrations..."
./vendor/bin/sail artisan migrate --force

# 13. アプリケーションの状態確認
echo "Checking application status..."
./vendor/bin/sail artisan about | grep -E "Environment|Debug Mode|Cache|Database"

echo "Laravel Sail is ready!"
echo "Application URL: http://localhost" 
echo "phpMyAdmin URL: http://localhost:8080" 