<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  public function handle(Request $request, Closure $next)
  {
    // 管理画面へのアクセス制限（一時的に無効化してIP確認）
    // $allowedIPs = ['127.0.0.1', '::1', 'localhost']; // 許可するIPアドレス
    // if (!in_array($request->ip(), $allowedIPs)) {
    //     abort(403, 'Access denied from this IP address.');
    // }

    if (!Auth::guard('admin')->check()) {
      return redirect()->route('admin.login');
    }

    return $next($request);
  }
}
