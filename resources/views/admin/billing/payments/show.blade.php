@extends('admin.layouts.app')

@section('title', '決済詳細')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.billing.payments.index') }}">決済履歴</a></li>
        <li class="breadcrumb-item active">#{{ $payment->id }}</li>
      </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="h3 mb-0">
        <i class="fas fa-file-invoice-dollar me-2"></i>決済詳細
      </h1>
      @if($payment->status === 'succeeded')
      <form method="POST" action="{{ route('admin.billing.payments.refund', $payment->id) }}" class="d-inline" onsubmit="return confirm('返金処理を実行しますか？');">
        @csrf
        <button type="submit" class="btn btn-danger">
          <i class="fas fa-undo me-1"></i>返金
        </button>
      </form>
      @endif
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
          <dd class="col-sm-8">#{{ $payment->id }}</dd>
          <dt class="col-sm-4">ユーザー</dt>
          <dd class="col-sm-8">{{ $payment->user->name }}<br><small class="text-muted">{{ $payment->user->email }}</small></dd>
          <dt class="col-sm-4">金額</dt>
          <dd class="col-sm-8">{{ $payment->formatted_amount }}</dd>
          <dt class="col-sm-4">ステータス</dt>
          <dd class="col-sm-8">
            @if($payment->status === 'succeeded')
            <span class="badge bg-success">成功</span>
            @elseif($payment->status === 'failed')
            <span class="badge bg-danger">失敗</span>
            @elseif($payment->status === 'refunded')
            <span class="badge bg-warning text-dark">返金済み</span>
            @else
            <span class="badge bg-secondary">{{ $payment->status }}</span>
            @endif
          </dd>
          <dt class="col-sm-4">支払日</dt>
          <dd class="col-sm-8">{{ optional($payment->paid_at)->format('Y/m/d H:i') }}</dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">Stripe 情報</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-5">PaymentIntent ID</dt>
          <dd class="col-sm-7">{{ $payment->stripe_payment_intent_id }}</dd>
          <dt class="col-sm-5">Charge ID</dt>
          <dd class="col-sm-7">{{ $payment->stripe_charge_id ?? '-' }}</dd>
          <dt class="col-sm-5">タイプ</dt>
          <dd class="col-sm-7">{{ $payment->type }}</dd>
          <dt class="col-sm-5">返金額</dt>
          <dd class="col-sm-7">{{ $payment->refund_amount }}</dd>
        </dl>
      </div>
    </div>
  </div>
</div>
@if($payment->metadata)
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">メタデータ</div>
      <div class="card-body">
        <pre class="mb-0">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
      </div>
    </div>
  </div>
</div>
@endif
@endsection
