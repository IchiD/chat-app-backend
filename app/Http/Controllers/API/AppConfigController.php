<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
  /**
   * アプリケーションの公開設定情報を取得
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPublicConfig(): JsonResponse
  {
    return response()->json([
      'vapid' => [
        'publicKey' => config('webpush.vapid.public_key')
      ],
      'env' => [
        'app_name' => config('app.name'),
        'app_url' => config('app.url'),
        'app_env' => config('app.env')
      ]
    ]);
  }
}
