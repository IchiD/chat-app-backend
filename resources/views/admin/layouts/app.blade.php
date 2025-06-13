<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', '管理画面') - Admin Panel</title>

  <!-- Favicon設定 -->
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
  <link rel="icon" type="image/png" href="{{ asset('admin-favicon.png') }}" sizes="32x32">
  <link rel="apple-touch-icon" href="{{ asset('admin-apple-touch-icon.png') }}" sizes="180x180">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    /* サイドバー・ヘッダー */
    .sidebar {
      min-height: 100vh;
      background: #343a40;
      color: #fff;
    }

    .sidebar .nav-link {
      color: rgba(255, 255, 255, 0.75);
      transition: all 0.3s ease;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
      color: #fff;
      background-color: #3b5b7a;
      border-radius: 0.25rem;
    }

    .navbar-custom {
      background: #343a40;
      color: #fff;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
      border-bottom: 1px solid #dee2e6;
    }

    .main-content {
      background-color: #f8f9fa;
      min-height: 100vh;
    }

    .card {
      border: 1px solid #e9ecef;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
      border-radius: 0.375rem;
    }

    /* ボタン・リンクのアクセント */
    .btn-primary,
    .badge-accent {
      background: #3b5b7a !important;
      color: #fff !important;
      border: none;
    }

    .btn-primary:hover,
    .badge-accent:hover {
      background: #2d415a !important;
    }

    /* 警告・削除 */
    .btn-danger,
    .badge-danger {
      background: #c0392b !important;
      color: #fff !important;
      border: none;
    }

    /* 一般バッジ */
    .badge,
    .badge-secondary {
      background: #6c757d !important;
      color: #fff !important;
    }

    /* 5パターンのバッジ色 */
    .badge-superadmin {
      background: #2c5282 !important;
      /* 深い青 - スーパーアドミン（最高権限） */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-admin {
      background: #6c757d !important;
      /* グレー - アドミン（通常権限） */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-success {
      background: #2d5016 !important;
      /* 深い緑 - 成功・承認・有効系 */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-special {
      background: #553c9a !important;
      /* 深い紫 - 特別・プレミアム系 */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-warning {
      background: #9c4221 !important;
      /* 深いオレンジ - 警告・注意系 */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    /* ログイン中バッジ */
    .badge-loggedin {
      background: #553c9a !important;
      /* 深い紫 - 特別状態 */
      color: #fff !important;
      font-weight: 500;
    }

    /* チャットルームタイプ専用バッジ */
    .badge-friend-chat {
      background: #553c9a !important;
      /* 深い紫 - 友達チャット（特別な関係） */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-group-chat {
      background: #2d5016 !important;
      /* 深い緑 - グループチャット（みんなでつながる） */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-member-chat {
      background: #9c4221 !important;
      /* 深いオレンジ - メンバーチャット（注意が必要） */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-support-chat {
      background: #6c757d !important;
      /* グレー - サポートチャット（管理的） */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    /* 認証状態専用バッジ */
    .badge-verified {
      background: #28a745 !important;
      /* 明るい緑 - 認証済み（安心・成功） */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-unverified {
      background: #ffc107 !important;
      /* 黄色 - 未認証（注意が必要） */
      color: #212529 !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-banned {
      background: #dc3545 !important;
      /* 赤 - バン済み（危険・禁止） */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-pending {
      background: #6f42c1 !important;
      /* 紫 - 保留中・審査中 */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-suspended {
      background: #fd7e14 !important;
      /* オレンジ - 一時停止 */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    /* テーブル */
    .table th,
    .table td {
      color: #212529;
    }

    .table-striped>tbody>tr:nth-of-type(odd) {
      background-color: #f8f9fa;
    }

    .table-striped>tbody>tr:nth-of-type(even) {
      background-color: #fcfcfc;
    }

    .table th {
      white-space: nowrap;
      vertical-align: middle;
      font-weight: 600;
      font-size: 0.9rem;
      padding: 0.75rem 0.5rem;
    }

    .table td {
      vertical-align: middle;
      padding: 0.75rem 0.5rem;
    }

    /* メッセージ内容の改行設定 */
    .message-content,
    .message-content div,
    .text-truncate {
      word-break: break-all;
      word-wrap: break-word;
      overflow-wrap: break-word;
      white-space: pre-wrap;
    }

    /* 一般的なメッセージ表示エリアにも適用 */
    .message-item .message-content,
    .message-bubble .message-content,
    .alert .text-muted {
      word-break: break-all;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }

    /* IDバッジ */
    .id-badge {
      color: #c0392b;
      font-weight: bold;
    }

    /* 件数バッジなど */
    .badge-count {
      background: #6c757d !important;
      color: #fff !important;
    }

    /* ページネーション */
    .pagination {
      margin-bottom: 0;
    }

    .pagination .page-link {
      padding: 0.375rem 0.75rem;
      color: #495057;
      background-color: #fff;
      border: 1px solid #dee2e6;
      text-decoration: none;
      transition: all 0.15s ease-in-out;
    }

    .pagination .page-item.active .page-link {
      background-color: #3b5b7a;
      border-color: #3b5b7a;
      color: #fff;
      z-index: 3;
    }

    .pagination .page-link:hover {
      background-color: #e9ecef;
      border-color: #adb5bd;
      color: #495057;
      text-decoration: none;
    }

    .pagination .page-item.active .page-link:hover {
      background-color: #3b5b7a;
      border-color: #3b5b7a;
      color: #fff;
    }

    .pagination .page-item.disabled .page-link {
      color: #6c757d;
      background-color: #fff;
      border-color: #dee2e6;
      pointer-events: none;
    }

    .pagination .page-item:first-child .page-link {
      border-top-left-radius: 0.375rem;
      border-bottom-left-radius: 0.375rem;
    }

    .pagination .page-item:last-child .page-link {
      border-top-right-radius: 0.375rem;
      border-bottom-right-radius: 0.375rem;
    }

    /* レスポンシブテーブルの改善 */
    .table-responsive {
      border-radius: 0.375rem;
    }

    /* 参加者バッジの調整 */
    .badge.bg-light {
      background-color: #f8f9fa !important;
      color: #495057 !important;
      border: 1px solid #dee2e6 !important;
    }

    /* ヘッダー右上のユーザー名・アイコン */
    .navbar-custom .nav-link,
    .navbar-custom .nav-link .fas {
      color: #fff !important;
    }

    /* ユーザー名のテキストを太字で強調 */
    .navbar-custom .nav-link .user-name {
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.01em;
    }

    /* ナビゲーションバーのバッジ */
    .navbar-custom .badge-superadmin {
      background: #2c5282 !important;
      /* 深い青 - スーパーアドミン */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
    }

    .navbar-custom .badge-admin {
      background: #6c757d !important;
      /* グレー - アドミン */
      color: #fff !important;
      font-weight: 600;
      letter-spacing: 0.03em;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
    }

    /* ドロップダウン矢印 */
    .navbar-custom .dropdown-toggle::after {
      border-top-color: #fff !important;
    }
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <!-- サイドバー -->
      <div class="col-md-3 col-lg-2 sidebar px-0">
        <div class="p-3">
          <h4 class="text-white text-center mb-4">
            <i class="fas fa-cogs"></i> 管理画面
          </h4>
          <nav class="nav flex-column">
            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
              <i class="fas fa-tachometer-alt me-2"></i> ダッシュボード
            </a>
            <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}">
              <i class="fas fa-users me-2"></i> ユーザー
            </a>
            <a href="{{ route('admin.conversations') }}" class="nav-link {{ request()->routeIs('admin.conversations*') ? 'active' : '' }}">
              <i class="fas fa-comments me-2"></i> チャットルーム
            </a>
            <a href="{{ route('admin.groups') }}" class="nav-link {{ request()->routeIs('admin.groups*') ? 'active' : '' }}">
              <i class="fas fa-users-gear me-2"></i> グループ
            </a>
            <a href="{{ route('admin.support') }}" class="nav-link {{ request()->routeIs('admin.support*') ? 'active' : '' }}">
              <i class="fas fa-comments me-2"></i> お問い合わせ
              <span id="unread-support-badge" class="badge bg-danger rounded-circle ms-2" style="display: none;">0</span>
            </a>
            @if(auth('admin')->user() && auth('admin')->user()->isSuperAdmin())
            <a href="{{ route('admin.admins') }}" class="nav-link {{ request()->routeIs('admin.admins') ? 'active' : '' }}">
              <i class="fas fa-user-shield me-2"></i> 管理者
            </a>
            @endif
          </nav>
        </div>
      </div>

      <!-- メインコンテンツ -->
      <div class="col-md-9 col-lg-10 main-content px-0">
        <!-- トップナビゲーション -->
        <nav class="navbar navbar-expand-lg navbar-custom px-3">
          <div class="navbar-nav ms-auto">
            <div class="nav-item dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle me-2"></i>
                {{ auth('admin')->user()->name ?? 'Admin' }}
                @if(auth('admin')->user() && auth('admin')->user()->isSuperAdmin())
                <span class="badge badge-superadmin ms-2">Super Admin</span>
                @else
                <span class="badge badge-admin ms-2">Admin</span>
                @endif
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="dropdown-item">
                      <i class="fas fa-sign-out-alt me-2"></i> ログアウト
                    </button>
                  </form>
                </li>
              </ul>
            </div>
          </div>
        </nav>

        <!-- ページコンテンツ -->
        <div class="p-4">
          @if(session('success'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          @endif

          @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          @endif

          @yield('content')
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // 新着サポートメッセージ数を取得する関数
    function updateUnreadSupportCount() {
      fetch('{{ route("admin.support.unread-count") }}', {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          const badge = document.getElementById('unread-support-badge');
          if (data.unread_count > 0) {
            badge.textContent = data.unread_count;
            badge.style.display = 'inline-block';
          } else {
            badge.style.display = 'none';
          }
        })
        .catch(error => {
          console.error('Error fetching unread support count:', error);
        });
    }

    // ページ読み込み時に新着数を取得
    document.addEventListener('DOMContentLoaded', function() {
      updateUnreadSupportCount();

      // 30秒ごとに新着数を更新
      setInterval(updateUnreadSupportCount, 30000);
    });
  </script>

  @yield('scripts')
</body>

</html>