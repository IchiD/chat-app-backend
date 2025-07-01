<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use App\Models\ExternalApiToken;

class ExternalAuthController extends Controller
{
    public function issueToken(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
        ]);

        $configuredId = Config::get('services.external.client_id');
        $configuredSecret = Config::get('services.external.client_secret');

        if ($validated['client_id'] !== $configuredId || $validated['client_secret'] !== $configuredSecret) {
            Log::warning('外部トークン発行失敗: 認証情報不一致', ['ip' => $request->ip()]);
            return response()->json(['message' => 'invalid credentials'], 401);
        }

        $token = ExternalApiToken::generateToken();
        $expiresAt = Carbon::now()->addMinutes(30);

        ExternalApiToken::create([
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        Log::info('外部トークン発行成功', ['client_id' => $validated['client_id']]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresAt->timestamp,
        ]);
    }

    public function verifyToken(Request $request)
    {
        $header = $request->header('Authorization');
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'unauthorized'], 401);
        }
        $tokenString = substr($header, 7);
        $token = ExternalApiToken::where('token', $tokenString)->first();
        if (!$token || $token->isExpired()) {
            return response()->json(['message' => 'invalid token'], 401);
        }

        $token->increment('usage_count');
        $token->update(['last_used_at' => Carbon::now()]);

        Log::info('外部トークン検証成功', ['token_id' => $token->id]);

        return response()->json(['message' => 'valid']);
    }
}
