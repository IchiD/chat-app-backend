# 本番環境マイグレーション手順

## 問題の内容
本番環境では旧アーキテクチャの`conversation_id`カラムが使われているが、コードは新アーキテクチャの`chat_room_id`を期待している。

## 必要なマイグレーション

### 1. messagesテーブルの更新

```sql
-- chat_room_idカラムを追加
ALTER TABLE messages ADD COLUMN chat_room_id BIGINT UNSIGNED NULL AFTER id;

-- conversation_idからchat_room_idにデータをコピー
UPDATE messages SET chat_room_id = conversation_id WHERE conversation_id IS NOT NULL;

-- chat_room_idカラムをNOT NULLに変更
ALTER TABLE messages MODIFY COLUMN chat_room_id BIGINT UNSIGNED NOT NULL;

-- 外部キー制約を追加
ALTER TABLE messages ADD FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE;

-- 旧conversation_idカラムを削除
ALTER TABLE messages DROP COLUMN conversation_id;
```

### 2. インデックスの確認・追加

```sql
-- chat_room_idにインデックスが存在することを確認
SHOW INDEX FROM messages WHERE Column_name = 'chat_room_id';

-- 必要に応じてインデックスを追加
ALTER TABLE messages ADD INDEX idx_messages_chat_room_id (chat_room_id);
```

### 3. Railwayでの実行方法

1. Railwayのダッシュボードにアクセス
2. データベースサービスを選択
3. "Connect"タブからデータベースに接続
4. 上記SQLを順番に実行

### 4. 実行前の注意点

- **必ずバックアップを取る**
- メンテナンスモードに入る
- 実行前にconversation_idとchat_room_idの対応を確認

### 5. 実行後の確認

```sql
-- データが正しく移行されたことを確認
SELECT COUNT(*) FROM messages WHERE chat_room_id IS NULL;
-- 結果は0であること

-- サンプルデータを確認
SELECT id, chat_room_id, sender_id, text_content FROM messages LIMIT 5;
```

### 6. ロールバック手順（必要時）

```sql
-- chat_room_idからconversation_idに戻す
ALTER TABLE messages ADD COLUMN conversation_id BIGINT UNSIGNED NULL;
UPDATE messages SET conversation_id = chat_room_id;
ALTER TABLE messages DROP FOREIGN KEY messages_chat_room_id_foreign;
ALTER TABLE messages DROP COLUMN chat_room_id;
```