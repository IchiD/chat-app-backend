@extends('admin.layouts.app')

@section('title', 'ダッシュボード')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <h1 class="h3 mb-0">
      <i class="fas fa-tachometer-alt me-2"></i>ダッシュボード
    </h1>
    <p class="text-muted">システムの概要と管理機能</p>
  </div>
</div>

<!-- 統計カード -->
<div class="row mb-4">
  <div class="col-md-6 col-lg-3 mb-3">
    <div class="card h-100 border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0;">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="rounded-circle bg-primary bg-opacity-10 p-2">
            <i class="fas fa-users text-primary"></i>
          </div>
          <h3 class="mb-0 text-primary fw-bold">{{ number_format($userCount) }}</h3>
        </div>
        <h6 class="text-dark mb-1">総ユーザー数</h6>
        <p class="text-muted small mb-0">登録済みユーザー</p>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-lg-3 mb-3">
    <div class="card h-100 border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0;">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="rounded-circle bg-success bg-opacity-10 p-2">
            <i class="fas fa-comments text-success"></i>
          </div>
          <h3 class="mb-0 text-success fw-bold">{{ number_format($chatRoomCount) }}</h3>
        </div>
        <h6 class="text-dark mb-1">チャットルーム数</h6>
        <p class="text-muted small mb-0">作成済みルーム</p>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-lg-3 mb-3">
    <div class="card h-100 border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0;">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="rounded-circle bg-warning bg-opacity-10 p-2">
            <i class="fas fa-user-clock text-warning"></i>
          </div>
          <h3 class="mb-0 text-warning fw-bold">{{ number_format($todayActiveUsersCount) }}</h3>
        </div>
        <h6 class="text-dark mb-1">本日のアクティブユーザー数</h6>
        <p class="text-muted small mb-0">本日メッセージ送信</p>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-lg-3 mb-3">
    <div class="card h-100 border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0;">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="rounded-circle bg-info bg-opacity-10 p-2">
            <i class="fas fa-envelope text-info"></i>
          </div>
          <h3 class="mb-0 text-info fw-bold">{{ number_format($todayMessagesCount) }}</h3>
        </div>
        <h6 class="text-dark mb-1">本日送信されたメッセージ数</h6>
        <p class="text-muted small mb-0">本日の投稿</p>
      </div>
    </div>
  </div>
</div>

<!-- システム状態一覧 -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card border-0" style="box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 0;">
      <div class="card-header bg-transparent border-0 pb-0">
        <h5 class="card-title mb-0">
          <i class="fas fa-server me-2"></i>システム状態監視
        </h5>
        <p class="text-muted small mb-0">リアルタイムでシステムの状態を監視しています</p>
      </div>
      <div class="card-body pt-3">
        <div class="row">
          @foreach($systemStatus as $key => $item)
          <div class="col-md-6 col-lg-4 mb-3">
            <div class="d-flex align-items-start">
              <div class="flex-shrink-0 me-3">
                @if($item['status'] === 'success')
                <div class="rounded-circle bg-success bg-opacity-10 p-2">
                  <i class="fas fa-check text-success"></i>
                </div>
                @elseif($item['status'] === 'warning')
                <div class="rounded-circle bg-warning bg-opacity-10 p-2">
                  <i class="fas fa-exclamation-triangle text-warning"></i>
                </div>
                @else
                <div class="rounded-circle bg-danger bg-opacity-10 p-2">
                  <i class="fas fa-times text-danger"></i>
                </div>
                @endif
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-1 fw-semibold">{{ $item['name'] }}</h6>
                <p class="text-muted small mb-0" style="line-height: 1.4;">{{ $item['message'] }}</p>
                <span class="badge 
                  @if($item['status'] === 'success') bg-success 
                  @elseif($item['status'] === 'warning') bg-warning 
                  @else bg-danger @endif
                  bg-opacity-10 
                  @if($item['status'] === 'success') text-success 
                  @elseif($item['status'] === 'warning') text-warning 
                  @else text-danger @endif
                  mt-1" style="font-size: 0.75rem;">
                  @if($item['status'] === 'success') 正常
                  @elseif($item['status'] === 'warning') 注意
                  @else エラー @endif
                </span>
              </div>
            </div>
          </div>
          @endforeach
        </div>

        <!-- 更新時刻 -->
        <div class="row mt-3">
          <div class="col-12">
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted">
                <i class="fas fa-clock me-1"></i>最終更新: {{ now()->format('Y年m月d日 H:i:s') }}
              </small>
              <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i>更新
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 管理機能 -->
<div class="row mb-4">
  <div class="col-12">
    <h3 class="h5 mb-3">
      <i class="fas fa-tools me-2"></i>管理機能
    </h3>
  </div>
</div>

<div class="row">
  <div class="col-md-6 col-lg-4 mb-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="flex-shrink-0">
            <i class="fas fa-users fa-2x text-primary"></i>
          </div>
          <div class="flex-grow-1 ms-3">
            <h5 class="card-title mb-0">ユーザー管理</h5>
            <p class="text-muted mb-0">ユーザーの管理・編集</p>
          </div>
        </div>
        <a href="{{ route('admin.users') }}" class="btn btn-primary btn-sm">
          <i class="fas fa-arrow-right me-1"></i>管理画面へ
        </a>
      </div>
    </div>
  </div>

  @if($admin->isSuperAdmin())
  <div class="col-md-6 col-lg-4 mb-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="flex-shrink-0">
            <i class="fas fa-user-shield fa-2x text-danger"></i>
          </div>
          <div class="flex-grow-1 ms-3">
            <h5 class="card-title mb-0">アドミン管理</h5>
            <p class="text-muted mb-0">管理者の管理・作成</p>
          </div>
        </div>
        <a href="{{ route('admin.admins') }}" class="btn btn-danger btn-sm">
          <i class="fas fa-arrow-right me-1"></i>管理画面へ
        </a>
      </div>
    </div>
  </div>
  @endif

  <div class="col-md-6 col-lg-4 mb-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="flex-shrink-0">
            <i class="fas fa-comments fa-2x text-info"></i>
          </div>
          <div class="flex-grow-1 ms-3">
            <h5 class="card-title mb-0">お問い合わせ管理</h5>
            <p class="text-muted mb-0">ユーザーからのサポート対応</p>
          </div>
        </div>
        <a href="{{ route('admin.support') }}" class="btn btn-info btn-sm">
          <i class="fas fa-arrow-right me-1"></i>管理画面へ
        </a>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-lg-4 mb-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="flex-shrink-0">
            <i class="fas fa-chart-bar fa-2x text-success"></i>
          </div>
          <div class="flex-grow-1 ms-3">
            <h5 class="card-title mb-0">レポート</h5>
            <p class="text-muted mb-0">システム分析・統計</p>
          </div>
        </div>
        <button class="btn btn-success btn-sm" disabled>
          <i class="fas fa-clock me-1"></i>準備中
        </button>
      </div>
    </div>
  </div>
</div>

<!-- 最近の活動 -->
<div class="row mt-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-history me-2"></i>最近の活動
        </h5>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center py-2">
          <div class="flex-shrink-0">
            <i class="fas fa-user-plus text-success me-3"></i>
          </div>
          <div class="flex-grow-1">
            <strong>新規ユーザー登録</strong>
            <p class="text-muted mb-0">3名の新規ユーザーが登録されました</p>
          </div>
          <div class="flex-shrink-0">
            <small class="text-muted">2時間前</small>
          </div>
        </div>
        <hr>
        <div class="d-flex align-items-center py-2">
          <div class="flex-shrink-0">
            <i class="fas fa-sign-in-alt text-primary me-3"></i>
          </div>
          <div class="flex-grow-1">
            <strong>管理者ログイン</strong>
            <p class="text-muted mb-0">{{ $admin->name }} がログインしました</p>
          </div>
          <div class="flex-shrink-0">
            <small class="text-muted">現在</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection