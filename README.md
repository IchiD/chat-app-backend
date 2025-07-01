# プロジェクトの立ち上げ

以下のコマンドでプロジェクトを起動します。

```bash
sail up -d
```

## キューワーカーの起動

キューワーカーを以下のコマンドで起動します。

```bash
sail artisan queue:work
```

## 外部トークンクリーンアップ

期限切れの外部 API トークンを削除するコマンドです。

```bash
sail artisan tokens:cleanup
```
