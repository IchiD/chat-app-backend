<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
  /**
   * Handle an incoming request.
   */
  public function handle(Request $request, Closure $next)
  {
    /** @var \Illuminate\Http\Response|\Illuminate\Http\JsonResponse $response */
    $response = $next($request);

    $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
    $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; font-src 'self' cdnjs.cloudflare.com");
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->remove('Server');

    return $response;
  }
}
