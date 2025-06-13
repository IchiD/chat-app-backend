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
        <a href="{{ route('admin.support') }}" class="btn btn-secondary">
          <i class="fas fa-arrow-left me-1"></i>一覧に戻る
        </a>
      </div>

      <!-- チャット情報 -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-title mb-0">チャット情報</h5>
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
            @endphp

            <div class="message mb-3 {{ $isUserMessage ? 'user-message' : 'admin-message' }}">
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
</style>
@endsection