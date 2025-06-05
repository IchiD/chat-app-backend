#!/bin/bash

echo "🚀 Railway Application Starting..."

# データベース接続の確認
echo "🔌 データベース接続確認中..."
php -r "
try {
    \$pdo = new PDO('mysql:host=' . getenv('MYSQLHOST') . ':' . getenv('MYSQLPORT') . ';dbname=' . getenv('MYSQLDATABASE'), getenv('MYSQLUSER'), getenv('MYSQLPASSWORD'));
    echo '✅ データベース接続成功\n';
} catch (Exception \$e) {
    echo '❌ データベース接続失敗: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

# マイグレーション実行
echo "🛠️  マイグレーション実行中..."
if php artisan migrate --force; then
    echo "✅ マイグレーション完了"
else
    echo "❌ マイグレーション失敗 - アプリケーションを継続起動"
fi

# アプリケーション起動
echo "🌐 アプリケーション起動中..."
exec php artisan serve --host=0.0.0.0 --port=$PORT 