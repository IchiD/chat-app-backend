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
            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">ユーザー管理</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users.show', $user->id) }}">{{ $user->name }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users.conversations', $user->id) }}">チャットルーム管理</a></li>
            <li class="breadcrumb-item active">チャットルーム #{{ $chatRoom->id }}</li>
          </ol>
        </nav>
      </div>
      <div>
        <a href="{{ route('admin.users.conversations', $user->id) }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>チャットルーム一覧に戻る
        </a>
      </div>
    </div>
  </div>
</div>

<!-- チャットルーム情報カード -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card {{ $chatRoom->trashed() ? 'border-danger' : '' }}">
      <div class="card-header {{ $chatRoom->trashed() ? 'bg-danger text-white' : '' }}">
        <h5 class="card-title mb-0">
          <i class="fas fa-info-circle me-2"></i>チャットルーム情報
          @if($chatRoom->trashed())
          <span class="badge bg-light text-danger ms-2">
            <i class="fas fa-trash me-1"></i>削除済み
          </span>
          @endif
        </h5>
        <div class="mt-2">
          @if($chatRoom->trashed())
          <form method="POST" action="{{ route('admin.users.conversations.restore', [$user->id, $chatRoom->id]) }}" class="d-inline">
            @csrf
            <button type="submit"
              class="btn btn-sm btn-outline-light"
              onclick="return confirm('このチャットルームの削除を取り消しますか？')">
              <i class="fas fa-undo me-1"></i>削除を取り消し
            </button>
          </form>
          @else
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="showDeleteConversationModal()">
            <i class="fas fa-trash me-1"></i>チャットルームを削除
          </button>
          @endif
        </div>
      </div>
      <div class="card-body">
        @if($chatRoom->trashed())
        <div class="alert alert-danger mb-3">
          <h6 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>このチャットルームは削除されています
          </h6>
          <hr>
          <div class="row">
            <div class="col-md-6">
              <strong>削除日時:</strong> {{ $chatRoom->deleted_at->format('Y年m月d日 H:i') }}
            </div>
            @if($chatRoom->deletedByAdmin)
            <div class="col-md-6">
              <strong>削除実行者:</strong> {{ $chatRoom->deletedByAdmin->name }} (管理者)
            </div>
            @endif
          </div>
          @if($chatRoom->deleted_reason)
          <div class="mt-2">
            <strong>削除理由:</strong><br>
            <div class="bg-light p-2 rounded mt-1">{{ $chatRoom->deleted_reason }}</div>
          </div>
          @endif
        </div>
        @endif

        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label text-muted">チャットルームID</label>
              <div class="fw-bold">#{{ $chatRoom->id }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">ルームトークン</label>
              <div><code class="bg-light p-2 rounded">{{ $chatRoom->room_token }}</code></div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">チャットタイプ</label>
              <div>
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
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">作成日時</label>
              <div>{{ $chatRoom->created_at->format('Y年m月d日 H:i') }}</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label text-muted">参加者</label>
              <div>
                @if($chatRoom->type === 'group_chat' && $chatRoom->group)
                <strong>{{ $chatRoom->group->name }}</strong>
                <div class="mt-1">
                  <small class="text-muted">グループID: {{ $chatRoom->group->id }}</small>
                  @if($chatRoom->group && $chatRoom->group->activeMembers)
                  <br><small class="text-muted">{{ $chatRoom->group->activeMembers->count() }}人参加</small>
                  @endif
                </div>
                @elseif($chatRoom->type === 'friend_chat' || $chatRoom->type === 'member_chat')
                @if($chatRoom->participant1 && $chatRoom->participant2)
                <div class="d-flex flex-column gap-1">
                  <span class="badge bg-light text-dark border">{{ $chatRoom->participant1->name }}</span>
                  <span class="badge bg-light text-dark border">{{ $chatRoom->participant2->name }}</span>
                </div>
                @else
                <span class="text-muted">参加者情報不明</span>
                @endif
                @else
                <span class="text-muted">サポート</span>
                @endif
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">最終更新</label>
              <div>{{ $chatRoom->updated_at->format('Y年m月d日 H:i') }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">メッセージ数</label>
              <div><span class="badge badge-count">{{ $chatRoom->messages->count() }}</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- メッセージ一覧 -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-envelope me-2"></i>メッセージ一覧
        </h5>
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
                    <div class="fw-bold">
                      {{ $message->getSenderDisplayName() }}
                      @if($message->sender && $message->sender->id == $user->id)
                      <span class="badge bg-primary ms-1">対象ユーザー</span>
                      @endif
                    </div>
                    <small class="text-muted">
                      {{ $message->sent_at->format('Y/m/d H:i:s') }}
                      @if($message->edited_at)
                      <span class="badge bg-info ms-1">編集済み</span>
                      @endif
                    </small>
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
                <div class="text-muted fst-italic">
                  元のメッセージ: "{{ $message->text_content }}"
                </div>
                @elseif($message->deleted_at)
                <div class="alert alert-warning mb-2">
                  <i class="fas fa-exclamation-triangle me-1"></i>
                  <strong>ユーザーにより削除されました</strong>
                  <small class="d-block">削除日時: {{ $message->deleted_at->format('Y/m/d H:i') }}</small>
                </div>
                <div class="text-muted fst-italic">
                  元のメッセージ: "{{ $message->text_content }}"
                </div>
                @else
                <div class="message-content" id="message-content-{{ $message->id }}">
                  {{ $message->text_content }}
                </div>
                <div class="message-edit-form d-none" id="message-edit-{{ $message->id }}">
                  <form method="POST" action="{{ route('admin.users.messages.update', [$user->id, $chatRoom->id, $message->id]) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-2">
                      <textarea class="form-control" name="text_content" rows="3" required>{{ $message->text_content }}</textarea>
                    </div>
                    <div class="d-flex gap-2">
                      <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i>保存
                      </button>
                      <button type="button" class="btn btn-sm btn-secondary" onclick="cancelEdit({{ $message->id }})">
                        キャンセル
                      </button>
                    </div>
                  </form>
                </div>
                @endif
              </div>

              @if(!$message->isAdminDeleted() && !$chatRoom->isDeleted())
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                  type="button"
                  data-bs-toggle="dropdown">
                  <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu">
                  @if(!$message->deleted_at)
                  <li>
                    <button class="dropdown-item" onclick="startEdit({{ $message->id }})">
                      <i class="fas fa-edit me-2"></i>編集
                    </button>
                  </li>
                  @endif
                  <li>
                    <button class="dropdown-item text-danger"
                      onclick="showDeleteMessageModal({{ $message->id }})">
                      <i class="fas fa-trash me-2"></i>削除
                    </button>
                  </li>
                </ul>
              </div>
              @endif
            </div>
          </div>
          @endforeach
        </div>

        <!-- ページネーション -->
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted">
            {{ $messages->firstItem() }}〜{{ $messages->lastItem() }}件目 / 全{{ $messages->total() }}件
          </div>
          <div>
            {{ $messages->links() }}
          </div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">メッセージがありません</h5>
          <p class="text-muted">この会話にはまだメッセージが投稿されていません。</p>
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
      <form method="POST" action="{{ route('admin.users.conversations.delete', [$user->id, $chatRoom->id]) }}">
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
              placeholder="削除理由を入力してください（任意）"></textarea>
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

<!-- メッセージ削除確認モーダル -->
<div class="modal fade" id="deleteMessageModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">メッセージ削除確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="deleteMessageForm" method="POST">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告:</strong> この操作により、メッセージが管理者により削除されます。
          </div>
          <div class="mb-3">
            <label for="deleteMessageReason" class="form-label">削除理由</label>
            <textarea class="form-control" id="deleteMessageReason" name="reason" rows="2"
              placeholder="削除理由を入力してください（任意）"></textarea>
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

  function showDeleteMessageModal(messageId) {
    document.getElementById('deleteMessageForm').action = `/admin/users/{{ $user->id }}/conversations/{{ $chatRoom->id }}/messages/${messageId}`;
    document.getElementById('deleteMessageReason').value = '';
    new bootstrap.Modal(document.getElementById('deleteMessageModal')).show();
  }

  function startEdit(messageId) {
    document.getElementById('message-content-' + messageId).classList.add('d-none');
    document.getElementById('message-edit-' + messageId).classList.remove('d-none');
  }

  function cancelEdit(messageId) {
    document.getElementById('message-content-' + messageId).classList.remove('d-none');
    document.getElementById('message-edit-' + messageId).classList.add('d-none');
  }
</script>

<style>
  .message-item {
    transition: all 0.2s ease;
  }

  .message-item:hover {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .messages-container {
    max-height: 600px;
    overflow-y: auto;
  }
</style>
@endsection