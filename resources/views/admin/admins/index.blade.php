@extends('admin.layouts.app')

@section('title', 'アドミン管理')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-user-shield me-2"></i>アドミン管理
        </h1>
        <p class="text-muted">システム管理者の管理（スーパーアドミン専用）</p>
      </div>
      <div>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#createAdminModal">
          <i class="fas fa-plus me-2"></i>新規アドミン作成
        </button>
      </div>
    </div>
  </div>
</div>

<!-- アドミン一覧 -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-list me-2"></i>アドミン一覧
          <span class="badge bg-danger ms-2">{{ $admins->total() }}名</span>
        </h5>
      </div>
      <div class="card-body">
        @if($admins->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>プロフィール</th>
                <th>メールアドレス</th>
                <th>権限レベル</th>
                <th>作成日</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($admins as $adminUser)
              <tr>
                <td>
                  <span class="fw-bold text-danger">#{{ $adminUser->id }}</span>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                      <div class="fw-bold">{{ $adminUser->name }}</div>
                      <small class="text-muted">ID: {{ $adminUser->id }}</small>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="text-muted">{{ $adminUser->email }}</span>
                </td>
                <td>
                  @if($adminUser->role === 'super_admin')
                  <span class="badge badge-superadmin">
                    <i class="fas fa-crown me-1"></i>スーパーアドミン
                  </span>
                  @else
                  <span class="badge badge-admin">
                    <i class="fas fa-user-cog me-1"></i>アドミン
                  </span>
                  @endif
                </td>
                <td>
                  <div>{{ $adminUser->created_at->format('Y/m/d') }}</div>
                  <small class="text-muted">{{ $adminUser->created_at->format('H:i') }}</small>
                </td>
                <td>
                  @if($adminUser->id !== auth('admin')->id())
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                      type="button"
                      data-bs-toggle="dropdown">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu">
                      <li>
                        <a class="dropdown-item" href="{{ route('admin.admins.edit', $adminUser->id) }}">
                          <i class="fas fa-edit me-2"></i>編集
                        </a>
                      </li>
                      <li>
                        <hr class="dropdown-divider">
                      </li>
                      <li>
                        <a class="dropdown-item text-danger" href="#"
                          onclick="showDeleteAdminModal({{ $adminUser->id }}, '{{ $adminUser->name }}')">
                          <i class="fas fa-trash me-2"></i>削除
                        </a>
                      </li>
                    </ul>
                  </div>
                  @else
                  <span class="badge badge-loggedin">
                    <i class="fas fa-user me-1"></i>ログイン中
                  </span>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <!-- ページネーション -->
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted">
            {{ $admins->firstItem() }}〜{{ $admins->lastItem() }}件目 / 全{{ $admins->total() }}件
          </div>
          <div>
            {{ $admins->links() }}
          </div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-user-shield fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">アドミンが見つかりません</h5>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- 新規アドミン作成モーダル -->
<div class="modal fade" id="createAdminModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-plus me-2"></i>新規アドミン作成
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.admins.create') }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label for="name" class="form-label">名前 <span class="text-danger">*</span></label>
            <input type="text"
              class="form-control @error('name') is-invalid @enderror"
              id="name"
              name="name"
              value="{{ old('name') }}"
              required>
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label for="email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
            <input type="email"
              class="form-control @error('email') is-invalid @enderror"
              id="email"
              name="email"
              value="{{ old('email') }}"
              required>
            @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label for="password" class="form-label">パスワード <span class="text-danger">*</span></label>
            <input type="password"
              class="form-control @error('password') is-invalid @enderror"
              id="password"
              name="password"
              required>
            @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">8文字以上で設定してください</div>
          </div>

          <div class="mb-3">
            <label for="role" class="form-label">権限レベル <span class="text-danger">*</span></label>
            <select class="form-select @error('role') is-invalid @enderror"
              id="role"
              name="role"
              required>
              <option value="">選択してください</option>
              <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>アドミン</option>
              <option value="super_admin" {{ old('role') == 'super_admin' ? 'selected' : '' }}>スーパーアドミン</option>
            </select>
            @error('role')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
              <strong>アドミン:</strong> 基本的な管理機能<br>
              <strong>スーパーアドミン:</strong> 全ての管理機能（アドミン管理含む）
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>キャンセル
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-plus me-1"></i>作成
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- アドミン削除確認モーダル -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">アドミン削除確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="deleteAdminForm" method="POST">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告:</strong> この操作により、アドミン「<span id="deleteAdminName"></span>」が完全に削除されます。
            この操作は取り消すことができません。
          </div>
          <div class="mb-3">
            <label for="deleteReason" class="form-label">削除理由 <span class="text-danger">*</span></label>
            <textarea class="form-control" id="deleteReason" name="reason" rows="3"
              placeholder="削除理由を入力してください" required></textarea>
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
@endsection

@section('scripts')
@if($errors->any())
<script>
  // エラーがある場合はモーダルを開く
  var createModal = new bootstrap.Modal(document.getElementById('createAdminModal'));
  createModal.show();
</script>
@endif

<script>
  function showDeleteAdminModal(adminId, adminName) {
    document.getElementById('deleteAdminName').textContent = adminName;
    document.getElementById('deleteAdminForm').action = `/admin/admins/${adminId}`;
    document.getElementById('deleteReason').value = '';
    new bootstrap.Modal(document.getElementById('deleteAdminModal')).show();
  }
</script>
@endsection