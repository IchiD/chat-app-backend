@extends('admin.layouts.app')

@section('title', 'ユーザー会話管理')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-comments me-2"></i>会話管理
        </h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">ダッシュボード</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">ユーザー管理</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users.show', $user->id) }}">{{ $user->name }}</a></li>
            <li class="breadcrumb-item active">会話管理</li>
          </ol>
        </nav>
      </div>
      <div>
        <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>ユーザー詳細に戻る
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ユーザー情報カード -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card {{ $user->isDeleted() ? 'border-warning' : '' }}">
      <div class="card-body">
        <div class="d-flex align-items-center">
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h5 class="mb-1">{{ $user->name }}</h5>
                <p class="text-muted mb-0">
                  {{ $user->email }} | フレンドID: <code>{{ $user->friend_id }}</code>
                  @if($user->isDeleted())
                  <span class="badge bg-danger ms-2">削除済み</span>
                  @endif
                </p>
              </div>
              <div class="text-end">
                <div class="small text-muted">参加会話数</div>
                <div class="h4 mb-0">{{ $conversations->total() }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 会話一覧 -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-list me-2"></i>参加している会話一覧
        </h5>
      </div>
      <div class="card-body">
        @if($conversations->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>会話ID</th>
                <th>参加者</th>
                <th>最新メッセージ</th>
                <th>最終更新</th>
                <th>メッセージ数</th>
                <th>状態</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($conversations as $conversation)
              <tr class="{{ $conversation->isDeleted() ? 'table-warning' : '' }}">
                <td>
                  <span class="fw-bold text-primary">#{{ $conversation->id }}</span>
                  @if($conversation->room_token)
                  <br><small class="text-muted">{{ $conversation->room_token }}</small>
                  @endif
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    @foreach($conversation->participants->take(3) as $participant)
                    <span class="badge bg-light text-dark border">
                      {{ $participant->name }}
                    </span>
                    @endforeach
                    @if($conversation->participants->count() > 3)
                    <span class="badge bg-secondary">
                      +{{ $conversation->participants->count() - 3 }}
                    </span>
                    @endif
                  </div>
                  <small class="text-muted">
                    {{ $conversation->participants->count() }}人参加
                  </small>
                </td>
                <td>
                  @if($conversation->latestMessage)
                  <div class="text-truncate" style="max-width: 200px;">
                    <strong>{{ $conversation->latestMessage->sender->name }}:</strong>
                    {{ $conversation->latestMessage->text_content }}
                  </div>
                  <small class="text-muted">
                    {{ $conversation->latestMessage->sent_at->format('m/d H:i') }}
                  </small>
                  @else
                  <span class="text-muted">メッセージなし</span>
                  @endif
                </td>
                <td>
                  <div>{{ $conversation->updated_at->format('Y/m/d') }}</div>
                  <small class="text-muted">{{ $conversation->updated_at->format('H:i') }}</small>
                </td>
                <td>
                  <span class="badge bg-info">{{ $conversation->messages->count() }}</span>
                </td>
                <td>
                  @if($conversation->isDeleted())
                  <span class="badge bg-danger">
                    <i class="fas fa-trash me-1"></i>削除済み
                  </span>
                  @else
                  <span class="badge bg-success">
                    <i class="fas fa-check me-1"></i>アクティブ
                  </span>
                  @endif
                </td>
                <td>
                  <div class="btn-group" role="group">
                    <a href="{{ route('admin.users.conversations.detail', [$user->id, $conversation->id]) }}"
                      class="btn btn-sm btn-outline-primary" title="詳細を見る">
                      <i class="fas fa-eye"></i>
                    </a>
                    @if($conversation->isDeleted())
                    <form method="POST" action="{{ route('admin.users.conversations.restore', [$user->id, $conversation->id]) }}" class="d-inline">
                      @csrf
                      <button type="submit"
                        class="btn btn-sm btn-outline-success"
                        title="削除を取り消し"
                        onclick="return confirm('この会話の削除を取り消しますか？')">
                        <i class="fas fa-undo"></i>
                      </button>
                    </form>
                    @else
                    <button type="button"
                      class="btn btn-sm btn-outline-danger"
                      title="会話を削除"
                      onclick="showDeleteConversationModal({{ $conversation->id }}, '{{ $user->id }}')">
                      <i class="fas fa-trash"></i>
                    </button>
                    @endif
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <!-- ページネーション -->
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted">
            {{ $conversations->firstItem() }}〜{{ $conversations->lastItem() }}件目 / 全{{ $conversations->total() }}件
          </div>
          <div>
            {{ $conversations->links() }}
          </div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-comments fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">参加している会話がありません</h5>
          <p class="text-muted">このユーザーはまだ会話に参加していません。</p>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- 会話削除確認モーダル -->
<div class="modal fade" id="deleteConversationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">会話削除確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="deleteConversationForm" method="POST">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告:</strong> この操作により、会話が論理削除され、
            参加者全員から見えなくなります。この操作は慎重に行ってください。
          </div>
          <div class="mb-3">
            <label for="deleteConversationReason" class="form-label">削除理由</label>
            <textarea class="form-control" id="deleteConversationReason" name="reason" rows="3"
              placeholder="削除理由を入力してください"></textarea>
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
  function showDeleteConversationModal(conversationId, userId) {
    document.getElementById('deleteConversationForm').action = `/admin/users/${userId}/conversations/${conversationId}`;
    document.getElementById('deleteConversationReason').value = '';
    new bootstrap.Modal(document.getElementById('deleteConversationModal')).show();
  }
</script>
@endsection