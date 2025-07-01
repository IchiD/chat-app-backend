<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequirePremiumPlan
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    $user = Auth::user();

    if (!$user) {
      return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // プラン情報がないか、freeプランの場合はアクセス拒否
    if (!$user->plan || $user->plan === 'free') {
      return response()->json([
        'message' => 'This feature requires a premium plan',
        'error' => 'premium_required'
      ], 403);
    }

    return $next($request);
  }
}
