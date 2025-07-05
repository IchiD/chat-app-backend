@extends('admin.layouts.app')

@section('title', 'ユーザー詳細')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-user me-2"></i>ユーザー詳細
        </h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">ダッシュボード</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.users') }}">ユーザー管理</a></li>
            <li class="breadcrumb-item active">{{ $user->name }}</li>
          </ol>
        </nav>
      </div>
      <div>
        @if(!$user->isDeleted())
        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-primary me-2">
          <i class="fas fa-edit me-1"></i>編集
        </a>
        <a href="{{ route('admin.users.conversations', $user->id) }}" class="btn btn-outline-primary">
          <i class="fas fa-comments me-1"></i>チャット管理
        </a>
        @else
        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-primary me-2">
          <i class="fas fa-edit me-1"></i>編集
        </a>
        <a href="{{ route('admin.users.conversations', $user->id) }}" class="btn btn-outline-primary me-2">
          <i class="fas fa-comments me-1"></i>チャット管理
        </a>
        <form method="POST" action="{{ route('admin.users.restore', $user->id) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-success"
            onclick="return confirm('ユーザーの削除を取り消しますか？')">
            <i class="fas fa-undo me-1"></i>削除取り消し
          </button>
        </form>
        @endif
      </div>
    </div>
  </div>
</div>

@if($user->isDeleted())
<div class="row mb-4">
  <div class="col-12">
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <strong>
        @if($user->isDeletedBySelf())
        このユーザーは自身でアカウントを削除しています
        @else
        このユーザーは管理者によって削除されています
        @endif
      </strong>
      <div class="mt-2">
        <strong>削除日時:</strong> {{ $user->deleted_at->format('Y年m月d日 H:i') }}<br>
        @if($user->deletedByAdmin)
        <strong>削除者:</strong> {{ $user->deletedByAdmin->name }}<br>
        @endif
        @if($user->deleted_reason)
        <strong>削除理由:</strong> {{ $user->deleted_reason }}
        @endif
        @if($user->isDeletedBySelf())
        <br><small class="text-muted">※ 自己削除されたユーザーの再登録可否は管理者が設定できます</small>
        @endif
      </div>
    </div>
  </div>
</div>
@endif

<!-- 基本情報 -->
<div class="row mb-4">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-user me-2"></i>基本情報
        </h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label text-muted">ユーザーID</label>
              <div class="fw-bold">#{{ $user->id }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">名前</label>
              <div class="fw-bold">{{ $user->name }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">メールアドレス</label>
              <div>{{ $user->email }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">フレンドID</label>
              <div><code class="bg-light p-2 rounded">{{ $user->friend_id }}</code></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label text-muted">登録日時</label>
              <div>{{ $user->created_at->format('Y年m月d日 H:i') }}</div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">認証状態</label>
              <div>
                @if($user->is_verified)
                <span class="badge badge-verified">
                  <i class="fas fa-check-circle me-1"></i>認証済み
                </span>
                @else
                <span class="badge badge-unverified">
                  <i class="fas fa-exclamation-circle me-1"></i>未認証
                </span>
                @endif
              </div>
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
              <label class="form-label text-muted">プラン</label>
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
              <label class="form-label text-muted">アカウント状態</label>
              <div>
                @if($user->isDeleted())
                <span class="badge bg-danger">
                  <i class="fas fa-trash me-1"></i>削除済み
                </span>
                @elseif($user->isBanned())
                <span class="badge badge-banned">
                  <i class="fas fa-ban me-1"></i>バン済み
                </span>
                @else
                <span class="badge badge-verified">
                  <i class="fas fa-check me-1"></i>アクティブ
                </span>
                @endif
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted">再登録設定</label>
              <div>
                @if($user->allow_re_registration)
                <span class="badge bg-success">
                  <i class="fas fa-check me-1"></i>再登録許可
                </span>
                @else
                <span class="badge bg-danger">
                  <i class="fas fa-times me-1"></i>再登録禁止
                </span>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-chart-bar me-2"></i>統計情報
        </h5>
      </div>
      <div class="card-body">
        <div class="row text-center">
          <div class="col-6 mb-3">
            <div class="fs-2 fw-bold text-primary">{{ $stats['total_chat_rooms'] }}</div>
            <div class="text-muted">参加チャットルーム</div>
          </div>
          <div class="col-6 mb-3">
            <div class="fs-2 fw-bold text-success">{{ $stats['total_messages'] }}</div>
            <div class="text-muted">送信メッセージ</div>
          </div>
          <div class="col-6">
            <div class="fs-2 fw-bold text-info">{{ $stats['friends_count'] }}</div>
            <div class="text-muted">友達数</div>
          </div>
          <div class="col-6">
            <div class="fs-6 fw-bold text-warning">{{ $stats['last_login'] ?? '-' }}</div>
            <div class="text-muted">最終ログイン</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- 最近のチャットルーム -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
          <i class="fas fa-comments me-2"></i>最近のチャットルーム
        </h5>
        <a href="{{ route('admin.users.conversations', $user->id) }}" class="btn btn-sm btn-outline-primary">
          <i class="fas fa-external-link-alt me-1"></i>全てのチャットルームを見る
        </a>
      </div>
      <div class="card-body">
        @if($chatRooms->count() > 0)
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>チャットルームID</th>
                <th>タイプ</th>
                <th>参加者/グループ</th>
                <th>最新メッセージ</th>
                <th>最終更新</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($chatRooms as $chatRoom)
              <tr>
                <td>
                  <span class="text-primary">#{{ $chatRoom->id }}</span>
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
                  @if($chatRoom->type === 'group_chat')
                  @if($chatRoom->group)
                  <strong>{{ $chatRoom->group->name }}</strong>
                  @if($chatRoom->group->trashed())
                  <span class="badge bg-danger ms-1">削除済み</span>
                  @endif
                  <br><small class="text-muted">グループID: {{ $chatRoom->group->id }}</small>
                  @else
                  <span class="text-muted">削除されたグループ</span>
                  @endif
                  @elseif(in_array($chatRoom->type, ['friend_chat', 'member_chat']))
                  @php
                  $otherParticipant = $chatRoom->participant1_id === $user->id ? $chatRoom->participant2 : $chatRoom->participant1;
                  @endphp
                  @if($otherParticipant)
                  {{ $otherParticipant->name }}{{ $otherParticipant->trashed() ? ' (削除済み)' : '' }}
                  @else
                  <span class="text-muted">参加者情報不明</span>
                  @endif
                  @else
                  <span class="text-muted">サポート</span>
                  @endif
                </td>
                <td>
                  @if($chatRoom->latestMessage)
                  <small class="text-truncate d-inline-block message-content" style="max-width: 200px;">
                    {{ $chatRoom->latestMessage->text_content }}
                  </small>
                  @else
                  <span class="text-muted">メッセージなし</span>
                  @endif
                </td>
                <td>
                  @if($chatRoom->latestMessage && $chatRoom->latestMessage->sent_at)
                  <small class="text-muted">
                    {{ $chatRoom->latestMessage->sent_at->format('m/d H:i') }}
                  </small>
                  @else
                  <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  <a href="{{ route('admin.users.conversations.detail', [$user->id, $chatRoom->id]) }}"
                    class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div class="text-center py-3">
          <i class="fas fa-comments fa-2x text-muted mb-2"></i>
          <div class="text-muted">参加しているチャットルームがありません</div>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- 最近のメッセージ -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-envelope me-2"></i>最近のメッセージ
        </h5>
      </div>
      <div class="card-body">
        @if($user->messages->count() > 0)
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>メッセージID</th>
                <th>内容</th>
                <th>チャット</th>
                <th>送信日時</th>
                <th>状態</th>
              </tr>
            </thead>
            <tbody>
              @foreach($user->messages->take(10) as $message)
              <tr>
                <td>
                  <span class="text-primary">#{{ $message->id }}</span>
                </td>
                <td>
                  <div class="text-truncate message-content" style="max-width: 300px;">
                    {{ $message->text_content }}
                  </div>
                </td>
                <td>
                  @if($message->chat_room_id)
                  <a href="{{ route('admin.users.conversations.detail', [$user->id, $message->chat_room_id]) }}"
                    class="text-decoration-none">
                    #{{ $message->chat_room_id }}
                  </a>
                  @elseif($message->conversation_id)
                  <a href="{{ route('admin.users.conversations.detail', [$user->id, $message->conversation_id]) }}"
                    class="text-decoration-none">
                    #{{ $message->conversation_id }} (旧)
                  </a>
                  @else
                  <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  <small class="text-muted">
                    {{ $message->sent_at->format('m/d H:i') }}
                  </small>
                </td>
                <td>
                  @if($message->isAdminDeleted())
                  <span class="badge bg-danger">管理者削除</span>
                  @elseif($message->deleted_at)
                  <span class="badge bg-danger">ユーザー削除</span>
                  @elseif($message->edited_at)
                  <span class="badge bg-info">編集済み</span>
                  @else
                  <span class="badge bg-success">正常</span>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div class="text-center py-3">
          <i class="fas fa-envelope fa-2x text-muted mb-2"></i>
          <div class="text-muted">送信したメッセージがありません</div>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection