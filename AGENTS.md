# AI エージェント事前指示書

## 🎯 プロジェクト概要

このプロジェクトは、既存のチャットアプリケーションに**有料 1 対多チャットサービス機能**を追加する開発プロジェクトです。

### 対象システム

- **メインリポジトリ**: https://github.com/IchiD/simple-chat-app
- **バックエンド**: https://github.com/IchiD/chat-app-backend (Laravel 10)
- **フロントエンド**: https://github.com/IchiD/chat-app-frontend (Nuxt 3 + Vue 3)

---

## 📋 開発ルールと制約

### 🚨 絶対遵守事項

1. **ブランチ保護**: `main` ブランチでの直接作業は**絶対禁止**
2. **段階的実装**: 指定されたフェーズ順序を厳密に遵守
3. **技術スタック固定**: 既存の技術バージョンを変更禁止
4. **UI/UX 変更禁止**: 明示的指示なしでのデザイン変更禁止
5. **参照ドキュメント必読**: `implementation_plan.md` を必ず参照
6. **環境制約の遵守**: PHP 環境なしの制約に応じた開発

### 🔄 開発フロー

```
Phase 1: データモデル設計 (並行開発可能)
    ↓
Phase 2: Stripe 決済機能 (Backend → Frontend)
    ↓
Phase 3: グループチャット機能 (Backend → Frontend)
    ↓
Phase 4: QR コード機能 (Backend → Frontend)
    ↓
Phase 5: API 連携ログイン (Backend → Frontend)
```

---

## 🚨 **重要: 環境制約事項**

### ❌ **利用不可な環境・技術**

1. **PHP 環境なし**
   - Laravel Sail (Docker) 起動不可
   - Artisan コマンド実行不可
   - マイグレーション実行不可
   - PHPUnit テスト実行不可
   - Tinker (Laravel REPL) 使用不可

### ✅ **利用可能な環境・技術**

1. **Node.js/JavaScript/TypeScript**

   - npm/yarn コマンド実行可能
   - Nuxt.js 開発サーバー起動可能
   - TypeScript ビルド実行可能
   - Jest/Vitest テスト実行可能

2. **一般的な開発ツール**
   - Git コマンド
   - ファイル編集・作成
   - コードレビュー・静的解析

---

## 🛠️ 技術要件

### Backend (Laravel 10)

- **認証方式**: 既存カスタム JWT (sessionStorage 使用)
- **データベース**: MySQL
- **決済**: Stripe API
- **開発方針**: ファイル作成・編集（動作確認は後日）

### Frontend (Nuxt 3)

- **フレームワーク**: Vue 3 + TypeScript
- **状態管理**: Pinia
- **スタイリング**: Tailwind CSS
- **ルーティング**: Nuxt ファイルベースルーティング
- **テスト**: Vitest（実行可能）

---

## 📂 ブランチ戦略

### 開発ブランチ

- **Main**: `feature/group-chat-service`
- **Backend**: `feature/group-chat-api`
- **Frontend**: `feature/group-chat-ui`

### フェーズブランチ (各リポジトリで作成)

- Phase 1: `feat/phase1-*`
- Phase 2: `feat/phase2-*`
- Phase 3: `feat/phase3-*`
- Phase 4: `feat/phase4-*`
- Phase 5: `feat/phase5-*`

---

## 🎯 各フェーズの重要ポイント

### Phase 1: データモデル設計

- **並行開発可**: Backend と Frontend で同時実行可能
- **Backend**: Laravel マイグレーション + Eloquent モデル（ファイル作成のみ）
- **Frontend**: User インターフェース拡張 (subscription 関連)

### Phase 2: Stripe 決済機能

- **Backend 優先**: API 実装完了後に Frontend 開始
- **重要**: Webhook 署名検証、エラーハンドリング（実装のみ）
- **Frontend**: 価格表示ページ、決済フロー UI

### Phase 3: グループチャット機能

- **Backend**: RESTful API（実装のみ）
- **Frontend**: 管理者ダッシュボード、チャット UI
- **重要**: 認可制御、メンバー管理

### Phase 4: QR コード機能

- **Backend**: QR 生成、トークン管理、参加ロジック（実装のみ）
- **Frontend**: QR 表示、参加ランディングページ
- **重要**: セキュリティ、有効期限管理

### Phase 5: API 連携ログイン

- **Backend**: 外部システム向け認証 API（実装のみ）
- **Frontend**: 連携フロー UI
- **重要**: OAuth2 風実装、レート制限

---

## 📝 実装時の注意点

### コード品質

- **型安全性**: TypeScript の厳密な型チェック
- **静的解析**: ESLint/PHPStan による静的チェック
- **エラーハンドリング**: 適切な例外処理とログ出力
- **セキュリティ**: 認証・認可・入力検証の徹底

### パフォーマンス

- **データベース**: 適切なインデックス設定
- **API**: ページネーション実装
- **フロントエンド**: レスポンシブデザイン対応

### ドキュメント

- **API 仕様書**: 変更時は必ず更新
- **README**: セットアップ手順の明記
- **コメント**: 複雑な処理に対する適切な説明

---

## 🔍 テストと検証

### ✅ **実行可能なテスト**

#### **Frontend**

1. **TypeScript ビルド**: `npm run build` での型チェック
2. **開発サーバー**: `npm run dev` での動作確認
3. **単体テスト**: Vitest での UI コンポーネントテスト
4. **レスポンシブ**: ブラウザでの UI/UX 確認

#### **Backend**

1. **静的解析**: ファイル作成・編集時のコードレビュー
2. **構文チェック**: PHP ファイルの構文エラー確認
3. **設定ファイル**: 環境設定の妥当性確認

### ❌ **実行不可なテスト（後日別途実行予定）**

- **PHP 環境依存テスト**
  - Laravel マイグレーション実行
  - PHPUnit テスト実行
  - データベース接続テスト
  - API エンドポイントテスト
  - Stripe 決済テスト

### 検証環境

- **開発環境**: フロントエンドでの基本動作確認
- **統合テスト**: 実装完了後に別途環境で実行
- **モバイル**: スマートフォンでの UI/UX 確認

---

## 📞 質問・相談時のガイドライン

### 質問前の確認事項

1. `implementation_plan.md` の該当セクション確認
2. 環境制約 (`AI_AGENT_ENVIRONMENT_NOTES.md`) の確認
3. エラーログの詳細な内容収集（フロントエンドのみ）
4. 現在のブランチと作業状況の明確化

### 質問時に含める情報

- **フェーズ番号**: 現在作業中のフェーズ
- **リポジトリ**: Backend / Frontend
- **環境**: 利用可能な環境（Node.js 等）のみ記載
- **エラー内容**: TypeScript/JavaScript のエラーメッセージ
- **期待動作**: 本来期待していた結果

---

## 🏁 完了基準

### 各フェーズ完了の判定基準

#### **Frontend**

- [ ] 指定機能の実装完了
- [ ] TypeScript ビルドエラー 0 件
- [ ] 開発サーバー正常起動
- [ ] UI コンポーネント動作確認
- [ ] ドキュメントの更新完了

#### **Backend**

- [ ] ファイル作成・編集完了
- [ ] 静的解析エラー 0 件
- [ ] コードレビュー基準のクリア
- [ ] 設定ファイル妥当性確認
- [ ] ドキュメントの更新完了

### 最終完了基準

- [ ] フロントエンド統合テスト成功
- [ ] 静的解析・型チェック完了
- [ ] UI/UX 確認完了
- [ ] ドキュメント整備完了
- [ ] **※ バックエンド動作確認は環境準備後に実施**

---

## 🎓 参考リソース

### 重要ドキュメント

- `implementation_plan.md`: 詳細技術仕様
- `data_models.md`: データモデル定義
- `technologystack.md`: 技術スタック詳細
- `setup_guide.md`: 環境構築手順
- `AI_AGENT_ENVIRONMENT_NOTES.md`: 環境制約ガイド

### 外部リソース

- [Laravel 10 Documentation](https://laravel.com/docs/10.x)
- [Nuxt 3 Documentation](https://nuxt.com/docs)
- [Stripe API Documentation](https://stripe.com/docs/api)
- [Vue 3 Composition API](https://vuejs.org/guide/extras/composition-api-faq.html)

---

_このドキュメントは AI エージェントが効率的かつ安全に開発を進めるための重要な指針です。環境制約を理解した上で、実行可能な範囲での開発を進めてください。_
