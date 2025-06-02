<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
    then: function () {
      Route::middleware('api')
        ->prefix('api')
        ->group(base_path('routes/api.php'));
    }
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
      'admin' => \App\Http\Middleware\AdminMiddleware::class,
      'check.user.status' => \App\Http\Middleware\CheckUserStatus::class,
    ]);

    // 既存のCSRFミドルウェアをカスタムCSRFミドルウェアに置き換え
    $middleware->web(replace: [
      \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (AuthenticationException $e, Request $request) {
      if ($request->is('api/*') || $request->expectsJson()) {
        return response()->json(['message' => $e->getMessage()], 401);
      }
    });
  })
  ->create();
