<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\PaymentTransaction;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\StripeService;

class BillingController extends Controller
{
  protected StripeService $stripeService;

  public function __construct(StripeService $stripeService)
  {
    $this->middleware('auth:admin');
    $this->stripeService = $stripeService;
  }

  /**
   * 決済ダッシュボード
   */
  public function dashboard()
  {
    $thisMonth = now()->startOfMonth();
    $lastMonth = now()->subMonth()->startOfMonth();

    $stats = [
      'monthly_revenue' => PaymentTransaction::succeeded()
        ->where('created_at', '>=', $thisMonth)
        ->sum('amount') * 100, // 現在decimal保存のため×100
      'last_month_revenue' => PaymentTransaction::succeeded()
        ->whereBetween('created_at', [$lastMonth, $thisMonth])
        ->sum('amount') * 100, // 現在decimal保存のため×100
      'active_subscriptions' => Subscription::where('status', 'active')->count(),
      'new_subscriptions_this_month' => Subscription::where('created_at', '>=', $thisMonth)->count(),
      'canceled_subscriptions_this_month' => Subscription::where('status', 'canceled')
        ->where('updated_at', '>=', $thisMonth)
        ->count(),
      'total_revenue' => PaymentTransaction::succeeded()->sum('amount') * 100, // 現在decimal保存のため×100
    ];

    // 売上成長率の計算
    $stats['growth_rate'] = $stats['last_month_revenue'] > 0
      ? round((($stats['monthly_revenue'] - $stats['last_month_revenue']) / $stats['last_month_revenue']) * 100, 1)
      : 0;

    $planStats = Subscription::select('plan', DB::raw('count(*) as count'))
      ->where('status', 'active')
      ->groupBy('plan')
      ->get();

    // Chart.js用のプランデータを準備
    $planChartData = [
      'labels' => $planStats->pluck('plan')->toArray(),
      'data' => $planStats->pluck('count')->toArray(),
    ];

    // 月別売上推移（過去12ヶ月）
    $monthlyRevenue = [];
    for ($i = 11; $i >= 0; $i--) {
      $month = Carbon::now()->subMonths($i);
      $revenue = PaymentTransaction::succeeded()
        ->whereYear('created_at', $month->year)
        ->whereMonth('created_at', $month->month)
        ->sum('amount') * 100; // 現在decimal保存のため×100

      $monthlyRevenue[] = [
        'month' => $month->format('Y-m'),
        'revenue' => $revenue,
        'formatted_month' => $month->format('Y年n月'),
      ];
    }

    // Chart.js用の売上データを準備
    $revenueChartData = [
      'labels' => array_column($monthlyRevenue, 'formatted_month'),
      'data' => array_column($monthlyRevenue, 'revenue'),
    ];

    // 最近のWebhookエラー
    $recentWebhookErrors = WebhookLog::failed()
      ->recent(7)
      ->orderBy('created_at', 'desc')
      ->limit(5)
      ->get();

    return view('admin.billing.dashboard', [
      'stats' => $stats,
      'planStats' => $planStats,
      'planChartData' => $planChartData,
      'monthlyRevenue' => $monthlyRevenue,
      'revenueChartData' => $revenueChartData,
      'recentWebhookErrors' => $recentWebhookErrors,
    ]);
  }

  /**
   * サブスクリプション一覧
   */
  public function index(Request $request)
  {
    $query = Subscription::with(['user' => function ($query) {
      $query->withTrashed()->select('id', 'name', 'email', 'deleted_at');
    }]);

    // フィルタリング
    if ($status = $request->get('status')) {
      $query->where('status', $status);
    }
    if ($plan = $request->get('plan')) {
      $query->where('plan', $plan);
    }
    if ($search = $request->get('search')) {
      $query->whereHas('user', function ($q) use ($search) {
        $q->withTrashed()
          ->where('name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
      });
    }

    $subscriptions = $query->orderByDesc('created_at')->paginate(20);
    $subscriptions->appends($request->query());

    // ステータス別カウント
    $statusCounts = [
      'all' => Subscription::count(),
      'active' => Subscription::where('status', 'active')->count(),
      'canceled' => Subscription::where('status', 'canceled')->count(),
      'past_due' => Subscription::where('status', 'past_due')->count(),
    ];

    return view('admin.billing.subscriptions.index', compact('subscriptions', 'statusCounts'));
  }

  /**
   * サブスクリプション詳細
   */
  public function show($id)
  {
    $subscription = Subscription::with([
      'user' => function ($query) {
        $query->withTrashed();
      },
      'paymentTransactions' => function ($query) {
        $query->orderBy('created_at', 'desc')->limit(10);
      }
    ])->findOrFail($id);

    // サブスクリプション履歴
    $history = \App\Models\SubscriptionHistory::where('user_id', $subscription->user_id)
      ->orderBy('created_at', 'desc')
      ->limit(20)
      ->get();

    return view('admin.billing.subscriptions.show', compact('subscription', 'history'));
  }

  /**
   * サブスクリプションキャンセル
   */
  public function cancelSubscription($id)
  {
    $subscription = Subscription::findOrFail($id);

    // 既にキャンセルされている場合のチェック
    if ($subscription->status === 'canceled') {
      return redirect()->back()->with('error', 'このサブスクリプションは既にキャンセルされています。');
    }

    try {
      $this->stripeService->cancelSubscriptionAdmin($subscription->stripe_subscription_id);
      $subscription->update(['status' => 'canceled']);
      return redirect()->back()->with('success', 'サブスクリプションをキャンセルしました。');
    } catch (\Exception $e) {
      Log::error('Subscription cancellation failed', ['subscription_id' => $id, 'error' => $e->getMessage()]);
      return redirect()->back()->with('error', 'キャンセル処理に失敗しました。');
    }
  }

  /**
   * サブスクリプション再開
   */
  public function resumeSubscription($id)
  {
    $subscription = Subscription::findOrFail($id);

    // 既にアクティブな場合のチェック
    if ($subscription->status === 'active') {
      return redirect()->back()->with('error', 'このサブスクリプションは既にアクティブです。');
    }

    try {
      $this->stripeService->resumeSubscriptionAdmin($subscription->stripe_subscription_id);
      $subscription->update(['status' => 'active']);
      return redirect()->back()->with('success', 'サブスクリプションを再開しました。');
    } catch (\Exception $e) {
      Log::error('Subscription resumption failed', ['subscription_id' => $id, 'error' => $e->getMessage()]);
      return redirect()->back()->with('error', '再開処理に失敗しました。');
    }
  }

  /**
   * 決済履歴一覧
   */
  public function payments(Request $request)
  {
    $query = PaymentTransaction::with([
      'user' => function ($query) {
        $query->withTrashed()->select('id', 'name', 'email', 'deleted_at');
      },
      'subscription:id,plan'
    ]);

    // フィルタリング
    if ($status = $request->get('status')) {
      $query->where('status', $status);
    }
    if ($type = $request->get('type')) {
      $query->where('type', $type);
    }
    if ($from = $request->get('from')) {
      $query->whereDate('created_at', '>=', $from);
    }
    if ($to = $request->get('to')) {
      $query->whereDate('created_at', '<=', $to);
    }

    $payments = $query->orderByDesc('created_at')->paginate(20);
    $payments->appends($request->query());

    // ステータス別カウント
    $statusCounts = [
      'all' => PaymentTransaction::count(),
      'succeeded' => PaymentTransaction::where('status', 'succeeded')->count(),
      'failed' => PaymentTransaction::where('status', 'failed')->count(),
      'refunded' => PaymentTransaction::where('status', 'refunded')->count(),
    ];

    return view('admin.billing.payments.index', compact('payments', 'statusCounts'));
  }

  /**
   * 決済詳細
   */
  public function showPayment($id)
  {
    $payment = PaymentTransaction::with([
      'user' => function ($query) {
        $query->withTrashed();
      },
      'subscription'
    ])->findOrFail($id);

    return view('admin.billing.payments.show', compact('payment'));
  }

  /**
   * 決済履歴をCSVエクスポート
   */
  public function exportPayments(Request $request)
  {
    $query = PaymentTransaction::with([
      'user' => function ($query) {
        $query->withTrashed()->select('id', 'name', 'email', 'deleted_at');
      },
      'subscription:id,plan'
    ]);

    if ($status = $request->get('status')) {
      $query->where('status', $status);
    }
    if ($type = $request->get('type')) {
      $query->where('type', $type);
    }
    if ($from = $request->get('from')) {
      $query->whereDate('created_at', '>=', $from);
    }
    if ($to = $request->get('to')) {
      $query->whereDate('created_at', '<=', $to);
    }

    if (!$query->exists()) {
      return redirect()->back()->with('error', 'エクスポートするデータがありません。');
    }

    $filename = 'payments_' . date('Y-m-d') . '.csv';

    return response()->streamDownload(function () use ($query) {
      $handle = fopen('php://output', 'w');
      fwrite($handle, "\xEF\xBB\xBF");
      fputcsv($handle, ['ID', 'ユーザー', 'タイプ', '金額', 'ステータス', '支払日']);
      $query->orderByDesc('created_at')->chunk(1000, function ($payments) use ($handle) {
        foreach ($payments as $p) {
          fputcsv($handle, [
            $p->id,
            $p->user ? $p->user->name : '削除されたユーザー',
            $p->plan_at_payment ? strtoupper($p->plan_at_payment) : $p->type,
            $p->amount * 100, // 現在decimal保存のため×100
            $p->status,
            optional($p->paid_at)->format('Y-m-d H:i:s'),
          ]);
        }
      });
      fclose($handle);
    }, $filename);
  }

  /**
   * Webhook ログ一覧
   */
  public function webhooks(Request $request)
  {
    $query = WebhookLog::query();

    // フィルタリング
    if ($eventType = $request->get('event_type')) {
      $query->where('event_type', $eventType);
    }
    if ($status = $request->get('status')) {
      $query->where('status', $status);
    }

    $webhooks = $query->orderByDesc('created_at')->paginate(20);
    $webhooks->appends($request->query());

    // イベントタイプ一覧（フィルタ用）
    $eventTypes = WebhookLog::distinct('event_type')
      ->orderBy('event_type')
      ->pluck('event_type');

    // ステータス別カウント
    $statusCounts = [
      'all' => WebhookLog::count(),
      'processed' => WebhookLog::where('status', 'processed')->count(),
      'failed' => WebhookLog::where('status', 'failed')->count(),
      'pending' => WebhookLog::where('status', 'pending')->count(),
    ];

    return view('admin.billing.webhooks.index', compact('webhooks', 'eventTypes', 'statusCounts'));
  }

  /**
   * Webhook 詳細
   */
  public function showWebhook($id)
  {
    $webhook = WebhookLog::findOrFail($id);

    return view('admin.billing.webhooks.show', compact('webhook'));
  }

  /**
   * 分析・レポート
   */
  public function analytics(Request $request)
  {
    $period = $request->get('period', '12months');
    $data = $this->getRevenueData($period);

    // 改善されたMRR計算（Monthly Recurring Revenue）
    $mrr = $this->calculateMRR();

    // 改善されたチャーン率計算
    $churnData = $this->calculateChurnRate();

    // 顧客生涯価値の計算
    $ltv = $this->calculateLTV();

    return view('admin.billing.analytics.index', [
      'revenueData' => $data,
      'mrr' => $mrr,
      'churnData' => $churnData,
      'ltv' => $ltv,
      'period' => $period,
    ]);
  }

  /**
   * 分析データエクスポート
   */
  public function exportAnalytics(Request $request)
  {
    $period = $request->get('period', '12months');
    $data = $this->getRevenueData($period);

    if (empty($data)) {
      return redirect()->back()->with('error', 'エクスポートするデータがありません。');
    }

    $filename = 'analytics_' . date('Y-m-d') . '.csv';

    return response()->streamDownload(function () use ($data) {
      $handle = fopen('php://output', 'w');

      // BOMを追加してExcelで文字化けを防ぐ
      fwrite($handle, "\xEF\xBB\xBF");

      fputcsv($handle, ['月', '売上']);
      foreach ($data as $row) {
        fputcsv($handle, [$row['month'], $row['revenue']]);
      }
      fclose($handle);
    }, $filename);
  }

  /**
   * 期間別売上データを取得
   */
  private function getRevenueData(string $period): array
  {
    $months = match ($period) {
      '3months' => 3,
      '6months' => 6,
      '12months' => 12,
      default => 12,
    };

    return PaymentTransaction::succeeded()
      ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('SUM(amount) as revenue'))
      ->where('created_at', '>=', now()->subMonths($months))
      ->groupBy('month')
      ->orderBy('month')
      ->get()
      ->map(function ($item) {
        return [
          'month' => $item->month,
          'revenue' => (float) $item->revenue * 100, // 現在decimal保存のため×100
        ];
      })
      ->toArray();
  }

  /**
   * MRR（月次経常収益）を計算
   */
  private function calculateMRR(): array
  {
    $activeSubscriptions = Subscription::where('status', 'active')
      ->select('plan', DB::raw('count(*) as count'))
      ->groupBy('plan')
      ->get();

    $totalMRR = 0;
    $mrrByPlan = [];

    foreach ($activeSubscriptions as $subscription) {
      $monthlyPrice = match ($subscription->plan) {
        'standard' => 30,
        'premium' => 60,
        default => 0,
      };

      $planMRR = $monthlyPrice * $subscription->count;
      $totalMRR += $planMRR;

      $mrrByPlan[$subscription->plan] = [
        'count' => $subscription->count,
        'price' => $monthlyPrice,
        'mrr' => $planMRR,
      ];
    }

    return [
      'total' => $totalMRR,
      'by_plan' => $mrrByPlan,
    ];
  }

  /**
   * チャーン率を計算
   */
  private function calculateChurnRate(): array
  {
    $thisMonth = now()->startOfMonth();

    $totalSubscriptions = Subscription::count();
    $canceledThisMonth = Subscription::where('status', 'canceled')
      ->where('updated_at', '>=', $thisMonth)
      ->count();

    $churnRate = $totalSubscriptions > 0
      ? round(($canceledThisMonth / $totalSubscriptions) * 100, 2)
      : 0;

    return [
      'rate' => $churnRate,
      'canceled_count' => $canceledThisMonth,
      'total_subscriptions' => $totalSubscriptions,
    ];
  }

  /**
   * LTV（顧客生涯価値）を計算
   */
  private function calculateLTV(): array
  {
    $mrr = $this->calculateMRR();
    $churnData = $this->calculateChurnRate();

    // 平均月次収益
    $avgMonthlyRevenue = $mrr['total'] > 0 && count($mrr['by_plan']) > 0
      ? $mrr['total'] / array_sum(array_column($mrr['by_plan'], 'count'))
      : 0;

    // LTV = 平均月次収益 / チャーン率(月次)
    $monthlyChurnRate = $churnData['rate'] / 100;
    $ltv = $monthlyChurnRate > 0 ? round($avgMonthlyRevenue / $monthlyChurnRate, 0) : 0;

    return [
      'ltv' => $ltv,
      'avg_monthly_revenue' => round($avgMonthlyRevenue, 0),
      'monthly_churn_rate' => $churnData['rate'],
    ];
  }
}
