<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 認証済みユーザーのみチェック
        if ($request->user()) {
            $user = $request->user();

            // ユーザーが削除されている場合
            if ($user->isDeleted()) {
                // トークンを削除してログアウト状態にする
                $user->tokens()->delete();
                
                return response()->json([
                    'status' => 'error',
                    'error_type' => 'account_deleted',
                    'message' => 'このアカウントは削除されています。'
                ], 403);
            }

            // ユーザーがバンされている場合
            if ($user->isBanned()) {
                // トークンを削除してログアウト状態にする
                $user->tokens()->delete();
                
                return response()->json([
                    'status' => 'error',
                    'error_type' => 'account_banned',
                    'message' => 'このアカウントは利用停止されています。'
                ], 403);
            }
        }

        return $next($request);
    }
}