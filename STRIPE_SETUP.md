# Stripe 決済機能のローカル開発セットアップ

## 概要

このアプリケーションの決済機能は、Stripe のテストモードを使用してローカル開発を行います。
ダミー処理ではなく、実際の Stripe API を使用することで、本番環境との整合性を保ちます。

## セットアップ手順

### 1. Stripe アカウントの作成

1. [Stripe](https://stripe.com)でアカウントを作成
2. ダッシュボードにログイン
3. 左上の「テストデータを表示」がオンになっていることを確認

### 2. API キーの取得

1. Stripe ダッシュボードで「開発者」→「API キー」に移動
2. 以下のキーをコピー：
    - **公開可能キー** (pk*test*で始まる)
    - **シークレットキー** (sk*test*で始まる)

### 3. 商品と価格の作成

1. Stripe ダッシュボードで「商品」に移動
2. 「商品を追加」をクリック

#### Standard プラン

-   商品名: `Standard Plan`
-   価格: `¥2,980`
-   請求間隔: `月次`
-   価格 ID をコピー (price\_で始まる)

#### Premium プラン

-   商品名: `Premium Plan`
-   価格: `¥5,980`
-   請求間隔: `月次`
-   価格 ID をコピー (price\_で始まる)

### 4. Webhook エンドポイントの設定

1. Stripe ダッシュボードで「開発者」→「Webhook」に移動
2. 「エンドポイントを追加」をクリック
3. エンドポイント URL: `http://localhost/api/stripe/webhook`
4. 以下のイベントを選択：
    - `checkout.session.completed`
    - `customer.subscription.updated`
    - `customer.subscription.deleted`
    - `invoice.payment_succeeded`
    - `invoice.payment_failed`
    - `payment_intent.succeeded`
    - `payment_intent.payment_failed`
5. Webhook 署名シークレットをコピー (whsec\_で始まる)

### 5. 環境変数の設定

#### バックエンド設定

`backend/.env`ファイルに以下を設定：

```env
# Stripe設定
STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
STRIPE_PUBLISHABLE_KEY=pk_test_xxxxx
STRIPE_PRICE_STANDARD=price_xxxxx
STRIPE_PRICE_PREMIUM=price_xxxxx
```

#### フロントエンド設定

`frontend/.env`ファイルに以下を設定：

```env
# Stripe Configuration
NUXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=pk_test_xxxxx

# API Configuration
NUXT_PUBLIC_API_BASE_URL=http://localhost
```

### 6. アプリケーションの再起動

設定変更後、アプリケーションを再起動：

```bash
# バックエンド
cd backend
docker compose restart laravel.test

# フロントエンド
cd frontend
npm run dev
```

## テスト方法

### テスト用カード番号

Stripe テストモードでは以下のカード番号を使用できます：

#### 基本テスト

-   **成功**: `4242 4242 4242 4242`
-   **失敗**: `4000 0000 0000 0002`
-   **3D セキュア**: `4000 0025 0000 3155`

#### デプロイ前推奨テスト

-   **残高不足**: `4000 0000 0000 9995`
-   **期限切れカード**: `4000 0000 0000 0069`
-   **CVC エラー**: `4000 0000 0000 0127`
-   **処理拒否**: `4000 0000 0000 0341`
-   **紛失・盗難カード**: `4000 0000 0000 9987`

#### サブスクリプション継続課金テスト

-   **初回成功 →2 回目失敗**: `4000 0000 0000 0341`
-   **継続課金成功**: `4242 4242 4242 4242`
-   **アップグレード**: `4242 4242 4242 4242` → 異なるプランに変更

#### 国際カード・カード種別テスト

-   **Visa (海外)**: `4000 0000 0000 3220` (3D セキュア必須)
-   **Mastercard**: `5555 5555 5555 4444`
-   **American Express**: `3782 8224 6310 005`
-   **JCB**: `3530 1113 3330 0000`
-   **デビットカード**: `4000 0566 5566 5556`

**3D セキュア決済について：**

-   3D セキュア認証が必要なカードでは、通常の決済フローと異なります
-   `checkout.session.completed` と `payment_intent.succeeded` の両方のイベントが発生します
-   システムでは重複防止機能により、決済履歴は 1 件のみ記録されます
-   `checkout.session.completed` でトランザクションが作成され、`payment_intent.succeeded` で 3D 認証完了として更新されます
-   認証完了まで時間がかかる場合があります

**テスト時の設定：**

-   有効期限: 任意の未来の日付
-   CVC: 任意の 3 桁の数字（AMEX は 4 桁）
-   郵便番号: 任意の有効な形式

### 決済フローのテスト

#### 基本テストシナリオ

1. アプリケーションにログイン
2. `/pricing`ページでプランを選択
3. Stripe の決済ページでテストカード番号を入力
4. 決済完了後、サブスクリプション状態を確認

#### デプロイ前必須テストチェックリスト

**✅ 新規登録・決済テスト**

-   [ ] 通常決済成功 (`4242 4242 4242 4242`)
-   [ ] 3D セキュア決済成功 (`4000 0025 0000 3155`)
-   [ ] 決済失敗処理 (`4000 0000 0000 0002`)
-   [ ] 残高不足エラー (`4000 0000 0000 9995`)
-   [ ] CVC エラー処理 (`4000 0000 0000 0127`)

**✅ プラン変更テスト**

-   [ ] STANDARD → PREMIUM へアップグレード
-   [ ] PREMIUM → STANDARD へダウングレード（テストモードのみ）
-   [ ] プラン変更時の日割り計算確認

**✅ サブスクリプション管理テスト**

-   [ ] サブスクリプションキャンセル
-   [ ] キャンセル後の再開（テストモードのみ）
-   [ ] 期間終了時の自動更新

**✅ 国際決済・カード種別テスト**

-   [ ] Visa 以外のカードブランド (Mastercard: `5555 5555 5555 4444`)
-   [ ] American Express (`3782 8224 6310 005`)
-   [ ] JCB (`3530 1113 3330 0000`)

**✅ エラーハンドリングテスト**

-   [ ] 決済ページでのキャンセル
-   [ ] ネットワークエラー時の処理
-   [ ] Webhook 遅延時の処理
-   [ ] 重複決済防止の確認

**✅ 管理画面テスト**

-   [ ] 決済履歴の正確な表示
-   [ ] サブスクリプション一覧の確認
-   [ ] Webhook ログの正常性確認

### Webhook のテスト

ローカル環境で Webhook をテストする場合：

1. Stripe CLI をインストール
   　 2. 必要なイベントを指定してリスナーを起動：

```bash
stripe listen --forward-to localhost/api/stripe/webhook --events checkout.session.completed,customer.subscription.updated,customer.subscription.deleted,invoice.payment_succeeded,invoice.payment_failed,payment_intent.succeeded,payment_intent.payment_failed
```

3. 表示される Webhook 署名シークレットを`.env`に設定

**重要：** 3D セキュア決済対応のため、`payment_intent.succeeded` イベントを必ず含めてください。

## 設定確認

### 一括設定確認コマンド

以下のコマンドで Stripe 設定を一括確認：

```bash
cd backend
docker compose exec laravel.test php artisan stripe:config-check
```

このコマンドは以下をチェックします：

-   環境変数の設定状況
-   Stripe API 接続
-   価格設定の有効性

### 個別確認コマンド

#### API 接続テスト

```bash
docker compose exec laravel.test php artisan tinker --execute="
try {
  \$stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
  \$account = \$stripe->account->retrieve();
  echo 'Stripe connection successful: ' . \$account->id . PHP_EOL;
} catch (Exception \$e) {
  echo 'Stripe connection failed: ' . \$e->getMessage() . PHP_EOL;
}
"
```

#### 価格 ID 確認

```bash
docker compose exec laravel.test php artisan tinker --execute="
echo 'Standard Price ID: ' . config('services.stripe.prices.standard') . PHP_EOL;
echo 'Premium Price ID: ' . config('services.stripe.prices.premium') . PHP_EOL;
"
```

## 本番環境との違い

-   **テストモード**: 実際の決済は発生しません
-   **ダウングレード**: テストモードではダウングレードが許可されます
-   **Webhook**: ローカル環境では Stripe CLI を使用して Webhook をテストします

## トラブルシューティング

### よくある問題

1. **API キーエラー**:

    - テストモードのキーを使用していることを確認
    - `.env`ファイルの設定を確認

2. **価格 ID エラー**:

    - Stripe ダッシュボードで価格 ID が正しいことを確認
    - テストモードの価格 ID を使用していることを確認

3. **Webhook エラー**:

    - エンドポイント URL が正しいことを確認
    - 署名シークレットが正しいことを確認

4. **3D セキュア決済が反映されない**:
    - `payment_intent.succeeded` イベントが有効になっているか確認
    - Stripe CLI で該当イベントが届いているか確認
    - ログで Webhook の処理状況を確認

### ログの確認

```bash
# Laravelログ
tail -f storage/logs/laravel.log

# Stripeイベントの確認
# Stripeダッシュボード → 開発者 → イベント
```

### 設定リセット

テストデータをクリアする場合：

```bash
docker compose exec laravel.test php artisan tinker --execute="
App\Models\Subscription::truncate();
App\Models\SubscriptionHistory::truncate();
App\Models\User::query()->update(['plan' => 'free', 'subscription_status' => null]);
echo 'Test data cleared';
"
```

## 次のステップ

1. Stripe アカウントを作成
2. テスト用の商品と価格を作成
3. 環境変数を設定
4. アプリケーションを再起動
5. テスト決済を実行

## 参考リンク

-   [Stripe テストモード](https://stripe.com/docs/testing)
-   [Stripe Webhook](https://stripe.com/docs/webhooks)
-   [Stripe CLI](https://stripe.com/docs/stripe-cli)
