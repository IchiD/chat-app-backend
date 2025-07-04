# 🔧 LumoChat Backend

**Laravel 11 + PHP 8.2 + Stripe API** で構築された高機能チャットアプリケーションのバックエンド

---

## 🎯 概要

LumoChat のバックエンドは、RESTful API とリアルタイム通信機能を提供する強力な Laravel アプリケーションです。

### 🌟 主な特徴

-   **🚀 高性能**: Laravel 11 の最新機能を活用
-   **🔒 セキュア**: Sanctum 認証と CSRF 保護
-   **💳 決済統合**: Stripe API の完全統合
-   **📱 リアルタイム**: プッシュ通知と SSE
-   **🎛️ 管理機能**: 包括的な管理画面

---

## 🛠️ 技術スタック

### Core

-   **Framework**: Laravel 11.0+
-   **Language**: PHP 8.2+
-   **Database**: MySQL 8.0+ / SQLite (開発)
-   **Cache**: Redis (本番) / File (開発)
-   **Queue**: Database Driver

### 主要パッケージ

-   **認証**: Laravel Sanctum
-   **決済**: Stripe PHP SDK
-   **通知**: Laravel Notifications + Web Push
-   **バリデーション**: Laravel Validation
-   **テスト**: PHPUnit + Laravel Testing

### インフラ

-   **本番環境**: Railway
-   **開発環境**: Laravel Sail (Docker)
-   **メール**: Gmail SMTP
-   **モニタリング**: Laravel Telescope

---

## 📋 主要機能

### 💬 チャット機能

| 機能                 | 説明                 | API エンドポイント |
| -------------------- | -------------------- | ------------------ |
| **1 対 1 チャット**  | 個人間メッセージング | `/api/chat`        |
| **グループチャット** | 1 対多数チャット     | `/api/groups`      |
| **サポートチャット** | 管理者サポート       | `/api/support`     |
| **未読管理**         | 未読メッセージ追跡   | `/api/unread`      |

### 🔐 認証システム

| 機能                   | 説明               | API エンドポイント    |
| ---------------------- | ------------------ | --------------------- |
| **通常登録**           | メールアドレス登録 | `/api/register`       |
| **Google 認証**        | OAuth2 認証        | `/api/auth/google`    |
| **パスワードリセット** | メールリセット     | `/api/password/reset` |
| **トークン管理**       | Sanctum トークン   | `/api/tokens`         |

### 💳 決済・課金

| 機能                   | 説明             | API エンドポイント    |
| ---------------------- | ---------------- | --------------------- |
| **サブスクリプション** | 月額課金管理     | `/api/subscriptions`  |
| **決済処理**           | Stripe 決済      | `/api/payments`       |
| **プラン管理**         | Standard/Premium | `/api/plans`          |
| **Webhook**            | Stripe イベント  | `/api/stripe/webhook` |

### 👑 管理機能

| 機能             | 説明               | Web エンドポイント |
| ---------------- | ------------------ | ------------------ |
| **ユーザー管理** | ユーザー一覧・編集 | `/admin/users`     |
| **決済管理**     | 決済履歴・分析     | `/admin/billing`   |
| **サポート**     | サポートチャット   | `/admin/support`   |
| **グループ管理** | グループ監視       | `/admin/groups`    |

---

## 🚀 セットアップ

### 前提条件

-   PHP 8.2+ (Composer 2.0+)
-   MySQL 8.0+ / SQLite
-   Node.js 18.0+ (フロントエンド用)
-   Docker (Laravel Sail 使用時)

### 1. 基本セットアップ

```bash
# 1. リポジトリクローン
git clone https://github.com/your-username/chat-app_nuxt.git
cd chat-app_nuxt/backend

# 2. 依存関係インストール
composer install

# 3. 環境変数設定
cp .env.example .env

# 4. アプリケーションキー生成
php artisan key:generate

# 5. データベースマイグレーション
php artisan migrate

# 6. シーダー実行
php artisan db:seed

# 7. サーバー起動
php artisan serve
```

### 2. Laravel Sail 使用時

```bash
# 1. Sail起動
./vendor/bin/sail up -d

# 2. マイグレーション実行
./vendor/bin/sail artisan migrate

# 3. シーダー実行
./vendor/bin/sail artisan db:seed

# 4. キューワーカー起動
./vendor/bin/sail artisan queue:work
```

### 3. 環境変数設定

`.env`ファイルで以下を設定：

```env
# 基本設定
APP_NAME=LumoChat
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# データベース設定
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# Stripe設定
STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
STRIPE_PUBLISHABLE_KEY=pk_test_xxxxx
STRIPE_PRICE_STANDARD=price_xxxxx
STRIPE_PRICE_PREMIUM=price_xxxxx

# メール設定
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls

# プッシュ通知設定
VAPID_SUBJECT=mailto:admin@example.com
VAPID_PUBLIC_KEY=your-vapid-public-key
VAPID_PRIVATE_KEY=your-vapid-private-key

# フロントエンド設定
FRONTEND_URL=http://localhost:3000
FRONTEND_URL_PRODUCTION=https://your-app.vercel.app
```

---

## 🏗️ アーキテクチャ

### 📁 プロジェクト構造

```
backend/
├── 📁 app/
│   ├── Console/Commands/     # Artisanコマンド
│   ├── Http/Controllers/     # コントローラー
│   │   ├── API/             # API用コントローラー
│   │   ├── Admin/           # 管理画面用コントローラー
│   │   └── Auth/            # 認証用コントローラー
│   ├── Models/              # Eloquentモデル
│   ├── Services/            # ビジネスロジック
│   ├── Repositories/        # データアクセス層
│   └── Notifications/       # 通知クラス
├── 📁 database/
│   ├── migrations/          # マイグレーション
│   ├── seeders/            # シーダー
│   └── factories/          # ファクトリー
├── 📁 routes/
│   ├── api.php             # API ルート
│   ├── web.php             # Web ルート
│   └── console.php         # コンソールルート
├── 📁 resources/
│   ├── views/              # Bladeテンプレート
│   └── lang/               # 多言語対応
├── 📁 tests/
│   ├── Feature/            # 機能テスト
│   └── Unit/               # 単体テスト
└── 📁 config/              # 設定ファイル
```

### 🔄 データフロー

```mermaid
graph TD
    A[Frontend] --> B[API Routes]
    B --> C[Controllers]
    C --> D[Services]
    D --> E[Repositories]
    E --> F[Models]
    F --> G[Database]

    C --> H[Notifications]
    H --> I[Queue Jobs]
    I --> J[Push Notifications]

    K[Stripe Webhook] --> L[Webhook Controller]
    L --> M[Payment Service]
    M --> N[Database Update]
```

---

## 🔌 API 仕様

### 認証エンドポイント

```php
// 基本認証
POST   /api/register           // ユーザー登録
POST   /api/login              // ログイン
POST   /api/logout             // ログアウト
POST   /api/refresh            // トークンリフレッシュ

// Google認証
GET    /api/auth/google         // Google認証開始
GET    /api/auth/google/callback // Google認証コールバック

// パスワードリセット
POST   /api/password/email      // リセットメール送信
POST   /api/password/reset      // パスワード更新
```

### チャットエンドポイント

```php
// 1対1チャット
GET    /api/conversations       // チャットルーム一覧
GET    /api/conversations/{id}  // 特定チャットルーム
POST   /api/conversations       // チャットルーム作成
POST   /api/messages            // メッセージ送信

// グループチャット
GET    /api/groups              // グループ一覧
POST   /api/groups              // グループ作成
PUT    /api/groups/{id}         // グループ更新
DELETE /api/groups/{id}         // グループ削除
POST   /api/groups/{id}/join    // グループ参加
```

### 決済エンドポイント

```php
// サブスクリプション
GET    /api/subscriptions       // 現在のサブスクリプション
POST   /api/subscriptions       // サブスクリプション作成
PUT    /api/subscriptions/{id}  // プラン変更
DELETE /api/subscriptions/{id}  // サブスクリプション取消

// 決済
POST   /api/payments/checkout   // 決済セッション作成
GET    /api/payments/history    // 決済履歴
POST   /api/stripe/webhook      // Stripe Webhook
```

---

## 📚 主要機能詳細

### 💬 サポートチャット機能

#### 概要

ユーザーと管理者間のサポートチャット機能が実装されています。

#### 特徴

-   **自動作成**: ユーザー登録時に自動的にサポートチャットルーム作成
-   **条件表示**: メッセージがある場合のみユーザー側に表示
-   **管理者アクセス**: 全てのサポートチャットにアクセス可能

#### 実装詳細

```php
// 自動作成ロジック
class AuthService extends BaseService
{
    public function register(array $data): User
    {
        $user = User::create($data);

        // サポートチャットルーム自動作成
        $this->createSupportChatRoom($user);

        return $user;
    }
}

// 表示条件
class ConversationsController extends Controller
{
    public function index(Request $request)
    {
        // メッセージがある場合のみ表示
        return ChatRoom::whereHas('messages')
            ->where('user_id', $request->user()->id)
            ->get();
    }
}
```

#### 管理コマンド

```bash
# 既存ユーザーのサポートチャットルーム作成
php artisan support:create-chat-rooms

# 外部トークンクリーンアップ
php artisan tokens:cleanup
```

### 💳 決済機能

#### Stripe 統合

-   **テストモード**: 開発環境での安全なテスト
-   **本番モード**: 実際の決済処理
-   **Webhook**: リアルタイム決済状態同期

#### サブスクリプション

-   **Standard Plan**: ¥2,980/月（最大 50 名）
-   **Premium Plan**: ¥5,980/月（最大 200 名）
-   **アップグレード/ダウングレード**: リアルタイム対応

### 🔐 認証システム

#### Laravel Sanctum

-   **SPA 認証**: シングルページアプリケーション対応
-   **API 認証**: トークンベース認証
-   **CSRF 保護**: クロスサイトリクエストフォージェリ対策

#### 認証フロー

```php
// API認証例
$response = $this->post('/api/login', [
    'email' => 'user@example.com',
    'password' => 'password'
]);

$token = $response->json('token');

// 認証が必要なAPI呼び出し
$response = $this->withHeaders([
    'Authorization' => "Bearer {$token}"
])->get('/api/user');
```

---

## 🧪 テスト

### テスト実行

```bash
# 全テスト実行
php artisan test

# 特定のテストスイート実行
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# カバレッジ付きテスト
php artisan test --coverage

# 並列テスト実行
php artisan test --parallel
```

### 主要テストケース

#### 認証テスト

```php
// tests/Feature/AuthTest.php
class AuthTest extends TestCase
{
    public function test_user_can_register()
    {
        $response = $this->post('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com'
        ]);
    }
}
```

#### 決済テスト

```php
// tests/Feature/PaymentTest.php
class PaymentTest extends TestCase
{
    public function test_user_can_create_subscription()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/subscriptions', [
                'price_id' => 'price_test_standard'
            ]);

        $response->assertStatus(201);
    }
}
```

---

## 🚀 デプロイ

### Railway デプロイ

```bash
# 1. Railway CLI インストール
npm install -g @railway/cli

# 2. ログイン
railway login

# 3. プロジェクト作成
railway init

# 4. デプロイ
railway up
```

### 環境変数設定

Railway ダッシュボードで以下を設定：

```env
# 本番環境設定
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.railway.app

# データベース（Railway MySQL）
DATABASE_URL=mysql://user:password@host:port/database

# Stripe（本番キー）
STRIPE_SECRET_KEY=sk_live_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

---

## 🔧 運用・管理

### 主要コマンド

```bash
# キューワーカー起動
php artisan queue:work

# スケジューラー実行（cron設定）
php artisan schedule:run

# キャッシュクリア
php artisan cache:clear
php artisan view:clear
php artisan config:clear

# 最適化
php artisan optimize
php artisan route:cache
php artisan view:cache
```

### ログ監視

```bash
# アプリケーションログ
tail -f storage/logs/laravel.log

# クエリログ有効化
DB_LOG_QUERIES=true
```

### パフォーマンス監視

```bash
# Laravel Telescope アクセス
http://localhost:8000/telescope

# メトリクス確認
php artisan metrics:show
```

---

## 📖 設定ガイド

### 詳細設定ガイド

| 設定項目                      | ガイドドキュメント                                     |
| ----------------------------- | ------------------------------------------------------ |
| **💳 Stripe 決済**            | [Stripe 設定ガイド](./STRIPE_SETUP.md)                 |
| **📧 Gmail SMTP**             | [Gmail 設定ガイド](./GMAIL_SETUP_GUIDE.md)             |
| **🚀 Railway デプロイ**       | [Railway 設定ガイド](./RAILWAY_EMAIL_SETUP.md)         |
| **🐳 Docker 環境**            | [Docker 設定ガイド](./DOCKER_RESTART_CHECKLIST.md)     |
| **⚓ Laravel Sail**           | [Sail 設定ガイド](./SAIL_STARTUP_GUIDE.md)             |
| **🔧 トラブルシューティング** | [トラブルシューティング](./RAILWAY_TROUBLESHOOTING.md) |

### 管理者セットアップ

```bash
# 管理者アカウント作成
php artisan make:admin

# 管理者権限付与
php artisan admin:grant user@example.com
```

---

## 🔍 デバッグ・トラブルシューティング

### よくある問題

#### 1. データベース接続エラー

```bash
# 接続テスト
php artisan db:show

# マイグレーション状態確認
php artisan migrate:status
```

#### 2. キューが動作しない

```bash
# キューワーカー状態確認
php artisan queue:monitor

# 失敗したジョブ確認
php artisan queue:failed
```

#### 3. Stripe Webhook エラー

```bash
# Webhook ログ確認
php artisan webhook:logs

# Stripe イベント再送信
php artisan stripe:resend-event event_id
```

### デバッグツール

```php
// デバッグ出力
dd($variable);
dump($variable);

// ログ出力
Log::info('Debug message', ['data' => $data]);

// SQLクエリログ
DB::enableQueryLog();
// ... クエリ実行 ...
dump(DB::getQueryLog());
```

---

## 🤝 開発・貢献

### 開発フロー

```bash
# 1. 新機能ブランチ作成
git checkout -b feature/new-feature

# 2. コード実装
# ... 開発作業 ...

# 3. テスト実行
php artisan test

# 4. 静的解析
./vendor/bin/phpstan analyse

# 5. コミット・プッシュ
git add .
git commit -m "feat: 新機能を追加"
git push origin feature/new-feature
```

### コーディング規約

```php
// PSR-12 準拠
// 型宣言必須
function processPayment(User $user, string $paymentMethod): PaymentResult
{
    // 実装
}

// 配列型宣言
/** @param array<string, mixed> $data */
function processData(array $data): void
{
    // 実装
}
```

---

## 📞 サポート

### 技術サポート

-   **バグ報告**: [GitHub Issues](https://github.com/your-username/chat-app_nuxt/issues)
-   **機能要望**: [GitHub Discussions](https://github.com/your-username/chat-app_nuxt/discussions)
-   **セキュリティ**: [security@lumo-chat.com](mailto:security@lumo-chat.com)

### 関連ドキュメント

-   **📚 メインドキュメント**: [プロジェクト README](../README.md)
-   **🎨 フロントエンド**: [フロントエンド README](../frontend/README.md)
-   **🧪 テストガイド**: [テストガイド](./TESTING.md)

---

<div align="center">
  <p>🔧 <strong>LumoChat Backend</strong> - 堅牢で高性能、スケーラブル</p>
  
  [📚 メインドキュメント](../README.md) | 
  [🎨 フロントエンド](../frontend/README.md) | 
  [🎯 実装計画](../implementation_plan.md)
</div>
