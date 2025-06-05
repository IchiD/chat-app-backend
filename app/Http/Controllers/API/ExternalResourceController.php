<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\UrlCheckerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ExternalResourceController extends Controller
{
  private UrlCheckerService $checker;

  public function __construct(UrlCheckerService $checker)
  {
    $this->checker = $checker;
  }

  public function fetch(Request $request)
  {
    $request->validate(['url' => 'required|string']);
    $url = $request->input('url');

    if (!$this->checker->isAllowed($url)) {
      return response()->json(['message' => 'blocked'], 422);
    }

    $currentUrl = $url;
    for ($i = 0; $i < 3; $i++) {
      if (!$this->checker->isAllowed($currentUrl)) {
        return response()->json(['message' => 'blocked'], 422);
      }
      $response = Http::withoutRedirecting()->get($currentUrl);
      if ($response->status() >= 300 && $response->status() < 400 && $response->header('Location')) {
        $currentUrl = $response->header('Location');
        continue;
      }
      return response()->json(['status' => 'ok', 'final_url' => $currentUrl]);
    }

    return response()->json(['message' => 'redirect limit'], 422);
  }
}
