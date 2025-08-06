# 本番環境マイグレーション手順

## 問題の内容
本番環境では旧アーキテクチャの`conversation_id`カラムが使われているが、コードは新アーキテクチャの`chat_room_id`を期待している。

## 推奨手順: データベースリセット + 新マイグレーション

既存データが不要なため、データベースを完全にリセットして新アーキテクチャで再構築します。

### Railwayでの実行手順

1. **Railwayダッシュボードにアクセス**
   - プロジェクトを選択
   - バックエンドサービスを選択

2. **デプロイ実行**
   - "Deploy"タブを選択
   - "Deploy Now"をクリック

3. **デプロイ完了後、ログで確認**
   ```bash
   # 以下のコマンドが自動実行されることを確認
   php artisan migrate:fresh --seed
   ```

### 手動実行が必要な場合

Railway Connect を使用してSSH接続し、以下を実行：

```bash
# データベースを完全リセットし、新アーキテクチャでマイグレーション実行
php artisan migrate:fresh --seed
```

### 実行後の確認

```bash
# テーブル構造の確認
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema; 
echo 'messages table columns: ' . implode(', ', Schema::getColumnListing('messages')) . '\n';
echo 'chat_rooms table exists: ' . (Schema::hasTable('chat_rooms') ? 'YES' : 'NO') . '\n';
"
```

### 期待される結果

- `messages`テーブルに`chat_room_id`カラムが存在
- `conversations`テーブルが存在しない（旧アーキテクチャ）
- 新アーキテクチャでのチャット機能が正常動作

### メリット

- スキーマの完全な整合性が保証される
- 開発環境と本番環境が完全に同期される
- 複雑なデータ移行作業が不要
- 旧データの不整合による問題を回避