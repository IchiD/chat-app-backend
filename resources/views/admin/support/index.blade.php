@extends('admin.layouts.app')

@section('title', 'お問い合わせ管理')

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
          <i class="fas fa-comments me-2"></i>お問い合わせ管理
        </h1>
        <div>
          @if(request('filter') !== 'unread')
            <a href="{{ route('admin.support', ['filter' => 'unread']) }}" class="btn btn-outline-warning">
              <i class="fas fa-bell me-1"></i>未読のみ表示
            </a>
          @else
            <a href="{{ route('admin.support') }}" class="btn btn-outline-secondary">
              <i class="fas fa-list me-1"></i>すべて表示
            </a>
          @endif
        </div>
      </div>

      <!-- 検索・フィルタフォーム -->
      <div class="card mb-4">
        <div class="card-body">
          <form method="GET" action="{{ route('admin.support') }}" class="row g-3">
            <div class="col-md-4">
              <label for="search" class="form-label">ユーザー検索</label>
              <input type="text" class="form-control" id="search" name="search"
                value="{{ request('search') }}" placeholder="ユーザー名またはメールアドレス">
            </div>
            <div class="col-md-4">
              <label for="filter" class="form-label">フィルタ</label>
              <select class="form-select" id="filter" name="filter">
                <option value="">すべてのチャット</option>
                <option value="unread" {{ request('filter') === 'unread' ? 'selected' : '' }}>未読メッセージのあるチャットのみ</option>
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-primary me-2">
                <i class="fas fa-search me-1"></i>検索
              </button>
              <a href="{{ route('admin.support') }}" class="btn btn-secondary">
                <i class="fas fa-times me-1"></i>クリア
              </a>
            </div>
          </form>
        </div>
      </div>

      <!-- サポートチャットリスト -->
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">
            サポートチャット一覧 ({{ $conversations->total() }}件)
            @if(request('filter') === 'unread')
              <span class="badge bg-warning text-dark ms-2">未読メッセージのみ表示</span>
            @endif
            @php
              $totalUnreadCount = $conversations->sum('unread_count');
            @endphp
            @if($totalUnreadCount > 0)
              <span class="badge bg-danger ms-2">{{ $totalUnreadCount }}件の未読メッセージ</span>
            @endif
          </h5>
        </div>
        <div class="card-body">
          @if($conversations->count() > 0)
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ユーザー</th>
                  <th>最新メッセージ</th>
                  <th>作成日時</th>
                  <th>更新日時</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                @foreach($conversations as $conversation)
                <tr class="{{ $conversation->unread_count > 0 ? 'table-warning' : '' }}">
                  <td>
                    @php
                    // サポートチャットでは participant1 がユーザー、participant2 が管理者（通常はnull）
                    $user = $conversation->participant1;
                    @endphp
                    @if($user)
                    <div>
                      <strong>{{ $user->name }}</strong>
                      <br>
                      <small class="text-muted">{{ $user->email }}</small>
                    </div>
                    @else
                    <span class="text-muted">ユーザー不明</span>
                    @endif
                  </td>
                  <td>
                    @if($conversation->latestMessage)
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <div class="text-muted small message-content">
                          {{ $conversation->latestMessage->text_content }}
                        </div>
                        <div class="text-muted small">
                          {{ $conversation->latestMessage->getSenderDisplayName() }}
                          •
                          {{ $conversation->latestMessage->sent_at->format('m/d H:i') }}
                        </div>
                      </div>
                      @if($conversation->unread_count > 0)
                      <span class="badge bg-danger rounded-pill">{{ $conversation->unread_count }}件の未読</span>
                      @endif
                    </div>
                    @else
                    <div class="text-muted small">
                      メッセージがありません
                    </div>
                    @endif
                  </td>
                  <td>{{ $conversation->created_at->format('Y/m/d H:i') }}</td>
                  <td>{{ $conversation->updated_at->format('Y/m/d H:i') }}</td>
                  <td>
                    <a href="{{ route('admin.support.detail', $conversation->id) }}" class="btn btn-sm btn-outline-primary">
                      詳細を見る
                    </a>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <!-- ページネーション -->
          <div class="d-flex justify-content-center">
            {{ $conversations->links() }}
          </div>
          @else
          <div class="text-center py-4">
            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
            @if(request('filter') === 'unread')
              <h5 class="text-muted">未読メッセージのあるお問い合わせはありません</h5>
              <p class="text-muted">未読のメッセージがあるお問い合わせがある場合、ここに表示されます。</p>
              <a href="{{ route('admin.support') }}" class="btn btn-outline-primary">
                <i class="fas fa-list me-1"></i>すべてのお問い合わせを表示
              </a>
            @else
              <h5 class="text-muted">お問い合わせはありません</h5>
              <p class="text-muted">ユーザーからのお問い合わせがある場合、ここに表示されます。</p>
            @endif
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection