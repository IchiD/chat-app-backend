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
          <div class="col-md-3">
            <label for="search_type" class="form-label">検索タイプ</label>
            <select class="form-select" id="search_type" name="search_type">
              <option value="all" {{ request('search_type', 'all') === 'all' ? 'selected' : '' }}>すべて</option>
              <option value="room_token" {{ request('search_type') === 'room_token' ? 'selected' : '' }}>ルームトークンのみ</option>
              <option value="id" {{ request('search_type') === 'id' ? 'selected' : '' }}>IDのみ</option>
              <option value="messages" {{ request('search_type') === 'messages' ? 'selected' : '' }}>メッセージ内容のみ</option>
              <option value="participants" {{ request('search_type') === 'participants' ? 'selected' : '' }}>参加者のみ</option>
            </select>
          </div>
          <div class="col-md-5">
            <label for="search" class="form-label">検索キーワード</label>
            <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="検索キーワードを入力">
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
                <th style="width: 80px;">ID</th>
                <th style="width: 120px;">タイプ</th>
                <th style="width: 140px;">ルームトークン</th>
                <th style="width: 180px;">参加者/グループ</th>
                <th style="width: 250px;">最新メッセージ</th>
                <th style="width: 120px;">作成日時</th>
                <th style="width: 120px;">最終更新</th>
                <th style="width: 70px; white-space: nowrap;">件数</th>
                <th style="width: 100px;">操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($chatRooms as $chatRoom)
              <tr class="{{ $chatRoom->trashed() ? 'table-danger' : '' }}">
                <td>
                  #{{ $chatRoom->id }}
                  @if($chatRoom->trashed())
                  <span class="badge bg-danger ms-1">
                    <i class="fas fa-trash me-1"></i>削除済み
                  </span>
                  @endif
                </td>
                <td>
                  @switch($chatRoom->type)
                  @case('friend_chat')
                  <span class="badge badge-friend-chat">友達チャット</span>
                  @break
                  @case('group_chat')
                  <span class="badge badge-group-chat">グループチャット</span>
                  @break
                  @case('member_chat')
                  <span class="badge badge-member-chat">メンバーチャット</span>
                  @break
                  @case('support_chat')
                  <span class="badge badge-support-chat">サポートチャット</span>
                  @break
                  @default
                  <span class="badge badge-admin">{{ $chatRoom->type }}</span>
                  @endswitch
                </td>
                <td><code>{{ $chatRoom->room_token }}</code></td>
                <td>
                  @if($chatRoom->type === 'group_chat' && $chatRoom->group)
                  <strong>{{ $chatRoom->group->name }}</strong>
                  <br><small class="text-muted">グループID: {{ $chatRoom->group->id }}</small>
                  @elseif($chatRoom->type === 'friend_chat' || $chatRoom->type === 'member_chat')
                  @php
                  $participant1 = $chatRoom->participant1;
                  $participant2 = $chatRoom->participant2;
                  @endphp
                  @if($participant1 || $participant2)
                  <div class="d-flex flex-wrap gap-1">
                    @if($participant1)
                    <span class="badge {{ $participant1->trashed() ? 'bg-secondary' : 'bg-light' }} text-dark border">
                      {{ $participant1->name }}{{ $participant1->trashed() ? ' (削除済み)' : '' }}
                    </span>
                    @endif
                    @if($participant2)
                    <span class="badge {{ $participant2->trashed() ? 'bg-secondary' : 'bg-light' }} text-dark border">
                      {{ $participant2->name }}{{ $participant2->trashed() ? ' (削除済み)' : '' }}
                    </span>
                    @endif
                  </div>
                  @else
                  <span class="text-muted">参加者情報不明</span>
                  @endif
                  @else
                  <span class="text-muted">サポート</span>
                  @endif
                </td>
                <td>
                  @if($chatRoom->latestMessage)
                  <div class="text-truncate message-content" style="max-width: 200px;">
                    <strong>{{ $chatRoom->latestMessage->getSenderDisplayName() }}:</strong>
                    {{ $chatRoom->latestMessage->text_content }}
                  </div>
                  <small class="text-muted">{{ $chatRoom->latestMessage->sent_at->format('m/d H:i') }}</small>
                  @else
                  <span class="text-muted">メッセージなし</span>
                  @endif
                </td>
                <td>{{ $chatRoom->created_at->format('Y/m/d H:i') }}</td>
                <td>
                  {{ $chatRoom->updated_at->format('Y/m/d H:i') }}
                  @if($chatRoom->trashed())
                  <br><small class="text-danger">
                    <i class="fas fa-trash me-1"></i>削除: {{ $chatRoom->deleted_at->format('Y/m/d H:i') }}
                  </small>
                  @endif
                </td>
                <td><span class="badge badge-count">{{ $chatRoom->messages_count ?? 0 }}</span></td>
                <td>
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                      type="button"
                      data-bs-toggle="dropdown">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu">
                      <li>
                        <a class="dropdown-item" href="{{ route('admin.conversations.detail', $chatRoom->id) }}">
                          <i class="fas fa-eye me-2"></i>詳細を見る
                        </a>
                      </li>
                      @if($chatRoom->trashed())
                      <li>
                        <hr class="dropdown-divider">
                      </li>
                      <li>
                        <form method="POST" action="{{ route('admin.conversations.restore', $chatRoom->id) }}" class="d-inline">
                          @csrf
                          <button type="submit" class="dropdown-item text-success"
                            onclick="return confirm('このチャットルームの削除を取り消しますか？')">
                            <i class="fas fa-undo me-2"></i>削除を取り消し
                          </button>
                        </form>
                      </li>
                      @else
                      <li>
                        <hr class="dropdown-divider">
                      </li>
                      <li>
                        <a class="dropdown-item text-danger" href="#"
                          onclick="showDeleteConversationModal({{ $chatRoom->id }})">
                          <i class="fas fa-trash me-2"></i>削除
                        </a>
                      </li>
                      @endif
                    </ul>
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
            @if($chatRooms->total() > 0)
            {{ $chatRooms->firstItem() }}〜{{ $chatRooms->lastItem() }}件目 / 全{{ $chatRooms->total() }}件
            @else
            0件
            @endif
          </div>
          <div>
            {{ $chatRooms->links() }}
          </div>
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
        <h5 class="modal-title">チャット削除確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="deleteConversationForm" method="POST">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告:</strong> この操作により、チャットが論理削除されます。
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