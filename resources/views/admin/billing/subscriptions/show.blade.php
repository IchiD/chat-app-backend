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
        @if($subscription->cancel_at_period_end)
        <form method="POST" action="{{ route('admin.billing.subscriptions.resume', $subscription->id) }}" class="d-inline" onsubmit="return confirm('解約を取り消しますか？');">
          @csrf
          <button type="submit" class="btn btn-success">
            <i class="fas fa-redo me-1"></i>解約取り消し
          </button>
        </form>
        @else
        <form method="POST" action="{{ route('admin.billing.subscriptions.cancel', $subscription->id) }}" class="d-inline" onsubmit="return confirm('本当にキャンセルしますか？');">
          @csrf
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-ban me-1"></i>即時停止
          </button>
        </form>
        @endif
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
            @if($subscription->user)
            {{ $subscription->user->name }}
            @if($subscription->user->isDeleted())
            <span class="badge bg-warning text-dark ms-2">削除済み</span>
            @endif
            <br><small class="text-muted">{{ $subscription->user->email }}</small>
            @else
            <span class="text-muted">削除されたユーザー</span><br>
            <small class="text-muted">ユーザー情報なし</small>
            @endif
          </dd>
          <dt class="col-sm-4">プラン</dt>
          <dd class="col-sm-8">{{ strtoupper($subscription->plan) }}</dd>
          <dt class="col-sm-4">ステータス</dt>
          <dd class="col-sm-8">
            {{ $subscription->status }}
            @if($subscription->cancel_at_period_end)
            <br><span class="badge bg-warning text-dark">
              <i class="fas fa-exclamation-triangle me-1"></i>解約予定
            </span>
            <small class="text-muted d-block mt-1">
              {{ optional($subscription->current_period_end)->format('Y/m/d') }} に解約予定
            </small>
            @endif
          </dd>
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
          @if($subscription->cancel_at_period_end)
          <dt class="col-sm-5">解約設定</dt>
          <dd class="col-sm-7">
            <span class="text-warning">
              <i class="fas fa-exclamation-triangle me-1"></i>期間終了時に解約
            </span>
            <br><small class="text-muted">
              {{ optional($subscription->current_period_end)->format('Y年m月d日 H:i') }} に自動解約
            </small>
          </dd>
          @endif
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