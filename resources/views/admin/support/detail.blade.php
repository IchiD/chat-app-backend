@extends('admin.layouts.app')

@section('title', 'お問い合わせ詳細')

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
          <i class="fas fa-comment-dots me-2"></i>お問い合わせ詳細
        </h1>
        <div>
          <a href="{{ route('admin.support') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>一覧に戻る
          </a>
        </div>
      </div>

      <!-- チャット情報 -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0">
            チャット情報
            @if($unreadCount > 0)
              <span class="badge bg-warning text-dark ms-2">{{ $unreadCount }}件の未読メッセージ</span>
            @else
              <span class="badge bg-success ms-2">すべて既読</span>
            @endif
          </h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <strong>ユーザー:</strong>
              @php
              // サポートチャットでは participant1 がユーザー、participant2 が管理者（通常はnull）
              $user = $conversation->participant1;
              @endphp
              @if($user)
              {{ $user->name }}
              ({{ $user->email }})
              @else
              ユーザー不明
              @endif
            </div>
            <div class="col-md-3">
              <strong>作成日時:</strong> {{ $conversation->created_at->format('Y/m/d H:i') }}
            </div>
            <div class="col-md-3">
              <strong>更新日時:</strong> {{ $conversation->updated_at->format('Y/m/d H:i') }}
            </div>
          </div>
        </div>
      </div>

      <!-- メッセージ履歴 -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0">メッセージ履歴</h5>
        </div>
        <div class="card-body">
          <div class="messages-container" style="height: 400px; overflow-y: auto; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; background-color: #f8f9fa; display: flex; flex-direction: column-reverse;">
            @if($messages->count() > 0)
            @foreach($messages as $message)
            @php
            // 管理者からのメッセージかどうかを判定
            $isAdminMessage = !is_null($message->admin_sender_id);
            $isUserMessage = !$isAdminMessage && !is_null($message->sender_id);
            
            // 未読メッセージかどうかを判定（管理者の最後の既読時刻より後のユーザーメッセージ）
            $lastRead = \App\Models\AdminChatRead::where('admin_id', $admin->id)
                ->where('chat_room_id', $conversation->id)
                ->first();
            $isUnreadUserMessage = $isUserMessage && 
                (!$lastRead || !$lastRead->last_read_at || $message->sent_at > $lastRead->last_read_at);
            @endphp

            <div class="message mb-3 {{ $isUserMessage ? 'user-message' : 'admin-message' }} {{ $isUnreadUserMessage ? 'unread-message' : '' }}">
              <div class="d-flex {{ $isUserMessage ? 'justify-content-start' : 'justify-content-end' }}">
                <div class="message-bubble p-3 rounded" style="max-width: 70%; {{ $isUserMessage ? 'background-color: #e9ecef;' : 'background-color: #007bff; color: white;' }}">
                  <div class="message-header mb-2">
                    <strong>
                      @if($isUserMessage)
                      {{ $message->getSenderDisplayName() }}
                      @else
                      {{ $admin->name }}（管理者）
                      @endif
                    </strong>
                    <small class="text-muted {{ $isUserMessage ? '' : 'text-light' }} ms-2">
                      {{ $message->sent_at->format('Y/m/d H:i') }}
                    </small>
                  </div>
                  <div class="message-content">
                    {{ $message->text_content }}
                  </div>
                </div>
              </div>
            </div>
            @endforeach
            @else
            <div class="d-flex justify-content-center align-items-center h-100">
              <div class="text-center text-muted">
                <i class="fas fa-comments fa-3x mb-3"></i>
                <p>まだメッセージがありません</p>
              </div>
            </div>
            @endif
          </div>
        </div>
      </div>

      <!-- 返信フォーム -->
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">返信を送信</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.support.reply', $conversation->id) }}">
            @csrf
            <div class="mb-3">
              <label for="message" class="form-label">メッセージ</label>
              <textarea class="form-control @error('message') is-invalid @enderror"
                id="message" name="message" rows="4"
                placeholder="ユーザーへの返信メッセージを入力してください..." required>{{ old('message') }}</textarea>
              @error('message')
              <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="d-flex justify-content-end">
              @if($unreadCount > 0)
                <button type="button" class="btn btn-warning me-2" onclick="markAsRead()">
                  <i class="fas fa-bell me-1"></i>{{ $unreadCount }}件の未読を既読にする
                </button>
              @else
                <button type="button" class="btn btn-success me-2" disabled>
                  <i class="fas fa-check me-1"></i>既読済み
                </button>
              @endif
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane me-1"></i>送信
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // メッセージコンテナを最下部にスクロール
  document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) {
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
  });

  // 手動既読機能
  function markAsRead() {
    const button = document.querySelector('button[onclick="markAsRead()"]');
    const originalContent = button.innerHTML;
    
    // ローディング状態を表示
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>既読処理中...';
    button.disabled = true;
    
    fetch('{{ route("admin.support.mark-read", $conversation->id) }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      }
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // 既読ボタンを成功状態に変更
        button.innerHTML = '<i class="fas fa-check me-1"></i>既読済み';
        button.classList.remove('btn-warning');
        button.classList.add('btn-success');
        button.disabled = true;
        button.removeAttribute('onclick');
        
        // 成功メッセージを表示
        showAlert('{{ $unreadCount }}件のメッセージを既読にしました。', 'success');
        
        // 未読メッセージのハイライトを削除（もしあれば）
        const userMessages = document.querySelectorAll('.user-message');
        userMessages.forEach(msg => {
          msg.classList.remove('unread-message');
        });
        
        // チャット情報エリアのバッジも更新
        const chatInfoBadge = document.querySelector('.card-header .badge');
        if (chatInfoBadge) {
          chatInfoBadge.className = 'badge bg-success ms-2';
          chatInfoBadge.textContent = 'すべて既読';
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      // エラー時は元の状態に戻す
      button.innerHTML = originalContent;
      button.disabled = false;
      showAlert('既読処理中にエラーが発生しました。', 'danger');
    });
  }

  // アラート表示関数
  function showAlert(message, type) {
    const alertHtml = `
      <div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    `;
    
    // アラートを挿入する場所を見つけて表示
    const container = document.querySelector('.container-fluid .row .col-12');
    const firstCard = container.querySelector('.card');
    firstCard.insertAdjacentHTML('beforebegin', alertHtml);
    
    // 3秒後に自動的に削除
    setTimeout(() => {
      const alert = container.querySelector('.alert');
      if (alert) {
        alert.remove();
      }
    }, 3000);
  }
</script>

<style>
  .message-bubble {
    word-wrap: break-word;
  }

  .user-message .message-bubble {
    border-left: 4px solid #6c757d;
  }

  .admin-message .message-bubble {
    border-left: 4px solid #007bff;
  }

  /* 未読メッセージのスタイル */
  .unread-message .message-bubble {
    border-left: 4px solid #ffc107 !important;
    background-color: #fff3cd !important;
    box-shadow: 0 0 10px rgba(255, 193, 7, 0.3);
  }

  .unread-message::before {
    content: "未読";
    position: absolute;
    top: -8px;
    left: 10px;
    background-color: #ffc107;
    color: #000;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: bold;
    z-index: 10;
  }

  .unread-message {
    position: relative;
  }

  /* 既読処理後のアニメーション */
  .message.read-transition {
    transition: all 0.5s ease-in-out;
  }
</style>
@endsection