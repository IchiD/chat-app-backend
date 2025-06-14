@extends('admin.layouts.app')

@section('title', 'ユーザー編集')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-user-edit me-2"></i>ユーザー編集
        </h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">ダッシュボード</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">ユーザー管理</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users.show', $user->id) }}">{{ $user->name }}</a></li>
            <li class="breadcrumb-item active">編集</li>
          </ol>
        </nav>
      </div>
      <div>
        <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>戻る
        </a>
      </div>
    </div>
  </div>
</div>

@if($user->isDeleted())
<div class="row mb-4">
  <div class="col-12">
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <strong>注意:</strong> このユーザーは削除されています。削除を取り消してから編集してください。
    </div>
  </div>
</div>
@endif

<!-- エラー表示 -->
@if ($errors->any())
<div class="row mb-4">
  <div class="col-12">
    <div class="alert alert-danger">
      <h6>入力エラーがあります:</h6>
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  </div>
</div>
@endif

<!-- 編集フォーム -->
<div class="row">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-edit me-2"></i>基本情報編集
        </h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.users.update', $user->id) }}">
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
                  value="{{ old('name', $user->name) }}"
                  required
                  {{ $user->isDeleted() ? 'disabled' : '' }}>
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
                  value="{{ old('email', $user->email) }}"
                  required
                  {{ $user->isDeleted() ? 'disabled' : '' }}>
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="friend_id" class="form-label">フレンドID <span class="text-danger">*</span></label>
                <input type="text"
                  class="form-control @error('friend_id') is-invalid @enderror"
                  id="friend_id"
                  name="friend_id"
                  value="{{ old('friend_id', $user->friend_id) }}"
                  required
                  maxlength="6"
                  style="font-family: monospace;"
                  {{ $user->isDeleted() ? 'disabled' : '' }}>
                @error('friend_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">6桁の英数字。大文字に自動変換されます。</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">認証状態</label>
                <div class="form-check">
                  <input class="form-check-input"
                    type="checkbox"
                    id="is_verified"
                    name="is_verified"
                    value="1"
                    {{ old('is_verified', $user->is_verified) ? 'checked' : '' }}
                    {{ $user->isDeleted() ? 'disabled' : '' }}>
                  <label class="form-check-label" for="is_verified">
                    メールアドレス認証済み
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="plan" class="form-label">プラン <span class="text-danger">*</span></label>
                <select class="form-select @error('plan') is-invalid @enderror"
                  id="plan"
                  name="plan"
                  required
                  {{ $user->isDeleted() ? 'disabled' : '' }}>
                  <option value="free" {{ old('plan', $user->plan) == 'free' ? 'selected' : '' }}>
                    <i class="fas fa-gift me-1"></i>Free（無料プラン）
                  </option>
                  <option value="standard" {{ old('plan', $user->plan) == 'standard' ? 'selected' : '' }}>
                    <i class="fas fa-star me-1"></i>Standard（スタンダードプラン）
                  </option>
                  <option value="premium" {{ old('plan', $user->plan) == 'premium' ? 'selected' : '' }}>
                    <i class="fas fa-crown me-1"></i>Premium（プレミアムプラン）
                  </option>
                </select>
                @error('plan')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">ユーザーのプランを変更できます。</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">再登録設定</label>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="allow_re_registration" name="allow_re_registration"
                    value="1"
                    {{ old('allow_re_registration', $user->allow_re_registration) ? 'checked' : '' }}
                    {{ $user->isDeleted() ? 'disabled' : '' }}>
                  <label class="form-check-label" for="allow_re_registration">
                    同じメールアドレスでの再登録を許可する
                  </label>
                </div>
                <div class="form-text">
                  許可する場合、ユーザー削除時にメールアドレスが変更され、同じメールアドレスでの新規登録が可能になります。<br>
                  禁止する場合、メールアドレスは保持され、同じメールアドレスでの新規登録はできません。
                </div>
              </div>
            </div>
          </div>

          @if(!$user->isDeleted())
          <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>保存
            </button>
            <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-outline-secondary">
              キャンセル
            </a>
          </div>
          @endif
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
          <label class="form-label text-muted">ユーザーID</label>
          <div class="fw-bold">#{{ $user->id }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted">登録日時</label>
          <div>{{ $user->created_at->format('Y年m月d日 H:i') }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted">登録方法</label>
          <div>
            @if($user->social_type === 'google')
            <span class="badge bg-danger">
              <i class="fab fa-google me-1"></i>Googleアカウント
            </span>
            @else
            <span class="badge bg-primary">
              <i class="fas fa-envelope me-1"></i>メール認証
            </span>
            @endif
          </div>
        </div>
        @if($user->email_verified_at)
        <div class="mb-3">
          <label class="form-label text-muted">認証日時</label>
          <div>{{ $user->email_verified_at->format('Y年m月d日 H:i') }}</div>
        </div>
        @endif
        <div class="mb-3">
          <label class="form-label text-muted">現在のプラン</label>
          <div>
            @switch($user->plan)
            @case('free')
            <span class="badge bg-success">
              <i class="fas fa-gift me-1"></i>Free
            </span>
            @break
            @case('standard')
            <span class="badge bg-primary">
              <i class="fas fa-star me-1"></i>Standard
            </span>
            @break
            @case('premium')
            <span class="badge bg-warning text-dark">
              <i class="fas fa-crown me-1"></i>Premium
            </span>
            @break
            @default
            <span class="badge bg-secondary">{{ $user->plan ?? '不明' }}</span>
            @endswitch
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted">最終更新</label>
          <div>{{ $user->updated_at->format('Y年m月d日 H:i') }}</div>
        </div>

        @if($user->isDeleted())
        <hr>
        <div class="mb-3">
          <label class="form-label text-muted">削除情報</label>
          <div class="text-danger">
            <i class="fas fa-trash me-1"></i>削除済み<br>
            <small>{{ $user->deleted_at->format('Y年m月d日 H:i') }}</small>
            @if($user->deletedByAdmin)
            <br><small>削除者: {{ $user->deletedByAdmin->name }}</small>
            @endif
            @if($user->deleted_reason)
            <br><small>理由: {{ $user->deleted_reason }}</small>
            @endif
          </div>
        </div>
        @endif
      </div>
    </div>

    <!-- 統計情報 -->
    <div class="card mt-3">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-chart-bar me-2"></i>統計情報
        </h5>
      </div>
      <div class="card-body">
        <div class="row text-center">
          <div class="col-6 mb-2">
            <div class="fs-4 fw-bold text-primary">{{ $user->chatRooms()->count() }}</div>
            <div class="small text-muted">参加チャット数</div>
          </div>
          <div class="col-6 mb-2">
            <div class="fs-4 fw-bold text-success">{{ $user->messages()->count() }}</div>
            <div class="small text-muted">送信メッセージ数</div>
          </div>
          <div class="col-6">
            <div class="fs-4 fw-bold text-info">{{ $user->friends()->count() }}</div>
            <div class="small text-muted">友達数</div>
          </div>
          <div class="col-6">
            <div class="fs-4 fw-bold text-warning">
              {{ $user->sentFriendships()->where('status', 'pending')->count() }}
            </div>
            <div class="small text-muted">申請中</div>
          </div>
        </div>
      </div>
    </div>

    @if($user->isDeleted())
    <!-- 削除取り消し -->
    <div class="card mt-3">
      <div class="card-header">
        <h5 class="card-title mb-0 text-warning">
          <i class="fas fa-exclamation-triangle me-2"></i>削除操作
        </h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">このユーザーは削除されています。編集を行うには、まず削除を取り消してください。</p>
        <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
          @csrf
          <button type="submit" class="btn btn-success w-100"
            onclick="return confirm('ユーザーの削除を取り消しますか？')">
            <i class="fas fa-undo me-1"></i>削除取り消し
          </button>
        </form>
      </div>
    </div>
    @else
    <!-- ユーザー削除 -->
    <div class="card mt-3 border-secondary">
      <div class="card-header bg-secondary text-white">
        <h5 class="card-title mb-0">
          <i class="fas fa-user-minus me-2"></i>ユーザー削除
        </h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">ユーザーを論理削除します。削除後も「削除取り消し」機能で復元可能です。</p>
        <button type="button" class="btn btn-danger w-100"
          onclick="showDeleteModal({{ $user->id }}, '{{ $user->name }}')">
          <i class="fas fa-trash me-1"></i>ユーザーを削除
        </button>
      </div>
    </div>
    @endif
  </div>
</div>

@if(!$user->isDeleted())
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
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>確認:</strong> ユーザー「<span id="deleteUserName"></span>」を削除します。<br>
            <small class="text-muted">※ 論理削除のため、後から復元することができます。削除後は同じメールアドレスでの再登録ができなくなります。</small>
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

  // フレンドIDを大文字に変換
  document.getElementById('friend_id').addEventListener('input', function(e) {
    e.target.value = e.target.value.toUpperCase();
  });
</script>
@endif
@endsection