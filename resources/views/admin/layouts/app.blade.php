<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', '管理画面') - Admin Panel</title>

  <!-- Favicon設定 -->
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
  <link rel="icon" type="image/png" href="{{ asset('admin-favicon.png') }}" sizes="32x32">
  <link rel="apple-touch-icon" href="{{ asset('admin-apple-touch-icon.png') }}" sizes="180x180">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    .sidebar {
      min-height: 100vh;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .sidebar .nav-link {
      color: rgba(255, 255, 255, 0.8);
      transition: all 0.3s ease;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
      color: white;
      background-color: rgba(255, 255, 255, 0.1);
      border-radius: 0;
    }

    .navbar-custom {
      background: white;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .main-content {
      background-color: #f8f9fa;
      min-height: 100vh;
    }

    .card {
      border: none;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      border-radius: 0;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    }

    /* 例外: 丸いままにする要素 */
    .rounded-circle,
    .spinner-border,
    [class*="spinner"],
    .btn-circle {
      border-radius: 50% !important;
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
                <span class="badge bg-danger ms-2">Super Admin</span>
                @else
                <span class="badge bg-primary ms-2">Admin</span>
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