@extends('admin.layouts.app')

@section('title', 'アドミン編集 - 管理画面')

@section('content')
<div class="container-fluid">
  <!-- ページヘッダー -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
          <i class="fas fa-user-edit me-2"></i>管理者編集
        </h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a href="{{ route('admin.dashboard') }}">ダッシュボード</a>
            </li>
            <li class="breadcrumb-item">
              <a href="{{ route('admin.admins') }}">管理者一覧</a>
            </li>
            <li class="breadcrumb-item active">編集</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>

  <!-- 編集フォーム -->
  <div class="row">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">
            <i class="fas fa-edit me-2"></i>アドミン情報編集
          </h5>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.admins.update', $editAdmin->id) }}">
            @csrf
            @method('PUT')

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="name" class="form-label">名前 <span class="text-danger">*</span></label>
                  <input type="text"
                    class="form-control @error('name') is-invalid @enderror"
                    id="name"
                    name="name"
                    value="{{ old('name', $editAdmin->name) }}"
                    required>
                  @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                  <input type="email"
                    class="form-control @error('email') is-invalid @enderror"
                    id="email"
                    name="email"
                    value="{{ old('email', $editAdmin->email) }}"
                    required>
                  @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="role" class="form-label">権限レベル <span class="text-danger">*</span></label>
                  <select class="form-select @error('role') is-invalid @enderror"
                    id="role"
                    name="role"
                    required>
                    <option value="">選択してください</option>
                    <option value="admin" {{ old('role', $editAdmin->role) == 'admin' ? 'selected' : '' }}>アドミン</option>
                    <option value="super_admin" {{ old('role', $editAdmin->role) == 'super_admin' ? 'selected' : '' }}>スーパーアドミン</option>
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
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="password" class="form-label">パスワード</label>
                  <input type="password"
                    class="form-control @error('password') is-invalid @enderror"
                    id="password"
                    name="password"
                    placeholder="変更する場合のみ入力">
                  @error('password')
                  <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <div class="form-text">8文字以上で設定してください（変更しない場合は空白のまま）</div>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-between">
              <a href="{{ route('admin.admins') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>戻る
              </a>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>更新
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <!-- 現在の情報 -->
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">
            <i class="fas fa-info-circle me-2"></i>現在の情報
          </h5>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label text-muted">アドミンID</label>
            <div class="fw-bold">#{{ $editAdmin->id }}</div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted">現在の権限</label>
            <div>
              @if($editAdmin->role === 'super_admin')
              <span class="badge badge-superadmin">
                <i class="fas fa-crown me-1"></i>スーパーアドミン
              </span>
              @else
              <span class="badge badge-admin">
                <i class="fas fa-user-cog me-1"></i>アドミン
              </span>
              @endif
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted">作成日時</label>
            <div>{{ $editAdmin->created_at->format('Y年m月d日 H:i') }}</div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted">最終更新</label>
            <div>{{ $editAdmin->updated_at->format('Y年m月d日 H:i') }}</div>
          </div>
        </div>
      </div>

      <!-- 注意事項 -->
      <div class="card mt-3">
        <div class="card-header">
          <h5 class="card-title mb-0 text-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>注意事項
          </h5>
        </div>
        <div class="card-body">
          <ul class="text-muted mb-0">
            <li>メールアドレスは他のアドミンと重複できません</li>
            <li>パスワードは8文字以上で設定してください</li>
            <li>変更しない場合、パスワード欄は空白のままにしてください</li>
            <li>権限レベルの変更は慎重に行ってください</li>
          </ul>
        </div>
      </div>

      <!-- 危険な操作 -->
      <div class="card mt-3 border-danger">
        <div class="card-header bg-danger text-white">
          <h5 class="card-title mb-0">
            <i class="fas fa-exclamation-triangle me-2"></i>危険な操作
          </h5>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">この操作は取り消しができません。慎重に行ってください。</p>
          <button type="button" class="btn btn-danger w-100"
            onclick="showDeleteAdminModal({{ $editAdmin->id }}, '{{ $editAdmin->name }}')">
            <i class="fas fa-trash me-1"></i>アドミンを削除
          </button>
        </div>
      </div>
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
<script>
  function showDeleteAdminModal(adminId, adminName) {
    document.getElementById('deleteAdminName').textContent = adminName;
    document.getElementById('deleteAdminForm').action = `/admin/admins/${adminId}`;
    document.getElementById('deleteReason').value = '';
    new bootstrap.Modal(document.getElementById('deleteAdminModal')).show();
  }
</script>
@endsection