#!/bin/bash

echo "🚀 Railway Application Starting..."

# 環境変数の確認
echo "🔍 環境変数確認中..."
echo "DB_CONNECTION: ${DB_CONNECTION:-未設定}"
echo "DB_HOST: ${DB_HOST:-未設定}"
echo "MYSQLHOST: ${MYSQLHOST:-未設定}"

# データベース接続の確認（複数パターンに対応）
echo "🔌 データベース接続確認中..."

# Laravel標準の環境変数を使用
if [ -n "$DB_HOST" ] && [ -n "$DB_DATABASE" ]; then
    echo "📋 Laravel標準環境変数を使用"
    php -r "
try {
    \$pdo = new PDO('mysql:host=' . getenv('DB_HOST') . ':' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    echo '✅ データベース接続成功（Laravel標準）\n';
} catch (Exception \$e) {
    echo '❌ データベース接続失敗（Laravel標準）: ' . \$e->getMessage() . '\n';
    exit(1);
}
"
# Railway固有の環境変数を使用
elif [ -n "$MYSQLHOST" ] && [ -n "$MYSQLDATABASE" ]; then
    echo "📋 Railway環境変数を使用"
    php -r "
try {
    \$pdo = new PDO('mysql:host=' . getenv('MYSQLHOST') . ':' . (getenv('MYSQLPORT') ?: '3306') . ';dbname=' . getenv('MYSQLDATABASE'), getenv('MYSQLUSER'), getenv('MYSQLPASSWORD'));
    echo '✅ データベース接続成功（Railway）\n';
} catch (Exception \$e) {
    echo '❌ データベース接続失敗（Railway）: ' . \$e->getMessage() . '\n';
    exit(1);
}
"
else
    echo "❌ データベース環境変数が見つかりません"
    echo "利用可能な環境変数："
    env | grep -E "(DB_|MYSQL)" | head -10
    exit(1);
fi

# マイグレーション実行
echo "🛠️  マイグレーション実行中..."
echo "現在のディレクトリ: $(pwd)"
echo "artisanファイル確認: $(ls -la artisan)"

if php artisan migrate --force --no-interaction; then
    echo "✅ マイグレーション完了"
    echo "📊 マイグレーション状況:"
    php artisan migrate:status --no-interaction | head -10
else
    echo "❌ マイグレーション失敗 - 詳細な情報:"
    php artisan migrate:status --no-interaction || echo "マイグレーション状況確認も失敗"
    echo "⚠️  アプリケーションを継続起動します"
fi

# アプリケーション起動
echo "🌐 アプリケーション起動中..."
echo "PORT: ${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000} 