@extends('admin.layouts.app')

@section('title', '分析・レポート')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <h1 class="h3 mb-0">
      <i class="fas fa-chart-line me-2"></i>分析・レポート
    </h1>
    <p class="text-muted">売上や解約率の分析</p>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('admin.billing.analytics.index') }}" class="row g-3">
          <div class="col-md-4">
            <label for="period" class="form-label">期間</label>
            <select id="period" name="period" class="form-select">
              <option value="3months" {{ request('period') == '3months' ? 'selected' : '' }}>直近3ヶ月</option>
              <option value="6months" {{ request('period') == '6months' ? 'selected' : '' }}>直近6ヶ月</option>
              <option value="12months" {{ request('period', '12months') == '12months' ? 'selected' : '' }}>直近12ヶ月</option>
            </select>
          </div>
          <div class="col-md-8 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">
              <i class="fas fa-search me-1"></i>表示
            </button>
            <a href="{{ route('admin.billing.analytics.index') }}" class="btn btn-secondary me-2">
              <i class="fas fa-times me-1"></i>クリア
            </a>
            <a href="{{ route('admin.billing.analytics.export', request()->query()) }}" class="btn btn-outline-success">
              <i class="fas fa-file-csv me-1"></i>CSVエクスポート
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-4 mb-3">
    <div class="card h-100">
      <div class="card-body text-center">
        <h6 class="text-muted">MRR</h6>
        <h3 class="mb-0">{{ number_format($mrr['total']) }}円</h3>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card h-100">
      <div class="card-body text-center">
        <h6 class="text-muted">チャーン率</h6>
        <h3 class="mb-0">{{ $churnData['rate'] }}%</h3>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card h-100">
      <div class="card-body text-center">
        <h6 class="text-muted">LTV</h6>
        <h3 class="mb-0">{{ number_format($ltv['ltv']) }}円</h3>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">売上推移</h5>
      </div>
      <div class="card-body">
        <canvas id="revenueChart" height="200"></canvas>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: @json(array_column($revenueData, 'month')),
        datasets: [{
          label: '売上',
          data: @json(array_column($revenueData, 'revenue')),
          borderColor: '#3b5b7a',
          backgroundColor: 'rgba(59, 91, 122, 0.1)',
          tension: 0.3
        }]
      },
      options: {
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { display: false } }
      }
    });
  });
</script>
@endsection
