# 古いアーキテクチャのマイグレーションファイル バックアップ

このディレクトリには、新しいアーキテクチャ（chat_rooms + groups）への移行により不要になった古いマイグレーションファイルがバックアップされています。

## バックアップ日時

2025-06-10

## 削除理由

新しいアーキテクチャでは以下の変更が行われました：

-   `conversations`テーブル → `chat_rooms`テーブルに置き換え
-   `participants`テーブル → 新しい`chat_room_participants`の実装（conversation_id → chat_room_id）
-   グループ機能の独立したテーブル設計

## バックアップファイル一覧

### Conversations テーブル関連

-   `2025_05_16_114847_create_conversations_table.php` - 基本の conversations テーブル作成
-   `2025_06_03_100001_add_soft_delete_columns_to_conversations_table.php` - ソフトデリート機能追加
-   `2025_06_03_100009_add_room_token_to_conversations_table.php` - ルームトークン機能追加
-   `2025_06_03_100010_add_support_type_to_conversations_table.php` - サポートタイプ機能追加
-   `2025_06_08_202615_add_group_fields_to_conversations_table.php` - グループフィールド追加
-   `2025_06_08_225924_add_group_conversation_id_to_conversations_table.php` - グループチャット ID 追加
-   `2025_06_08_233040_add_group_member_to_conversations_type_enum.php` - グループメンバータイプ追加
-   `2025_06_09_160621_add_chat_styles_to_conversations_table.php` - チャットスタイル追加
-   `2025_06_09_165724_remove_chat_style_column_from_conversations_table.php` - チャットスタイル削除

### Participants テーブル関連

-   `2025_05_16_114916_create_participants_table.php` - 古い participants テーブル（conversation_id 参照）

### 管理者機能関連

-   `2025_05_29_173305_create_admin_conversation_reads_table.php` - 管理者のチャット読み取り機能

### その他

-   `2025_06_09_165406_optimize_messages_table_structure.php` - メッセージテーブル最適化
-   `2025_06_09_171600_update_existing_tables_for_phase2.php` - フェーズ 2 への移行処理
-   `2025_06_10_111457_update_admin_conversation_reads_for_chat_rooms.php` - チャットルーム対応の管理者読み取り機能

## 復元方法

必要に応じて、これらのファイルを`backend/database/migrations/`ディレクトリにコピーしてください。ただし、新しいアーキテクチャとの競合に注意してください。
