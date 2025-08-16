<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>管理者ログイン - Admin Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-card {
      background: white;
      border-radius: 0;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      max-width: 400px;
      width: 100%;
    }

    .login-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      text-align: center;
      padding: 2rem;
    }

    .login-body {
      padding: 2rem;
    }

    .form-control {
      border-radius: 0;
      border: 2px solid #f0f0f0;
      padding: 12px 15px;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn-login {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      border-radius: 0;
      padding: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-login:hover {
      background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
      transform: translateY(-2px);
    }

    .input-group-text {
      border: 2px solid #f0f0f0;
      border-right: none;
      background: #f8f9fa;
      border-radius: 0;
    }

    .input-group .form-control {
      border-left: none;
      border-radius: 0;
    }

    .form-check-input:checked {
      background-color: #667eea;
      border-color: #667eea;
    }
  </style>
</head>

<body>
  <div class="login-card">
    <div class="login-header">
      <h3 class="mb-0">
        <i class="fas fa-lock me-2"></i>
        管理者ログイン
      </h3>
    </div>

    <div class="login-body">
      @if ($errors->any())
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        @foreach ($errors->all() as $error)
        {{ $error }}
        @endforeach
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      @endif

      <form method="POST" action="{{ route('admin.login') }}">
        @csrf

        <div class="mb-3">
          <label for="email" class="form-label">メールアドレス</label>
          <div class="input-group">
            <span class="input-group-text">
              <i class="fas fa-envelope"></i>
            </span>
            <input type="email"
              class="form-control @error('email') is-invalid @enderror"
              id="email"
              name="email"
              value="{{ old('email') }}"
              required
              autofocus
              placeholder="admin@example.com">
          </div>
          @error('email')
          <div class="invalid-feedback d-block">
            {{ $message }}
          </div>
          @enderror
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">パスワード</label>
          <div class="input-group">
            <span class="input-group-text">
              <i class="fas fa-lock"></i>
            </span>
            <input type="password"
              class="form-control @error('password') is-invalid @enderror"
              id="password"
              name="password"
              required
              placeholder="パスワードを入力">
          </div>
          @error('password')
          <div class="invalid-feedback d-block">
            {{ $message }}
          </div>
          @enderror
        </div>

        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="remember" name="remember">
          <label class="form-check-label" for="remember">
            ログイン状態を保持する
          </label>
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-primary btn-login text-white">
            <i class="fas fa-sign-in-alt me-2"></i>
            ログイン
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>