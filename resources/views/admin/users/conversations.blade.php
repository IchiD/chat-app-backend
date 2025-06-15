@extends('admin.layouts.app')

@section('title', 'ユーザーチャット管理')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-comments me-2"></i>チャット管理
        </h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">ダッシュボード</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">ユーザー管理</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users.show', $user->id) }}">{{ $user->name }}</a></li>
            <li class="breadcrumb-item active">チャット管理</li>
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
                <div class="small text-muted">参加チャット数</div>
                <div class="h4 mb-0">{{ $chatRooms->total() }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- チャット一覧 -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-list me-2"></i>参加しているチャットルーム一覧
        </h5>
      </div>
      <div class="card-body">
        @if($chatRooms->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>チャットルームID</th>
                <th>タイプ</th>
                <th>参加者/グループ</th>
                <th>最新メッセージ</th>
                <th>最終更新</th>
                <th>メッセージ数</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($chatRooms as $chatRoom)
              <tr class="{{ $chatRoom->trashed() ? 'table-danger' : '' }}">
                <td>
                  <span class="fw-bold text-primary">#{{ $chatRoom->id }}</span>
                  @if($chatRoom->trashed())
                  <span class="badge bg-danger ms-1">
                    <i class="fas fa-trash me-1"></i>削除済み
                  </span>
                  @endif
                  <br><small class="text-muted">{{ $chatRoom->room_token }}</small>
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
                  <span class="badge bg-secondary">{{ $chatRoom->type }}</span>
                  @endswitch
                </td>
                <td>
                  @if($chatRoom->type === 'group_chat' && $chatRoom->group)
                  <div class="d-flex align-items-center">
                    <strong>{{ $chatRoom->group->name }}</strong>
                  </div>
                  <small class="text-muted">
                    グループID: {{ $chatRoom->group->id }}
                    @if($chatRoom->group && $chatRoom->group->activeMembers)
                    | {{ $chatRoom->group->activeMembers->count() }}人参加
                    @endif
                  </small>
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
                  <small class="text-muted">{{ ($participant1 ? 1 : 0) + ($participant2 ? 1 : 0) }}人参加</small>
                  @else
                  <span class="text-muted">参加者情報不明</span>
                  @endif
                  @else
                  <span class="text-muted">サポート</span>
                  @endif
                </td>
                <td>
                  @if($chatRoom->latestMessage && $chatRoom->latestMessage->sent_at)
                  <div class="text-truncate message-content" style="max-width: 200px;">
                    <strong>{{ $chatRoom->latestMessage->getSenderDisplayName() }}:</strong>
                    {{ $chatRoom->latestMessage->text_content }}
                  </div>
                  <small class="text-muted">
                    {{ $chatRoom->latestMessage->sent_at->format('m/d H:i') }}
                  </small>
                  @else
                  <span class="text-muted">メッセージなし</span>
                  @endif
                </td>
                <td>
                  <div>{{ $chatRoom->updated_at->format('Y/m/d') }}</div>
                  <small class="text-muted">{{ $chatRoom->updated_at->format('H:i') }}</small>
                  @if($chatRoom->trashed())
                  <br><small class="text-danger">
                    <i class="fas fa-trash me-1"></i>削除: {{ $chatRoom->deleted_at->format('Y/m/d H:i') }}
                  </small>
                  @endif
                </td>
                <td>
                  <span class="badge badge-count">{{ $chatRoom->messages->count() }}</span>
                </td>
                <td>
                  <div class="btn-group" role="group">
                    <a href="{{ route('admin.users.conversations.detail', [$user->id, $chatRoom->id]) }}"
                      class="btn btn-sm btn-outline-primary" title="詳細を見る">
                      <i class="fas fa-eye"></i>
                    </a>
                    @if($chatRoom->trashed())
                    <form method="POST" action="{{ route('admin.users.conversations.restore', [$user->id, $chatRoom->id]) }}" class="d-inline">
                      @csrf
                      <button type="submit"
                        class="btn btn-sm btn-outline-success"
                        title="削除を取り消し"
                        onclick="return confirm('このチャットルームの削除を取り消しますか？')">
                        <i class="fas fa-undo"></i>
                      </button>
                    </form>
                    @else
                    <button type="button"
                      class="btn btn-sm btn-outline-danger"
                      title="チャットルームを削除"
                      onclick="showDeleteConversationModal({{ $chatRoom->id }}, '{{ $user->id }}')">
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
          <h5 class="text-muted">参加しているチャットルームがありません</h5>
          <p class="text-muted">このユーザーはまだチャットルームに参加していません。</p>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- チャット削除確認モーダル -->
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
            <strong>警告:</strong> この操作により、チャットが論理削除され、
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