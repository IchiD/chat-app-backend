<?php

namespace App\Services;

use App\Models\OperationLog;

class OperationLogService
{
  public static function log(string $category, string $action, ?string $description = null): void
  {
    OperationLog::create([
      'category' => $category,
      'action' => $action,
      'description' => $description,
    ]);

    // 古いログを3000件に保つ
    self::trimLogs($category);
  }

  private static function trimLogs(string $category): void
  {
    $logs = OperationLog::where('category', $category)
      ->orderBy('created_at', 'desc')
      ->skip(3000)
      ->take(PHP_INT_MAX)
      ->get();
    foreach ($logs as $log) {
      $log->delete();
    }
  }
}
