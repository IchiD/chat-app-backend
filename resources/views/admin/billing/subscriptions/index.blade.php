@extends('admin.layouts.app')

@section('title', 'サブスクリプション管理')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-sync-alt me-2"></i>サブスクリプション管理
        </h1>
        <p class="text-muted">契約状況の確認と管理</p>
      </div>
    </div>
  </div>
</div>

<!-- フィルタリング -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('admin.billing.subscriptions.index') }}" class="row g-3">
          <div class="col-md-4">
            <label for="search" class="form-label">検索</label>
            <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="ユーザー名・メールアドレス">
          </div>
          <div class="col-md-3">
            <label for="status" class="form-label">ステータス</label>
            <select class="form-select" id="status" name="status">
              <option value="">全て ({{ $statusCounts['all'] }})</option>
              <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>アクティブ ({{ $statusCounts['active'] }})</option>
              <option value="canceled" {{ request('status') == 'canceled' ? 'selected' : '' }}>キャンセル ({{ $statusCounts['canceled'] }})</option>
              <option value="past_due" {{ request('status') == 'past_due' ? 'selected' : '' }}>未払い ({{ $statusCounts['past_due'] }})</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="plan" class="form-label">プラン</label>
            <select class="form-select" id="plan" name="plan">
              <option value="">全て</option>
              <option value="standard" {{ request('plan') == 'standard' ? 'selected' : '' }}>スタンダード</option>
              <option value="premium" {{ request('plan') == 'premium' ? 'selected' : '' }}>プレミアム</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">
              <i class="fas fa-search me-1"></i>検索
            </button>
            <a href="{{ route('admin.billing.subscriptions.index') }}" class="btn btn-secondary">
              <i class="fas fa-times me-1"></i>クリア
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- サブスクリプション一覧 -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">サブスクリプション一覧 ({{ $subscriptions->total() }}件)</h5>
      </div>
      <div class="card-body">
        @if($subscriptions->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>ユーザー</th>
                <th>プラン</th>
                <th>ステータス</th>
                <th>次回更新日</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($subscriptions as $subscription)
              <tr>
                <td>#{{ $subscription->id }}</td>
                <td>
                  <div>
                    <strong>{{ $subscription->user->name }}</strong><br>
                    <small class="text-muted">{{ $subscription->user->email }}</small>
                  </div>
                </td>
                <td>{{ strtoupper($subscription->plan) }}</td>
                <td>
                  @if($subscription->status === 'active')
                  <span class="badge bg-success">アクティブ</span>
                  @if($subscription->cancel_at_period_end)
                  <br><span class="badge bg-warning text-dark mt-1">
                    <i class="fas fa-exclamation-triangle me-1"></i>解約予定
                  </span>
                  @endif
                  @elseif($subscription->status === 'canceled')
                  <span class="badge bg-secondary">キャンセル</span>
                  @elseif($subscription->status === 'past_due')
                  <span class="badge bg-danger">未払い</span>
                  @else
                  <span class="badge bg-warning text-dark">{{ $subscription->status }}</span>
                  @endif
                </td>
                <td>{{ optional($subscription->current_period_end)->format('Y/m/d') }}</td>
                <td>
                  <a href="{{ route('admin.billing.subscriptions.show', $subscription->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-eye me-1"></i>詳細
                  </a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted">
            {{ $subscriptions->firstItem() }}〜{{ $subscriptions->lastItem() }}件目 / 全{{ $subscriptions->total() }}件
          </div>
          <div>
            {{ $subscriptions->links() }}
          </div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-ban fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">サブスクリプションが見つかりません</h5>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection