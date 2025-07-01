# Docker再起動時の確認手順
1. .envファイルの存在確認: ls -la .env
2. APP_KEYの設定確認: grep APP_KEY .env
3. 必要に応じて: cp .env.example .env && php artisan key:generate
