@extends('admin.layouts.app')

@section('title', '決済履歴')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-file-invoice-dollar me-2"></i>決済履歴
        </h1>
        <p class="text-muted">Stripe で行われた決済の履歴</p>
      </div>
    </div>
  </div>
</div>

<!-- フィルタリング -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('admin.billing.payments.index') }}" class="row g-3">
          <div class="col-md-3">
            <label for="status" class="form-label">ステータス</label>
            <select class="form-select" id="status" name="status">
              <option value="">全て ({{ $statusCounts['all'] }})</option>
              <option value="succeeded" {{ request('status') == 'succeeded' ? 'selected' : '' }}>成功 ({{ $statusCounts['succeeded'] }})</option>
              <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>失敗 ({{ $statusCounts['failed'] }})</option>
              <option value="refunded" {{ request('status') == 'refunded' ? 'selected' : '' }}>返金 ({{ $statusCounts['refunded'] }})</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="type" class="form-label">タイプ</label>
            <select class="form-select" id="type" name="type">
              <option value="">全て</option>
              <option value="subscription" {{ request('type') == 'subscription' ? 'selected' : '' }}>サブスクリプション</option>
              <option value="one_time" {{ request('type') == 'one_time' ? 'selected' : '' }}>単発決済</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="from" class="form-label">開始日</label>
            <input type="date" class="form-control" id="from" name="from" value="{{ request('from') }}">
          </div>
          <div class="col-md-3">
            <label for="to" class="form-label">終了日</label>
            <input type="date" class="form-control" id="to" name="to" value="{{ request('to') }}">
          </div>
          <div class="col-12 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">
              <i class="fas fa-search me-1"></i>検索
            </button>
            <a href="{{ route('admin.billing.payments.index') }}" class="btn btn-secondary me-2">
              <i class="fas fa-times me-1"></i>クリア
            </a>
            <a href="{{ route('admin.billing.payments.export', request()->query()) }}" class="btn btn-outline-success">
              <i class="fas fa-file-csv me-1"></i>CSVエクスポート
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- 決済履歴一覧 -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">決済履歴一覧 ({{ $payments->total() }}件)</h5>
      </div>
      <div class="card-body">
        @if($payments->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>ユーザー</th>
                <th>プラン/タイプ</th>
                <th>金額</th>
                <th>ステータス</th>
                <th>支払日</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($payments as $payment)
              <tr>
                <td>#{{ $payment->id }}</td>
                <td>
                  <strong>{{ $payment->user->name }}</strong><br>
                  <small class="text-muted">{{ $payment->user->email }}</small>
                </td>
                <td>
                  @if($payment->subscription)
                  {{ strtoupper($payment->subscription->plan) }}
                  @else
                  {{ $payment->type }}
                  @endif
                </td>
                <td>{{ $payment->formatted_amount }}</td>
                <td>
                  @if($payment->status === 'succeeded')
                  <span class="badge bg-success">成功</span>
                  @elseif($payment->status === 'failed')
                  <span class="badge bg-danger">失敗</span>
                  @elseif($payment->status === 'refunded')
                  <span class="badge bg-warning text-dark">返金済み</span>
                  @else
                  <span class="badge bg-secondary">{{ $payment->status }}</span>
                  @endif
                </td>
                <td>{{ optional($payment->paid_at)->format('Y/m/d') }}</td>
                <td>
                  <a href="{{ route('admin.billing.payments.show', $payment->id) }}" class="btn btn-sm btn-outline-primary">
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
            {{ $payments->firstItem() }}〜{{ $payments->lastItem() }}件目 / 全{{ $payments->total() }}件
          </div>
          <div>
            {{ $payments->links() }}
          </div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">決済履歴が見つかりません</h5>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
