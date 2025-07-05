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
        @if($group->isDeleted())
        <span class="badge bg-danger ms-2">削除済み</span>
        @endif
      </h1>
      <div>
        @if(!$group->isDeleted())
        <a href="{{ route('admin.groups.edit', $group->id) }}" class="btn btn-outline-primary me-2">
          <i class="fas fa-edit me-1"></i>編集
        </a>
        <button class="btn btn-outline-danger" onclick="showDeleteGroupModal()">
          <i class="fas fa-trash me-1"></i>グループ削除
        </button>
        @else
        <button class="btn btn-outline-success" onclick="showRestoreGroupModal()">
          <i class="fas fa-undo me-1"></i>グループ復活
        </button>
        @endif
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
          <dd class="col-sm-8">
            @if($group->owner)
            {{ $group->owner->name }}
            @if($group->owner->deleted_at)
            <span class="badge bg-danger ms-1">削除済み</span>
            @endif
            @else
            <span class="text-muted">-</span>
            @endif
          </dd>

          <dt class="col-sm-4">メンバー上限</dt>
          <dd class="col-sm-8">{{ $group->max_members }}</dd>

          <dt class="col-sm-4">スタイル</dt>
          <dd class="col-sm-8">
            {{ is_array($group->chat_styles) ? implode(', ', $group->chat_styles) : ($group->chat_styles ?? '-') }}
          </dd>

          <dt class="col-sm-4">説明</dt>
          <dd class="col-sm-8">{!! nl2br(e($group->description)) !!}</dd>

          @if($group->isDeleted())
          <dt class="col-sm-4">削除情報</dt>
          <dd class="col-sm-8">
            <div class="text-danger">
              <i class="fas fa-exclamation-triangle me-1"></i>
              削除日: {{ $group->deleted_at ? $group->deleted_at->format('Y/m/d H:i') : '-' }}<br>
              削除者: {{ $group->deletedByAdmin->name ?? '不明' }}<br>
              削除理由: {{ $group->deleted_reason ?? '理由なし' }}
            </div>
          </dd>
          @endif
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
        @if(!$group->isDeleted())
        <button type="button" class="btn btn-sm btn-outline-success" onclick="showAddMemberModal()">
          <i class="fas fa-plus me-1"></i>メンバー追加
        </button>
        @endif
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
              <td>
                {{ $member->user->name ?? '-' }}
                @if($member->user && $member->user->deleted_at)
                <span class="badge bg-danger ms-1">削除済み</span>
                @endif
              </td>
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
                @if($member->role !== 'owner' && !$group->isDeleted())
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

    <!-- 削除済みメンバーセクション -->
    @php
    $deletedMembers = $group->groupMembers->where('left_at', '!=', null);
    @endphp
    @if($deletedMembers->count() > 0)
    <div class="card mb-3">
      <div class="card-header">
        <span>削除済みメンバー ({{ $deletedMembers->count() }}名)</span>
      </div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead>
            <tr>
              <th style="width: 60px;">ID</th>
              <th>名前</th>
              <th>削除日</th>
              <th>削除者</th>
              <th>再参加可否</th>
              <th style="width: 150px;">操作</th>
            </tr>
          </thead>
          <tbody>
            @foreach($deletedMembers as $member)
            <tr class="table-danger">
              <td>#{{ $member->user_id }}</td>
              <td>
                {{ $member->user->name ?? '-' }}
                @if($member->user && $member->user->deleted_at)
                <span class="badge bg-danger ms-1">ユーザー削除済み</span>
                @endif
              </td>
              <td>{{ optional($member->left_at)->format('Y/m/d H:i') }}</td>
              <td>
                @if($member->removal_type === \App\Models\GroupMember::REMOVAL_TYPE_KICKED_BY_ADMIN && $member->removedByAdmin)
                {{ $member->removedByAdmin->name }}
                @else
                <span class="text-muted">{{ $member->removal_type_display }}</span>
                @endif
              </td>
              <td>
                @if($member->can_rejoin)
                <span class="badge bg-success">許可</span>
                @else
                <span class="badge bg-danger">禁止</span>
                @endif
              </td>
              <td>
                @if(!$group->isDeleted())
                <div class="btn-group" role="group">
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="showToggleRejoinModal({{ $member->user_id }}, {{ $member->can_rejoin ? 'true' : 'false' }}, '{{ addslashes($member->user->name ?? 'ユーザー') }}')">
                    <i class="fas fa-toggle-on"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-success" onclick="showRestoreMemberModal({{ $member->user_id }}, '{{ addslashes($member->user->name ?? 'ユーザー') }}')">
                    <i class="fas fa-undo"></i>
                  </button>
                </div>
                @else
                <span class="text-muted">-</span>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif
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
      <form id="removeMemberForm" method="POST" onsubmit="handleFormSubmit(event)">
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
          <div class="mb-3">
            <label class="form-label">再参加可否</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="can_rejoin" id="canRejoinTrue" value="1" checked>
              <label class="form-check-label" for="canRejoinTrue">
                再参加を許可する
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="can_rejoin" id="canRejoinFalse" value="0">
              <label class="form-check-label" for="canRejoinFalse">
                再参加を禁止する
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-danger" onclick="console.log('Delete button clicked')">削除する</button>
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

<!-- 再参加可否切り替えモーダル -->
<div class="modal fade" id="toggleRejoinModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">再参加可否変更</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="toggleRejoinForm" method="POST">
        @csrf
        @method('PATCH')
        <div class="modal-body">
          <p id="toggleRejoinMessage"></p>
          <div class="mb-3">
            <label class="form-label">新しい設定</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="can_rejoin" id="newCanRejoinTrue" value="1">
              <label class="form-check-label" for="newCanRejoinTrue">
                再参加を許可する
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="can_rejoin" id="newCanRejoinFalse" value="0">
              <label class="form-check-label" for="newCanRejoinFalse">
                再参加を禁止する
              </label>
            </div>
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

<!-- メンバー復活モーダル -->
<div class="modal fade" id="restoreMemberModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">メンバー復活確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="restoreMemberForm" method="POST">
        @csrf
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>注意:</strong> この操作により、削除済みメンバーが復活します。
          </div>
          <p id="restoreMemberMessage"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-success">復活する</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- グループ削除モーダル -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">グループ削除確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.groups.delete', $group->id) }}">
        @csrf
        @method('DELETE')
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>警告:</strong> この操作により、グループが論理削除されます。
          </div>
          <p>「{{ $group->name }}」を削除しますか？</p>
          <div class="mb-3">
            <label for="deleteReason" class="form-label">削除理由</label>
            <textarea class="form-control" id="deleteReason" name="reason" rows="3" placeholder="削除理由を入力してください"></textarea>
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

<!-- グループ復活モーダル -->
<div class="modal fade" id="restoreGroupModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">グループ復活確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('admin.groups.restore', $group->id) }}">
        @csrf
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>注意:</strong> この操作により、削除済みグループが復活します。
          </div>
          <p>「{{ $group->name }}」を復活させますか？</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-success">復活する</button>
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
    console.log('showRemoveMemberModal called:', userId, userName);

    const modal = document.getElementById('removeMemberModal');
    const form = document.getElementById('removeMemberForm');
    const message = document.getElementById('removeMemberMessage');

    console.log('Modal elements:', {
      modal,
      form,
      message
    });

    if (!modal || !form || !message) {
      console.error('Required modal elements not found');
      return;
    }

    const actionUrl = `{{ route('admin.groups.members.remove', ['groupId' => $group->id, 'memberId' => '__USER_ID__']) }}`.replace('__USER_ID__', userId);
    console.log('Action URL:', actionUrl);

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

  function showToggleRejoinModal(userId, currentCanRejoin, userName) {
    const modal = document.getElementById('toggleRejoinModal');
    const form = document.getElementById('toggleRejoinForm');
    const message = document.getElementById('toggleRejoinMessage');
    const rejoinTrue = document.getElementById('newCanRejoinTrue');
    const rejoinFalse = document.getElementById('newCanRejoinFalse');

    form.action = `{{ route('admin.groups.members.rejoin', ['groupId' => $group->id, 'memberId' => '__USER_ID__']) }}`.replace('__USER_ID__', userId);
    message.textContent = `「${userName}」の再参加可否を変更します。現在の設定: ${currentCanRejoin ? '許可' : '禁止'}`;

    if (currentCanRejoin) {
      rejoinFalse.checked = true;
    } else {
      rejoinTrue.checked = true;
    }

    new bootstrap.Modal(modal).show();
  }

  function showRestoreMemberModal(userId, userName) {
    const modal = document.getElementById('restoreMemberModal');
    const form = document.getElementById('restoreMemberForm');
    const message = document.getElementById('restoreMemberMessage');

    form.action = `{{ route('admin.groups.members.restore', ['groupId' => $group->id, 'memberId' => '__USER_ID__']) }}`.replace('__USER_ID__', userId);
    message.textContent = `「${userName}」を復活させますか？`;

    new bootstrap.Modal(modal).show();
  }

  function showDeleteGroupModal() {
    const modal = document.getElementById('deleteGroupModal');
    new bootstrap.Modal(modal).show();
  }

  function showRestoreGroupModal() {
    const modal = document.getElementById('restoreGroupModal');
    new bootstrap.Modal(modal).show();
  }

  function handleFormSubmit(event) {
    console.log('Form submit triggered');
    const form = event.target;
    console.log('Form action:', form.action);
    console.log('Form method:', form.method);

    // CSRFトークンの確認
    const csrfToken = form.querySelector('input[name="_token"]');
    console.log('CSRF token:', csrfToken ? csrfToken.value : 'NOT FOUND');

    const methodInput = form.querySelector('input[name="_method"]');
    console.log('Method input:', methodInput ? methodInput.value : 'NOT FOUND');

    // フォームデータの確認
    const formData = new FormData(form);
    console.log('Form data:');
    for (let [key, value] of formData.entries()) {
      console.log(`  ${key}: ${value}`);
    }

    // 実際の送信を続行
    return true;
  }

  // DOMが読み込まれた後にイベントリスナーを追加
  document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up event listeners');

    // フォーム送信イベントリスナーを直接追加
    const removeMemberForm = document.getElementById('removeMemberForm');
    if (removeMemberForm) {
      console.log('Adding submit event listener to removeMemberForm');
      removeMemberForm.addEventListener('submit', function(event) {
        console.log('Submit event listener triggered');
        handleFormSubmit(event);
      });
    } else {
      console.error('removeMemberForm not found');
    }

    // 削除ボタンのクリックイベントも監視
    document.addEventListener('click', function(event) {
      if (event.target.type === 'submit' && event.target.classList.contains('btn-danger')) {
        console.log('Delete submit button clicked');
      }
    });
  });
</script>
@endsection