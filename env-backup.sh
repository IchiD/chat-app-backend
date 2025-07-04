#!/bin/bash

# .env バックアップ管理スクリプト
# 使用方法: ./env-backup.sh [backup|restore|cleanup]

BACKUP_DIR="./env-backups"
CURRENT_ENV=".env"
BACKUP_PREFIX="env-backup"
MAX_BACKUPS=5  # 保持する最大バックアップ数

# バックアップディレクトリの作成
mkdir -p "$BACKUP_DIR"

case "${1:-backup}" in
    "backup")
        if [[ -f "$CURRENT_ENV" ]]; then
            # 現在のバックアップファイルを保存
            if [[ -f ".env.backup" ]]; then
                mv ".env.backup" "$BACKUP_DIR/$BACKUP_PREFIX-$(date +%Y%m%d_%H%M%S).env"
            fi
            
            # 新しいバックアップを作成
            cp "$CURRENT_ENV" ".env.backup"
            echo "✅ .envファイルのバックアップを作成しました"
            
            # 古いバックアップの削除（最大5個まで保持）
            cd "$BACKUP_DIR"
            ls -t $BACKUP_PREFIX-*.env 2>/dev/null | tail -n +$((MAX_BACKUPS+1)) | xargs rm -f 2>/dev/null
            echo "📁 古いバックアップをクリーンアップしました"
        else
            echo "❌ .envファイルが見つかりません"
            exit 1
        fi
        ;;
    
    "restore")
        if [[ -f ".env.backup" ]]; then
            # 現在の.envをバックアップしてから復元
            cp "$CURRENT_ENV" "$BACKUP_DIR/env-before-restore-$(date +%Y%m%d_%H%M%S).env"
            cp ".env.backup" "$CURRENT_ENV"
            echo "✅ .envファイルを復元しました"
        else
            echo "❌ .env.backupファイルが見つかりません"
            exit 1
        fi
        ;;
    
    "cleanup")
        # 古いバックアップファイルを削除
        cd "$BACKUP_DIR"
        ls -t $BACKUP_PREFIX-*.env 2>/dev/null | tail -n +$((MAX_BACKUPS+1)) | xargs rm -f 2>/dev/null
        echo "🧹 古いバックアップファイルを削除しました"
        ;;
    
    *)
        echo "使用方法: $0 [backup|restore|cleanup]"
        echo "  backup  : 現在の.envファイルのバックアップを作成"
        echo "  restore : .env.backupから.envを復元"
        echo "  cleanup : 古いバックアップファイルを削除"
        exit 1
        ;;
esac 