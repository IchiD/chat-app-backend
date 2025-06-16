@extends('admin.layouts.app')

@section('title', 'Webhookログ')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="h3 mb-0">
        <i class="fas fa-code-branch me-2"></i>Webhook ログ
      </h1>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('admin.billing.webhooks.index') }}" class="row g-3">
          <div class="col-md-4">
            <label for="event_type" class="form-label">イベントタイプ</label>
            <select id="event_type" name="event_type" class="form-select">
              <option value="">全て</option>
              @foreach($eventTypes as $type)
              <option value="{{ $type }}" {{ request('event_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label for="status" class="form-label">ステータス</label>
            <select id="status" name="status" class="form-select">
              <option value="">全て ({{ $statusCounts['all'] }})</option>
              <option value="processed" {{ request('status') == 'processed' ? 'selected' : '' }}>成功 ({{ $statusCounts['processed'] }})</option>
              <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>失敗 ({{ $statusCounts['failed'] }})</option>
              <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>未処理 ({{ $statusCounts['pending'] }})</option>
            </select>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">
              <i class="fas fa-search me-1"></i>検索
            </button>
            <a href="{{ route('admin.billing.webhooks.index') }}" class="btn btn-secondary">
              <i class="fas fa-times me-1"></i>クリア
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Webhookログ一覧 ({{ $webhooks->total() }}件)</h5>
      </div>
      <div class="card-body">
        @if($webhooks->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>イベントID</th>
                <th>タイプ</th>
                <th>ステータス</th>
                <th>受信日時</th>
                <th>処理日時</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($webhooks as $log)
              <tr>
                <td>#{{ $log->id }}</td>
                <td>{{ $log->stripe_event_id }}</td>
                <td>{{ $log->event_type }}</td>
                <td>
                  @if($log->status === 'processed')
                  <span class="badge bg-success">成功</span>
                  @elseif($log->status === 'failed')
                  <span class="badge bg-danger">失敗</span>
                  @else
                  <span class="badge bg-secondary">未処理</span>
                  @endif
                </td>
                <td>{{ $log->created_at->format('Y/m/d H:i') }}</td>
                <td>{{ optional($log->processed_at)->format('Y/m/d H:i') ?? '-' }}</td>
                <td>
                  <a href="{{ route('admin.billing.webhooks.show', $log->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-eye me-1"></i>詳細
                  </a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted">{{ $webhooks->firstItem() }}〜{{ $webhooks->lastItem() }}件目 / 全{{ $webhooks->total() }}件</div>
          <div>{{ $webhooks->links() }}</div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-code-branch fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">Webhook ログが見つかりません</h5>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
