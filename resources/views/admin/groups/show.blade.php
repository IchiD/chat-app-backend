@extends('admin.layouts.app')

@section('title', 'グループ詳細')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.groups') }}">グループ</a></li>
        <li class="breadcrumb-item active">#{{ $group->id }}</li>
      </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="h3 mb-0">
        <i class="fas fa-users-gear me-2"></i>{{ $group->name }}
      </h1>
      <div>
        <a href="{{ route('admin.groups.edit', $group->id) }}" class="btn btn-outline-primary">
          <i class="fas fa-edit me-1"></i>編集
        </a>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">基本情報</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">ID</dt>
          <dd class="col-sm-8">#{{ $group->id }}</dd>

          <dt class="col-sm-4">オーナー</dt>
          <dd class="col-sm-8">{{ $group->owner->name ?? '-' }}</dd>

          <dt class="col-sm-4">メンバー上限</dt>
          <dd class="col-sm-8">{{ $group->max_members }}</dd>

          <dt class="col-sm-4">スタイル</dt>
          <dd class="col-sm-8">
            {{ is_array($group->chat_styles) ? implode(', ', $group->chat_styles) : ($group->chat_styles ?? '-') }}
          </dd>

          <dt class="col-sm-4">説明</dt>
          <dd class="col-sm-8">{!! nl2br(e($group->description)) !!}</dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <span>メンバー ({{ $group->getMembersCount() }}名)</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="showAddMemberModal()">
          <i class="fas fa-plus me-1"></i>メンバー追加
        </button>
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead>
            <tr>
              <th style="width: 60px;">ID</th>
              <th>名前</th>
              <th>役割</th>
              <th>参加日</th>
              <th style="width: 100px;">操作</th>
            </tr>
          </thead>
          <tbody>
            @if($group->activeMembers->count() > 0)
            @foreach($group->activeMembers as $member)
            <tr>
              <td>#{{ $member->user_id }}</td>
              <td>{{ $member->user->name ?? '-' }}</td>
              <td>
                @if($member->role === 'owner')
                <span class="badge bg-warning">オーナー</span>
                @elseif($member->role === 'admin')
                <span class="badge bg-info">管理者</span>
                @else
                <span class="badge bg-secondary">メンバー</span>
                @endif
              </td>
              <td>{{ optional($member->joined_at)->format('Y/m/d') }}</td>
              <td>
                @if($member->role !== 'owner')
                <div class="btn-group" role="group">
                  <button type="button" class="btn btn-sm btn-outline-warning" onclick="showRoleModal({{ $member->user_id }}, '{{ $member->role }}', '{{ addslashes($member->user->name ?? 'ユーザー') }}')">
                    <i class="fas fa-user-cog"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="showRemoveMemberModal({{ $member->user_id }}, '{{ addslashes($member->user->name ?? 'ユーザー') }}')">
                    <i class="fas fa-user-minus"></i>
                  </button>
                </div>
                @else
                <span class="text-muted">-</span>
                @endif
              </td>
            </tr>
            @endforeach
            @else
            <tr>
              <td colspan="5" class="text-center text-muted">
                アクティブなメンバーがいません
              </td>
            </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- メンバー追加モーダル -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">メンバー追加</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.groups.members.add', $group->id) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label for="user_id" class="form-label">ユーザーID</label>
            <input type="number" class="form-control" id="user_id" name="user_id" required placeholder="追加するユーザーのIDを入力">
            <small class="text-muted">ユーザー管理画面でユーザーIDを確認できます</small>
          </div>
          <div class="mb-3">
            <label for="role" class="form-label">役割</label>
            <select class="form-select" id="role" name="role" required>
              <option value="member">メンバー</option>
              <option value="admin">管理者</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-success">追加</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- メンバー削除モーダル -->
<div class="modal fade" id="removeMemberModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">メンバー削除確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="removeMemberForm" method="POST">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告:</strong> この操作により、メンバーがグループから削除されます。
          </div>
          <p id="removeMemberMessage"></p>
          <div class="mb-3">
            <label for="removeReason" class="form-label">削除理由</label>
            <textarea class="form-control" id="removeReason" name="reason" rows="3" placeholder="削除理由を入力してください"></textarea>
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

<!-- 役割変更モーダル -->
<div class="modal fade" id="roleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">役割変更</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="roleForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <p id="roleMessage"></p>
          <div class="mb-3">
            <label for="newRole" class="form-label">新しい役割</label>
            <select class="form-select" id="newRole" name="role" required>
              <option value="member">メンバー</option>
              <option value="admin">管理者</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-primary">変更</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function showAddMemberModal() {
    new bootstrap.Modal(document.getElementById('addMemberModal')).show();
  }

  function showRemoveMemberModal(userId, userName) {
    const modal = document.getElementById('removeMemberModal');
    const form = document.getElementById('removeMemberForm');
    const message = document.getElementById('removeMemberMessage');

    if (!modal || !form || !message) {
      return;
    }

    const actionUrl = `{{ route('admin.groups.members.remove', ['groupId' => $group->id, 'memberId' => '__USER_ID__']) }}`.replace('__USER_ID__', userId);

    form.action = actionUrl;
    message.textContent = `「${userName}」をグループから削除しますか？`;

    new bootstrap.Modal(modal).show();
  }

  function showRoleModal(userId, currentRole, userName) {
    const modal = document.getElementById('roleModal');
    const form = document.getElementById('roleForm');
    const message = document.getElementById('roleMessage');
    const roleSelect = document.getElementById('newRole');

    form.action = `{{ route('admin.groups.members.role', ['groupId' => $group->id, 'memberId' => '__USER_ID__']) }}`.replace('__USER_ID__', userId);
    message.textContent = `「${userName}」の役割を変更します。現在の役割: ${currentRole}`;
    roleSelect.value = currentRole;

    new bootstrap.Modal(modal).show();
  }
</script>
@endsection