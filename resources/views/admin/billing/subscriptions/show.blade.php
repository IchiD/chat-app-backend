@extends('admin.layouts.app')

@section('title', 'サブスクリプション詳細')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.billing.subscriptions.index') }}">サブスクリプション</a></li>
        <li class="breadcrumb-item active">#{{ $subscription->id }}</li>
      </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="h3 mb-0">
        <i class="fas fa-sync-alt me-2"></i>サブスクリプション詳細
      </h1>
      <div>
        @if($subscription->status === 'active')
        <form method="POST" action="{{ route('admin.billing.subscriptions.cancel', $subscription->id) }}" class="d-inline" onsubmit="return confirm('本当にキャンセルしますか？');">
          @csrf
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-ban me-1"></i>キャンセル
          </button>
        </form>
        @else
        <form method="POST" action="{{ route('admin.billing.subscriptions.resume', $subscription->id) }}" class="d-inline" onsubmit="return confirm('サブスクリプションを再開しますか？');">
          @csrf
          <button type="submit" class="btn btn-success">
            <i class="fas fa-redo me-1"></i>再開
          </button>
        </form>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">基本情報</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">ID</dt>
          <dd class="col-sm-8">#{{ $subscription->id }}</dd>
          <dt class="col-sm-4">ユーザー</dt>
          <dd class="col-sm-8">
            {{ $subscription->user->name }}<br>
            <small class="text-muted">{{ $subscription->user->email }}</small>
          </dd>
          <dt class="col-sm-4">プラン</dt>
          <dd class="col-sm-8">{{ strtoupper($subscription->plan) }}</dd>
          <dt class="col-sm-4">ステータス</dt>
          <dd class="col-sm-8">{{ $subscription->status }}</dd>
          <dt class="col-sm-4">次回更新日</dt>
          <dd class="col-sm-8">{{ optional($subscription->current_period_end)->format('Y/m/d') }}</dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Stripe情報</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-5">Subscription ID</dt>
          <dd class="col-sm-7">{{ $subscription->stripe_subscription_id }}</dd>
          <dt class="col-sm-5">Customer ID</dt>
          <dd class="col-sm-7">{{ $subscription->stripe_customer_id }}</dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">直近の決済 ({{ $subscription->paymentTransactions->count() }}件)</h5>
      </div>
      <div class="card-body">
        @if($subscription->paymentTransactions->count() > 0)
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>ID</th>
                <th>金額</th>
                <th>ステータス</th>
                <th>支払日</th>
              </tr>
            </thead>
            <tbody>
              @foreach($subscription->paymentTransactions as $payment)
              <tr>
                <td>#{{ $payment->id }}</td>
                <td>{{ $payment->formatted_amount }}</td>
                <td>{{ $payment->status }}</td>
                <td>{{ optional($payment->paid_at)->format('Y/m/d') }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div class="text-center text-muted py-3">決済履歴がありません</div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">サブスクリプション履歴 ({{ $history->count() }}件)</h5>
      </div>
      <div class="card-body">
        @if($history->count() > 0)
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>日時</th>
                <th>アクション</th>
                <th>詳細</th>
              </tr>
            </thead>
            <tbody>
              @foreach($history as $log)
              <tr>
                <td>{{ $log->created_at->format('Y/m/d H:i') }}</td>
                <td>{{ $log->action_display }}</td>
                <td>{{ $log->description }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div class="text-center text-muted py-3">履歴がありません</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
