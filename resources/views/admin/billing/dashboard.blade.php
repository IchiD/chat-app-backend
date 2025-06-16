@extends('admin.layouts.app')

@section('title', '決済ダッシュボード')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <h1 class="h3 mb-0">
      <i class="fas fa-credit-card me-2"></i>決済ダッシュボード
    </h1>
    <p class="text-muted">売上とサブスクリプションの統計</p>
  </div>
</div>

<!-- 統計カード -->
<div class="row mb-4">
  <div class="col-md-6 col-lg-3 mb-3">
    <a href="{{ route('admin.billing.payments.index') }}" class="text-decoration-none">
      <div class="card h-100 border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0; transition: transform 0.2s;">
        <div class="card-body p-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="rounded-circle bg-success bg-opacity-10 p-2">
              <i class="fas fa-yen-sign text-success"></i>
            </div>
            <h3 class="mb-0 text-success fw-bold">{{ number_format($stats['monthly_revenue']) }}円</h3>
          </div>
          <h6 class="text-dark mb-1">今月の売上</h6>
          <p class="text-muted small mb-0">先月比 {{ $stats['growth_rate'] }}% <i class="fas fa-external-link-alt ms-1"></i></p>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3 mb-3">
    <a href="{{ route('admin.billing.subscriptions.index', ['status' => 'active']) }}" class="text-decoration-none">
      <div class="card h-100 border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0; transition: transform 0.2s;">
        <div class="card-body p-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="rounded-circle bg-primary bg-opacity-10 p-2">
              <i class="fas fa-users text-primary"></i>
            </div>
            <h3 class="mb-0 text-primary fw-bold">{{ number_format($stats['active_subscriptions']) }}</h3>
          </div>
          <h6 class="text-dark mb-1">アクティブサブスクリプション</h6>
          <p class="text-muted small mb-0">現在有効な契約数 <i class="fas fa-external-link-alt ms-1"></i></p>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3 mb-3">
    <a href="{{ route('admin.billing.subscriptions.index') }}" class="text-decoration-none">
      <div class="card h-100 border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0; transition: transform 0.2s;">
        <div class="card-body p-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="rounded-circle bg-info bg-opacity-10 p-2">
              <i class="fas fa-plus text-info"></i>
            </div>
            <h3 class="mb-0 text-info fw-bold">{{ number_format($stats['new_subscriptions_this_month']) }}</h3>
          </div>
          <h6 class="text-dark mb-1">今月の新規契約</h6>
          <p class="text-muted small mb-0">{{ now()->format('n月') }}の新規数 <i class="fas fa-external-link-alt ms-1"></i></p>
        </div>
      </div>
    </a>
  </div>
  <div class="col-md-6 col-lg-3 mb-3">
    <a href="{{ route('admin.billing.subscriptions.index', ['status' => 'canceled']) }}" class="text-decoration-none">
      <div class="card h-100 border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0; transition: transform 0.2s;">
        <div class="card-body p-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="rounded-circle bg-danger bg-opacity-10 p-2">
              <i class="fas fa-ban text-danger"></i>
            </div>
            <h3 class="mb-0 text-danger fw-bold">{{ number_format($stats['canceled_subscriptions_this_month']) }}</h3>
          </div>
          <h6 class="text-dark mb-1">今月の解約</h6>
          <p class="text-muted small mb-0">{{ now()->format('n月') }}の解約数 <i class="fas fa-external-link-alt ms-1"></i></p>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- クイックアクセスメニュー -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-tachometer-alt me-2"></i>クイックアクセス
        </h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6 col-lg-3">
            <a href="{{ route('admin.billing.subscriptions.index') }}" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 text-decoration-none">
              <i class="fas fa-sync-alt fa-2x mb-2"></i>
              <span class="fw-bold">サブスクリプション管理</span>
              <small class="text-muted">契約状況の確認と管理</small>
            </a>
          </div>
          <div class="col-md-6 col-lg-3">
            <a href="{{ route('admin.billing.payments.index') }}" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 text-decoration-none">
              <i class="fas fa-credit-card fa-2x mb-2"></i>
              <span class="fw-bold">決済履歴</span>
              <small class="text-muted">全決済の追跡と返金処理</small>
            </a>
          </div>
          <div class="col-md-6 col-lg-3">
            <a href="{{ route('admin.billing.webhooks.index') }}" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 text-decoration-none">
              <i class="fas fa-code-branch fa-2x mb-2"></i>
              <span class="fw-bold">Webhook ログ</span>
              <small class="text-muted">イベント処理状況の監視</small>
            </a>
          </div>
          <div class="col-md-6 col-lg-3">
            <a href="{{ route('admin.billing.analytics.index') }}" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3 text-decoration-none">
              <i class="fas fa-chart-line fa-2x mb-2"></i>
              <span class="fw-bold">分析・レポート</span>
              <small class="text-muted">MRR・チャーン率の分析</small>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- グラフ -->
<div class="row mb-4">
  <div class="col-lg-6 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title mb-0">プラン別契約数</h5>
      </div>
      <div class="card-body">
        <canvas id="planChart" height="200"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title mb-0">月別売上推移</h5>
      </div>
      <div class="card-body">
        <canvas id="revenueChart" height="200"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Webhook エラー -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">最近の Webhook エラー</h5>
        <a href="{{ route('admin.billing.webhooks.index', ['status' => 'failed']) }}" class="btn btn-sm btn-outline-danger">
          <i class="fas fa-exclamation-triangle me-1"></i>すべてのエラーを表示
        </a>
      </div>
      <div class="card-body">
        @if($recentWebhookErrors->isEmpty())
        <p class="text-muted mb-0">エラーはありません</p>
        @else
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead class="table-light">
              <tr>
                <th>受信日時</th>
                <th>イベントタイプ</th>
                <th>エラーメッセージ</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentWebhookErrors as $log)
              <tr>
                <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $log->event_type }}</td>
                <td>{{ Str::limit($log->error_message, 60) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<style>
  .card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
  }

  .btn:hover {
    transform: translateY(-1px);
  }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Plan Chart
    const planCtx = document.getElementById('planChart').getContext('2d');
    const planData = {
      labels: @json($planChartData['labels']),
      datasets: [{
        data: @json($planChartData['data']),
        backgroundColor: ['#3b5b7a', '#c0392b', '#28a745', '#ffc107'],
      }]
    };
    new Chart(planCtx, {
      type: 'pie',
      data: planData,
      options: {
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = {
      labels: @json($revenueChartData['labels']),
      datasets: [{
        label: '売上',
        data: @json($revenueChartData['data']),
        borderColor: '#3b5b7a',
        backgroundColor: 'rgba(59, 91, 122, 0.1)',
        tension: 0.3
      }]
    };
    new Chart(revenueCtx, {
      type: 'line',
      data: revenueData,
      options: {
        scales: {
          y: {
            beginAtZero: true
          }
        },
        plugins: {
          legend: {
            display: false
          }
        }
      }
    });
  });
</script>
@endsection