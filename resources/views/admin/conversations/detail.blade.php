@extends('admin.layouts.app')

@section('title', 'チャットルーム詳細')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-comment-dots me-2"></i>チャットルーム詳細
        </h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">ダッシュボード</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.conversations') }}">チャットルーム</a></li>
            <li class="breadcrumb-item active">チャットルーム #{{ $chatRoom->id }}</li>
          </ol>
        </nav>
      </div>
      <div>
        <a href="{{ route('admin.conversations') }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>一覧に戻る
        </a>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12">
    <div class="card {{ $chatRoom->isDeleted() ? 'border-danger' : '' }}">
      <div class="card-header {{ $chatRoom->isDeleted() ? 'bg-danger text-white' : '' }}">
        <div class="d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">
            <i class="fas fa-info-circle me-2"></i>会話情報
          </h5>
          @if($chatRoom->isDeleted())
          <form method="POST" action="{{ route('admin.conversations.restore', $chatRoom->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-light" onclick="return confirm('この会話の削除を取り消しますか？')">
              <i class="fas fa-undo me-1"></i>削除を取り消し
            </button>
          </form>
          @else
          <button type="button" class="btn btn-sm btn-outline-light" onclick="showDeleteConversationModal()">
            <i class="fas fa-trash me-1"></i>会話を削除
          </button>
          @endif
        </div>
      </div>
      <div class="card-body">
        @if($chatRoom->isDeleted())
        <div class="alert alert-danger mb-3">
          <i class="fas fa-exclamation-triangle me-2"></i>
          <strong>この会話は削除されています</strong>
          <div class="mt-2">
            <strong>削除日時:</strong> {{ $chatRoom->deleted_at->format('Y/m/d H:i') }}<br>
            @if($chatRoom->deletedByAdmin)
            <strong>削除者:</strong> {{ $chatRoom->deletedByAdmin->name }}<br>
            @endif
            @if($chatRoom->deleted_reason)
            <strong>削除理由:</strong> {{ $chatRoom->deleted_reason }}
            @endif
          </div>
        </div>
        @endif

        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label text-muted">会話ID</label>
              <div class="fw-bold">#{{ $chatRoom->id }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">ルームトークン</label>
              <div><code class="bg-light p-2 rounded">{{ $chatRoom->room_token }}</code></div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">作成日時</label>
              <div>{{ $chatRoom->created_at->format('Y/m/d H:i') }}</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label text-muted">参加者</label>
              <div class="d-flex flex-wrap gap-1">
                @if($chatRoom->type === 'friend_chat')
                @if($chatRoom->participant1)
                <span class="badge bg-light text-dark border">{{ $chatRoom->participant1->name }}</span>
                @endif
                @if($chatRoom->participant2)
                <span class="badge bg-light text-dark border">{{ $chatRoom->participant2->name }}</span>
                @endif
                @elseif($chatRoom->type === 'group_chat' && $chatRoom->group)
                @foreach($chatRoom->group->activeMembers as $member)
                <span class="badge bg-light text-dark border">{{ $member->user->name ?? '削除されたユーザー' }}</span>
                @endforeach
                @endif
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">メッセージ数</label>
              <div><span class="badge bg-primary">{{ $messages->total() }}</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-envelope me-2"></i>メッセージ一覧</h5>
      </div>
      <div class="card-body">
        @if($messages->count() > 0)
        <div class="messages-container">
          @foreach($messages as $message)
          <div class="message-item mb-3 p-3 border rounded {{ $message->isAdminDeleted() ? 'border-danger bg-light' : ($message->deleted_at ? 'border-warning bg-light' : '') }}">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-2">
                  <div>
                    <div class="fw-bold">{{ $message->getSenderDisplayName() }}</div>
                    <small class="text-muted">{{ $message->sent_at->format('Y/m/d H:i:s') }}</small>
                  </div>
                </div>

                @if($message->isAdminDeleted())
                <div class="alert alert-danger mb-2">
                  <i class="fas fa-trash me-1"></i>
                  <strong>管理者により削除されました</strong>
                  <div class="mt-1">
                    <small>
                      削除日時: {{ $message->admin_deleted_at->format('Y/m/d H:i') }}
                      @if($message->adminDeletedBy)
                      | 削除者: {{ $message->adminDeletedBy->name }}
                      @endif
                      @if($message->admin_deleted_reason)
                      <br>理由: {{ $message->admin_deleted_reason }}
                      @endif
                    </small>
                  </div>
                </div>
                <div class="text-muted fst-italic">元のメッセージ: "{{ $message->text_content }}"</div>
                @elseif($message->deleted_at)
                <div class="alert alert-warning mb-2">
                  <i class="fas fa-exclamation-triangle me-1"></i>
                  <strong>ユーザーにより削除されました</strong>
                  <small class="d-block">削除日時: {{ $message->deleted_at->format('Y/m/d H:i') }}</small>
                </div>
                <div class="text-muted fst-italic">元のメッセージ: "{{ $message->text_content }}"</div>
                @else
                <div class="message-content">{{ $message->text_content }}</div>
                @endif
              </div>
            </div>
          </div>
          @endforeach
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted">{{ $messages->firstItem() }}〜{{ $messages->lastItem() }}件目 / 全{{ $messages->total() }}件</div>
          <div>{{ $messages->links() }}</div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">メッセージがありません</h5>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteConversationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">会話削除確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.conversations.delete', $chatRoom->id) }}">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告:</strong> この操作により、会話が論理削除されます。
          </div>
          <div class="mb-3">
            <label for="deleteConversationReason" class="form-label">削除理由</label>
            <textarea class="form-control" id="deleteConversationReason" name="reason" rows="3" placeholder="削除理由を入力してください"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-danger">削除する</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function showDeleteConversationModal() {
    new bootstrap.Modal(document.getElementById('deleteConversationModal')).show();
  }
</script>
@endsection