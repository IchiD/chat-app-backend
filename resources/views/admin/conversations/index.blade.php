@extends('admin.layouts.app')

@section('title', 'チャットルーム管理')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-comments me-2"></i>チャットルーム管理
        </h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">ダッシュボード</a></li>
            <li class="breadcrumb-item active">チャットルーム</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('admin.conversations') }}" class="row g-3">
          <div class="col-md-8">
            <label for="search" class="form-label">検索</label>
            <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="ルームトークン、会話内容、ユーザー名">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">
              <i class="fas fa-search me-1"></i>検索
            </button>
            <a href="{{ route('admin.conversations') }}" class="btn btn-secondary">
              <i class="fas fa-times me-1"></i>クリア
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">チャットルーム一覧 ({{ $chatRooms->total() }}件)</h5>
      </div>
      <div class="card-body">
        @if($chatRooms->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>チャットルームID</th>
                <th>タイプ</th>
                <th>room_token</th>
                <th>参加者/グループ</th>
                <th>最新メッセージ</th>
                <th>作成日時</th>
                <th>最終更新</th>
                <th>メッセージ数</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($chatRooms as $chatRoom)
              <tr>
                <td>#{{ $chatRoom->id }}</td>
                <td>
                  @switch($chatRoom->type)
                  @case('friend_chat')
                  <span class="badge bg-primary">友達チャット</span>
                  @break
                  @case('group_chat')
                  <span class="badge bg-success">グループチャット</span>
                  @break
                  @case('member_chat')
                  <span class="badge bg-info">メンバーチャット</span>
                  @break
                  @case('support_chat')
                  <span class="badge bg-warning">サポートチャット</span>
                  @break
                  @default
                  <span class="badge bg-secondary">{{ $chatRoom->type }}</span>
                  @endswitch
                </td>
                <td><code>{{ $chatRoom->room_token }}</code></td>
                <td>
                  @if($chatRoom->type === 'group_chat' && $chatRoom->group)
                  <strong>{{ $chatRoom->group->name }}</strong>
                  <br><small class="text-muted">グループID: {{ $chatRoom->group->id }}</small>
                  @elseif($chatRoom->type === 'friend_chat' || $chatRoom->type === 'member_chat')
                  @if($chatRoom->participant1 && $chatRoom->participant2)
                  <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-light text-dark border">{{ $chatRoom->participant1->name }}</span>
                    <span class="badge bg-light text-dark border">{{ $chatRoom->participant2->name }}</span>
                  </div>
                  @else
                  <span class="text-muted">参加者情報不明</span>
                  @endif
                  @else
                  <span class="text-muted">サポート</span>
                  @endif
                </td>
                <td>
                  @if($chatRoom->latestMessage && $chatRoom->latestMessage->sender)
                  <div class="text-truncate" style="max-width: 200px;">
                    <strong>{{ $chatRoom->latestMessage->sender->name ?? 'ユーザー' }}:</strong>
                    {{ $chatRoom->latestMessage->text_content }}
                  </div>
                  <small class="text-muted">{{ $chatRoom->latestMessage->sent_at->format('m/d H:i') }}</small>
                  @else
                  <span class="text-muted">メッセージなし</span>
                  @endif
                </td>
                <td>{{ $chatRoom->created_at->format('Y/m/d H:i') }}</td>
                <td>{{ $chatRoom->updated_at->format('Y/m/d H:i') }}</td>
                <td><span class="badge bg-info">{{ $chatRoom->messages_count ?? 0 }}</span></td>
                <td>
                  <div class="btn-group" role="group">
                    <a href="{{ route('admin.conversations.detail', $chatRoom->id) }}" class="btn btn-sm btn-outline-primary" title="詳細を見る">
                      <i class="fas fa-eye"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-danger" title="チャットルームを削除" onclick="showDeleteConversationModal({{ $chatRoom->id }})">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted">{{ $chatRooms->firstItem() }}〜{{ $chatRooms->lastItem() }}件目 / 全{{ $chatRooms->total() }}件</div>
          <div>{{ $chatRooms->links() }}</div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-comments fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">チャットルームがありません</h5>
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
      <form id="deleteConversationForm" method="POST">
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
  function showDeleteConversationModal(id) {
    document.getElementById('deleteConversationForm').action = `/admin/conversations/${id}`;
    document.getElementById('deleteConversationReason').value = '';
    new bootstrap.Modal(document.getElementById('deleteConversationModal')).show();
  }
</script>
@endsection