@extends('admin.layouts.app')

@section('title', 'ユーザー管理')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-users me-2"></i>ユーザー管理
        </h1>
        <p class="text-muted">システムに登録されているユーザーの管理</p>
      </div>
      <div>
        <span class="badge bg-primary fs-6">総ユーザー数: {{ $users->total() }}名</span>
      </div>
    </div>
  </div>
</div>

<!-- 検索・フィルター -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('admin.users') }}">
          <div class="row">
            <div class="col-md-3">
              <label for="search" class="form-label">検索</label>
              <input type="text"
                class="form-control"
                id="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="名前・メールアドレスで検索">
            </div>
            <div class="col-md-3">
              <label for="status" class="form-label">認証状態</label>
              <select class="form-select" id="status" name="status">
                <option value="">全て</option>
                <option value="verified" {{ request('status') == 'verified' ? 'selected' : '' }}>認証済み</option>
                <option value="unverified" {{ request('status') == 'unverified' ? 'selected' : '' }}>未認証</option>
                <option value="deleted" {{ request('status') == 'deleted' ? 'selected' : '' }}>削除済み</option>
                <option value="banned" {{ request('status') == 'banned' ? 'selected' : '' }}>バン済み</option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="sort" class="form-label">並び順</label>
              <select class="form-select" id="sort" name="sort">
                <option value="created_at_desc" {{ request('sort') == 'created_at_desc' ? 'selected' : '' }}>登録日（新しい順）</option>
                <option value="created_at_asc" {{ request('sort') == 'created_at_asc' ? 'selected' : '' }}>登録日（古い順）</option>
                <option value="name_asc" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>名前（昇順）</option>
                <option value="name_desc" {{ request('sort') == 'name_desc' ? 'selected' : '' }}>名前（降順）</option>
              </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <button type="submit" class="btn btn-primary me-2">
                <i class="fas fa-search me-1"></i>検索
              </button>
              <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary">
                <i class="fas fa-undo me-1"></i>リセット
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ユーザー一覧 -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-list me-2"></i>ユーザー一覧
        </h5>
      </div>
      <div class="card-body">
        @if($users->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>プロフィール</th>
                <th>メールアドレス</th>
                <th>認証状態</th>
                <th>フレンドID</th>
                <th>登録日</th>
                <th>削除情報</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($users as $user)
              <tr class="{{ $user->isDeleted() ? 'table-warning' : '' }}">
                <td>
                  <span class="fw-bold text-primary">#{{ $user->id }}</span>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                      <div class="fw-bold">{{ $user->name }}</div>
                      <small class="text-muted">ID: {{ $user->id }}</small>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="text-muted">{{ $user->email }}</span>
                </td>
                <td>
                  @if($user->is_verified)
                  <span class="badge badge-verified">
                    <i class="fas fa-check-circle me-1"></i>認証済み
                  </span>
                  @else
                  <span class="badge badge-unverified">
                    <i class="fas fa-exclamation-circle me-1"></i>未認証
                  </span>
                  @endif

                  @if($user->isBanned())
                  <br><span class="badge badge-banned mt-1">
                    <i class="fas fa-ban me-1"></i>バン済み
                  </span>
                  @endif
                </td>
                <td>
                  <code class="bg-light p-1 rounded">{{ $user->friend_id }}</code>
                </td>
                <td>
                  <div>{{ $user->created_at->format('Y/m/d') }}</div>
                  <small class="text-muted">{{ $user->created_at->format('H:i') }}</small>
                </td>
                <td>
                  @if($user->isDeleted())
                  <div class="{{ $user->isDeletedBySelf() ? 'text-warning' : 'text-danger' }}">
                    <i class="fas fa-trash me-1"></i>
                    <strong>
                      @if($user->isDeletedBySelf())
                      ユーザー自身で削除
                      @else
                      管理側で削除
                      @endif
                    </strong>
                  </div>
                  <small class="text-muted">
                    {{ $user->deleted_at->format('Y/m/d H:i') }}
                    @if($user->deletedByAdmin)
                    <br>削除者: {{ $user->deletedByAdmin->name }}
                    @endif
                  </small>
                  @else
                  <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                      type="button"
                      data-bs-toggle="dropdown">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu">
                      <li>
                        <a class="dropdown-item" href="{{ route('admin.users.show', $user->id) }}">
                          <i class="fas fa-eye me-2"></i>詳細を見る
                        </a>
                      </li>
                      @if(!$user->isDeleted())
                      <li>
                        <a class="dropdown-item" href="{{ route('admin.users.edit', $user->id) }}">
                          <i class="fas fa-edit me-2"></i>編集
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item" href="{{ route('admin.users.conversations', $user->id) }}">
                          <i class="fas fa-comments me-2"></i>チャット管理
                        </a>
                      </li>
                      <li>
                        <hr class="dropdown-divider">
                      </li>
                      <li>
                        <a class="dropdown-item text-danger" href="#"
                          onclick="showDeleteModal({{ $user->id }}, '{{ $user->name }}')">
                          <i class="fas fa-trash me-2"></i>削除
                        </a>
                      </li>
                      @else
                      <li>
                        <form method="POST" action="{{ route('admin.users.restore', $user->id) }}" class="d-inline">
                          @csrf
                          <button type="submit" class="dropdown-item text-success"
                            onclick="return confirm('ユーザーの削除を取り消しますか？')">
                            <i class="fas fa-undo me-2"></i>削除取り消し
                          </button>
                        </form>
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
            {{ $users->firstItem() }}〜{{ $users->lastItem() }}件目 / 全{{ $users->total() }}件
          </div>
          <div>
            {{ $users->links() }}
          </div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-users fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">ユーザーが見つかりません</h5>
          <p class="text-muted">検索条件を変更してください</p>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- 削除確認モーダル -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">ユーザー削除確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="deleteForm" method="POST">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告:</strong> この操作により、ユーザー「<span id="deleteUserName"></span>」が論理削除され、
            同じメールアドレスでの再登録ができなくなります。
          </div>
          <div class="mb-3">
            <label for="deleteReason" class="form-label">削除理由</label>
            <textarea class="form-control" id="deleteReason" name="reason" rows="3"
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
  function showDeleteModal(userId, userName) {
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteForm').action = `/admin/users/${userId}`;
    document.getElementById('deleteReason').value = '';
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
  }
</script>
@endsection