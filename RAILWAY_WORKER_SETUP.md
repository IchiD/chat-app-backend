# Railway キューワーカー設定手順

## 🚨 現在の問題
メッセージ送信時に通知がキューに追加されているが、ワーカーが起動していないため処理されていない。

## ✅ 解決方法

### 方法1: 既存サービスでワーカーを起動（簡易版）

Railway SSHで接続して手動起動：

```bash
# Railway SSH接続
railway ssh

# キューにジョブがあるか確認
php artisan tinker
>>> DB::table('jobs')->count();
>>> exit

# バックグラウンドでワーカー起動
nohup php artisan queue:work database --verbose --tries=3 --timeout=90 --sleep=3 > /tmp/queue.log 2>&1 &

# ログ監視
tail -f /tmp/queue.log
```

### 方法2: 別サービスとしてワーカーを起動（推奨）

#### 手順：

1. **Railwayダッシュボードにアクセス**
   - https://railway.app でログイン
   - プロジェクトを選択

2. **新しいサービスを追加**
   - 「+ New」ボタンをクリック
   - 「GitHub Repo」を選択
   - 同じリポジトリ（backend）を選択

3. **サービス名を設定**
   - Service Name: `backend-worker`

4. **環境変数を設定**
   - 既存のバックエンドサービスから環境変数をコピー
   - または「Reference Variable」で既存サービスの変数を参照
   - 特に以下が必要：
     ```
     DATABASE_URL
     QUEUE_CONNECTION=database
     VAPID_PUBLIC_KEY
     VAPID_PRIVATE_KEY
     MAIL_*（メール設定）
     ```

5. **Start Commandを設定**
   - Settings → Deploy → Start Command
   ```bash
   php artisan queue:work database --verbose --tries=3 --timeout=90 --sleep=3 --max-jobs=1000 --max-time=3600
   ```

6. **デプロイ**
   - 「Deploy」ボタンをクリック

### 方法3: Procfileを使用（Railway Pro版）

Railway Proプランの場合、Procfileの複数プロセスがサポートされます：

```procfile
web: php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-jobs=1000 --max-time=3600
```

## 📊 動作確認

### 1. ワーカーが起動しているか確認

```bash
railway ssh

# プロセス確認
ps aux | grep queue:work

# ジョブ数確認
php artisan tinker
>>> DB::table('jobs')->count();
>>> DB::table('failed_jobs')->count();
>>> exit
```

### 2. テスト通知を送信

```bash
# プッシュ通知テスト
php artisan push:test

# ログ確認
tail -f storage/logs/laravel.log
```

### 3. Railway Logsで確認

```bash
# CLIから
railway logs --service=backend-worker

# またはダッシュボードの「Logs」タブで確認
```

## 🔧 トラブルシューティング

### ワーカーが停止する場合

```bash
# supervisorを使用（永続化）
apt-get update && apt-get install -y supervisor

# supervisor設定作成
cat > /etc/supervisor/conf.d/laravel-worker.conf << EOF
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/app/storage/logs/worker.log
stopwaitsecs=3600
EOF

# supervisor起動
supervisorctl reread
supervisorctl update
supervisorctl start laravel-worker:*
```

### メモリ不足の場合

Start Commandを調整：

```bash
# メモリ節約版
php artisan queue:work database --sleep=5 --tries=2 --timeout=60 --max-jobs=100 --memory=64

# 処理を分散
php artisan queue:work database --queue=notifications --sleep=3
php artisan queue:work database --queue=emails --sleep=3
```

## ✅ 成功の確認ポイント

1. **ログに処理メッセージが表示される**
   ```
   [2025-08-06 09:35:00] Processing: App\Notifications\PushNotification
   [2025-08-06 09:35:01] Processed:  App\Notifications\PushNotification
   ```

2. **ジョブテーブルが空になる**
   ```sql
   SELECT COUNT(*) FROM jobs; -- 0になるはず
   ```

3. **通知が実際に届く**
   - プッシュ通知がブラウザに表示
   - メールが送信される

## 📝 注意事項

- ワーカーサービスは常時起動が必要
- Railway無料プランの場合、実行時間制限あり
- 本番環境では複数ワーカーの起動を検討
- ログファイルのサイズに注意（定期的にローテーション）

これで、Railwayでキューワーカーが正常に動作し、プッシュ通知とメール通知が処理されるようになります！