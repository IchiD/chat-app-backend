#!/bin/bash

echo "🚀 Railway Application Starting..."

# 環境変数の確認
echo "�� 環境変数確認中..."
echo "DATABASE_URL: ${DATABASE_URL:-未設定}"
echo "DB_CONNECTION: ${DB_CONNECTION:-未設定}"
echo "DB_HOST: ${DB_HOST:-未設定}"
echo "PORT: ${PORT:-8000}"

# データベース接続が必要なマイグレーションを試行（失敗してもアプリ起動を継続）
echo "🛠️  マイグレーション試行中..."
echo "現在のディレクトリ: $(pwd)"

# マイグレーション実行を試行（バックグラウンドで継続実行も可能）
php artisan migrate --force --no-interaction 2>&1 | head -20 || {
    echo "⚠️  マイグレーション失敗 - アプリケーションを起動します"
    echo "🔧 手動でマイグレーションが必要な場合があります"
}

echo "📊 マイグレーション状況確認:"
php artisan migrate:status --no-interaction 2>&1 | head -10 || echo "状況確認も失敗"

# アプリケーション起動（マイグレーション失敗に関係なく起動）
echo "🌐 アプリケーション起動中..."
echo "起動ポート: ${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000} 