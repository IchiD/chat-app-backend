<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\PaymentTransaction;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// use App\Services\StripeService;

class BillingController extends Controller
{
    // protected StripeService $stripeService;

    public function __construct(/*StripeService $stripeService*/)
    {
        $this->middleware('auth:admin');
        // $this->stripeService = $stripeService;
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
                ->sum('amount'),
            'last_month_revenue' => PaymentTransaction::succeeded()
                ->whereBetween('created_at', [$lastMonth, $thisMonth])
                ->sum('amount'),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'new_subscriptions_this_month' => Subscription::where('created_at', '>=', $thisMonth)->count(),
            'canceled_subscriptions_this_month' => Subscription::where('status', 'canceled')
                ->where('updated_at', '>=', $thisMonth)
                ->count(),
        ];

        $planStats = Subscription::select('plan', DB::raw('count(*) as count'))
            ->where('status', 'active')
            ->groupBy('plan')
            ->get();

        return view('admin.billing.dashboard', [
            'stats' => $stats,
            'planStats' => $planStats,
        ]);
    }

    /**
     * サブスクリプション一覧
     */
    public function index(Request $request)
    {
        $query = Subscription::with('user');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($plan = $request->get('plan')) {
            $query->where('plan', $plan);
        }
        if ($search = $request->get('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $subscriptions = $query->orderByDesc('created_at')->paginate(20);
        $subscriptions->appends($request->query());

        return view('admin.billing.subscriptions.index', compact('subscriptions'));
    }

    /**
     * サブスクリプション詳細
     */
    public function show($id)
    {
        $subscription = Subscription::with(['user', 'paymentTransactions'])
            ->findOrFail($id);

        return view('admin.billing.subscriptions.show', compact('subscription'));
    }

    /**
     * サブスクリプションキャンセル
     */
    public function cancelSubscription($id)
    {
        // TODO: StripeService の実装待ち
        // $this->stripe->cancelSubscription($id);
        Log::warning('cancelSubscription is temporarily disabled.');
        return redirect()->back()->with('error', '現在、この操作は利用できません。');
    }

    /**
     * サブスクリプション再開
     */
    public function resumeSubscription($id)
    {
        // TODO: StripeService の実装待ち
        // $this->stripe->resumeSubscription($id);
        Log::warning('resumeSubscription is temporarily disabled.');
        return redirect()->back()->with('error', '現在、この操作は利用できません。');
    }

    /**
     * 決済履歴一覧
     */
    public function payments(Request $request)
    {
        $query = PaymentTransaction::with(['user', 'subscription']);

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

        return view('admin.billing.payments.index', compact('payments'));
    }

    /**
     * 決済詳細
     */
    public function showPayment($id)
    {
        $payment = PaymentTransaction::with(['user', 'subscription'])
            ->findOrFail($id);

        return view('admin.billing.payments.show', compact('payment'));
    }

    /**
     * 返金処理
     */
    public function refundPayment($id)
    {
        // TODO: StripeService の実装待ち
        // $payment = PaymentTransaction::findOrFail($id);
        // $this->stripe->refundPayment($payment->stripe_charge_id, $payment->amount);
        Log::warning('refundPayment is temporarily disabled.');
        return redirect()->back()->with('error', '現在、この操作は利用できません。');
    }

    /**
     * Webhook ログ一覧
     */
    public function webhooks(Request $request)
    {
        $query = WebhookLog::query();

        if ($eventType = $request->get('event_type')) {
            $query->where('event_type', $eventType);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $webhooks = $query->orderByDesc('created_at')->paginate(20);
        $webhooks->appends($request->query());

        return view('admin.billing.webhooks.index', compact('webhooks'));
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

        $mrr = PaymentTransaction::succeeded()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');
        $churn = Subscription::where('status', 'canceled')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        return view('admin.billing.analytics.index', [
            'revenueData' => $data,
            'mrr' => $mrr,
            'churn' => $churn,
        ]);
    }

    /**
     * 分析データエクスポート
     */
    public function exportAnalytics(Request $request)
    {
        $period = $request->get('period', '12months');
        $data = $this->getRevenueData($period);

        $filename = 'analytics_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['月', '売上']);
            foreach ($data as $row) {
                fputcsv($handle, [$row['month'], $row['revenue']]);
            }
            fclose($handle);
        }, $filename);
    }

    private function getRevenueData(string $period): array
    {
        $months = match ($period) {
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
            ->toArray();
    }
}

