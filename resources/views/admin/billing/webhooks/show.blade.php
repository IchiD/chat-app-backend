@extends('admin.layouts.app')

@section('title', 'Webhook詳細')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.billing.webhooks.index') }}">Webhookログ</a></li>
        <li class="breadcrumb-item active">#{{ $webhook->id }}</li>
      </ol>
    </nav>
    <h1 class="h3 mb-0">
      <i class="fas fa-code-branch me-2"></i>Webhook 詳細
    </h1>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">基本情報</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">ID</dt>
          <dd class="col-sm-8">#{{ $webhook->id }}</dd>
          <dt class="col-sm-4">イベントID</dt>
          <dd class="col-sm-8">{{ $webhook->stripe_event_id }}</dd>
          <dt class="col-sm-4">タイプ</dt>
          <dd class="col-sm-8">{{ $webhook->event_type }}</dd>
          <dt class="col-sm-4">ステータス</dt>
          <dd class="col-sm-8">
            @if($webhook->status === 'processed')
            <span class="badge bg-success">成功</span>
            @elseif($webhook->status === 'failed')
            <span class="badge bg-danger">失敗</span>
            @else
            <span class="badge bg-secondary">未処理</span>
            @endif
          </dd>
          <dt class="col-sm-4">受信日時</dt>
          <dd class="col-sm-8">{{ $webhook->created_at->format('Y/m/d H:i') }}</dd>
          <dt class="col-sm-4">処理日時</dt>
          <dd class="col-sm-8">{{ optional($webhook->processed_at)->format('Y/m/d H:i') ?? '-' }}</dd>
          @if($webhook->error_message)
          <dt class="col-sm-4">エラー</dt>
          <dd class="col-sm-8">{{ $webhook->error_message }}</dd>
          @endif
        </dl>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">ペイロード</div>
      <div class="card-body">
        <pre class="mb-0">{{ $webhook->formatted_payload }}</pre>
      </div>
    </div>
  </div>
</div>
@endsection
