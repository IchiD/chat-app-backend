# Admin管理画面システム セットアップガイド

## 概要

このプロジェクトでは、Laravel用のadmin管理画面システムを構築しました。既存のuserモデルとは別のadminモデルを使用し、スーパーアドミンとアドミンの2つの権限レベルを提供します。

## 機能

### ✅ 実装済み機能

1. **Admin認証システム**
   - 独立したadmin guardを使用
   - ログイン/ログアウト機能
   - セッション管理

2. **権限管理**
   - **スーパーアドミン**: 全ての管理機能（アドミン管理含む）
   - **アドミン**: 基本的な管理機能（ユーザー管理のみ）

3. **管理画面**
   - 美しいBootstrap 5ベースのUI
   - レスポンシブデザイン
   - ダッシュボード（統計情報表示）
   - ユーザー管理画面
   - アドミン管理画面（スーパーアドミン専用）

4. **セキュリティ**
   - CSRF保護
   - パスワードハッシュ化
   - 権限ベースのアクセス制御

## セットアップ手順

### 1. マイグレーション実行

```bash
# Docker環境の場合
./vendor/bin/sail artisan migrate

# 通常のPHP環境の場合
php artisan migrate
```

### 2. 初期adminアカウント作成

```bash
# Docker環境の場合
./vendor/bin/sail artisan db:seed --class=AdminSeeder

# 通常のPHP環境の場合
php artisan db:seed --class=AdminSeeder
```

### 3. 初期ログイン情報

**スーパーアドミンアカウント:**
- メール: `admin@example.com`
- パスワード: `password123`

**通常のアドミンアカウント:**
- メール: `admin2@example.com`
- パスワード: `password123`

## アクセス方法

### 管理画面ログイン
```
http://localhost/admin/login
```

### 管理画面ダッシュボード（ログイン後）
```
http://localhost/admin/dashboard
```

## ファイル構成

### モデル
- `app/Models/Admin.php` - Adminモデル

### コントローラー
- `app/Http/Controllers/Admin/AdminAuthController.php` - 認証
- `app/Http/Controllers/Admin/AdminDashboardController.php` - 管理画面

### ミドルウェア
- `app/Http/Middleware/AdminMiddleware.php` - Admin認証チェック

### ビューファイル
- `resources/views/admin/layouts/app.blade.php` - 基本レイアウト
- `resources/views/admin/auth/login.blade.php` - ログイン画面
- `resources/views/admin/dashboard.blade.php` - ダッシュボード
- `resources/views/admin/users/index.blade.php` - ユーザー管理
- `resources/views/admin/admins/index.blade.php` - アドミン管理

### マイグレーション
- `database/migrations/2025_01_20_000000_create_admins_table.php`

### シーダー
- `database/seeders/AdminSeeder.php`

## ルート構成

```php
Route::prefix('admin')->name('admin.')->group(function () {
    // 認証ルート
    Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

    // 認証が必要なルート
    Route::middleware(['admin'])->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('users', [AdminDashboardController::class, 'users'])->name('users');
        
        // スーパーアドミン専用
        Route::get('admins', [AdminDashboardController::class, 'admins'])->name('admins');
        Route::post('admins', [AdminDashboardController::class, 'createAdmin'])->name('admins.create');
    });
});
```

## カスタマイズ

### 1. デザインのカスタマイズ
- `resources/views/admin/layouts/app.blade.php`のCSSを編集

### 2. 権限の追加
- `app/Models/Admin.php`で新しい権限を定義
- マイグレーションでroleのenumを更新

### 3. 新しい管理機能の追加
- 新しいコントローラーとビューを作成
- ルートを追加
- 必要に応じてミドルウェアで権限チェック

## セキュリティ注意事項

1. **本番環境では必ずパスワードを変更してください**
2. **HTTPSを使用してください**
3. **定期的にアクセスログを確認してください**
4. **不要なアドミンアカウントは削除してください**

## トラブルシューティング

### ログインできない場合
1. マイグレーションが実行されているか確認
2. シーダーが実行されているか確認
3. ブラウザのキャッシュをクリア

### 権限エラーが発生する場合
1. ミドルウェアが正しく登録されているか確認
2. ユーザーの権限レベルを確認

### ページが表示されない場合
1. ルートが正しく設定されているか確認
2. コントローラーのnamespaceが正しいか確認

## 今後の拡張予定

- [ ] ユーザー詳細編集機能
- [ ] ユーザー削除機能
- [ ] アドミン編集・削除機能
- [ ] アクセスログ機能
- [ ] エクスポート機能
- [ ] メール通知機能

---

**注意**: この管理画面システムは開発用として設計されています。本番環境で使用する場合は、追加のセキュリティ対策を検討してください。