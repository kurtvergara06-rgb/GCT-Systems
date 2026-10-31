<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ScheduledPurchase;

class PurchaseDashboardController extends Controller
{
    public function index()
    {
        return view('Purchase.dashboard-purchase', $this->data());
    }

    public function data(): array
    {
        $activeRequestStatuses = ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery'];

        $requestBase = MaintenanceRequest::query()
            ->where(function ($q) {
                $q->whereNull('pr_no')->orWhere('pr_no', 'not like', '%-P%');
            });

        $maintenanceRequestBase = MaintenanceRequest::query()
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhereIn('source_type', ['Maintenance Request', 'Job Order']);
            })
            ->where(function ($q) {
                $q->whereNull('job_order_no')->orWhere('job_order_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('pr_no')->orWhere(function ($pr) {
                    $pr->where('pr_no', 'not like', 'RST-%')->where('pr_no', 'not like', '%-P%');
                });
            });

        $totalRequests = (clone $requestBase)->whereIn('status', $activeRequestStatuses)->count();
        $forPurchase = (clone $requestBase)->where('status', 'For Purchase')->count();
        $activePurchaseOrders = PurchaseOrder::whereIn('status', ['Ordered', 'For Pick-up', 'For Delivery'])->count();
        $deliveredOrders = PurchaseOrder::whereIn('status', ['Delivered', 'Picked Up'])->count();
        $ordered = PurchaseOrder::where('status', 'Ordered')->count();
        $forPickup = PurchaseOrder::where('status', 'For Pick-up')->count();
        $forDelivery = PurchaseOrder::where('status', 'For Delivery')->count();
        $scheduledPurchases = ScheduledPurchase::where('status', 'Active')->count();

        $recentPurchaseRequests = $maintenanceRequestBase
            ->whereIn('status', ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up'])
            ->latest()
            ->limit(5)
            ->get();

        $recentPurchaseOrders = PurchaseOrder::query()
            ->latest()
            ->limit(5)
            ->get()
            ->each(function (PurchaseOrder $order) {
                $order->setAttribute('supplier', $order->supplier_name);
                $order->setAttribute('total_amount', $order->net_amount);
            });

        // -----------------------------------------------------------------
        // Financial & Fulfillment Metrics
        // -----------------------------------------------------------------
        $totalSpend = (float) PurchaseOrder::sum('net_amount');
        $totalOrdersCount = (int) PurchaseOrder::count();
        $avgOrderValue = $totalOrdersCount > 0 ? (float) ($totalSpend / $totalOrdersCount) : 0.0;

        $totalPipeline = $forPurchase + $ordered + $forPickup + $forDelivery + $deliveredOrders;
        $fulfillmentRate = $totalPipeline > 0 ? (int) round(($deliveredOrders / $totalPipeline) * 100) : 0;

        // -----------------------------------------------------------------
        // Monthly Spend Trend (Last 6 Months)
        // -----------------------------------------------------------------
        $baseMonth = now()->startOfMonth();
        $monthlyRange = collect(range(5, 0))->map(function ($offset) use ($baseMonth) {
            $date = $baseMonth->copy()->subMonths($offset);
            return [
                'key' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'short_label' => $date->format('M'),
            ];
        });

        $monthlyPoData = PurchaseOrder::query()
            ->selectRaw("DATE_FORMAT(COALESCE(po_date, created_at), '%Y-%m') as ym, COUNT(*) as order_count, SUM(net_amount) as total_spend")
            ->whereRaw("COALESCE(po_date, created_at) >= ?", [$baseMonth->copy()->subMonths(5)->toDateTimeString()])
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $spendTrendMonthly = $monthlyRange->map(function ($m) use ($monthlyPoData) {
            $row = $monthlyPoData->get($m['key']);
            return [
                'label' => $m['short_label'],
                'full_label' => $m['label'],
                'spend' => (float) ($row?->total_spend ?? 0),
                'orders' => (int) ($row?->order_count ?? 0),
            ];
        })->values()->all();

        // -----------------------------------------------------------------
        // Weekly Spend Trend (Last 6 Weeks)
        // -----------------------------------------------------------------
        $weeklyRange = collect(range(5, 0))->map(function ($offset) {
            $end = now()->subWeeks($offset)->endOfWeek();
            $start = now()->subWeeks($offset)->startOfWeek();
            return [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $start->format('M j') . ' - ' . $end->format('M j'),
                'short_label' => 'Wk ' . $start->format('W'),
            ];
        });

        $allRecentOrders = PurchaseOrder::query()
            ->select('id', 'po_no', 'po_date', 'created_at', 'net_amount')
            ->get();

        $spendTrendWeekly = $weeklyRange->map(function ($w) use ($allRecentOrders) {
            $matching = $allRecentOrders->filter(function ($po) use ($w) {
                $d = $po->po_date?->toDateString() ?: $po->created_at?->toDateString();
                return $d >= $w['start'] && $d <= $w['end'];
            });

            return [
                'label' => $w['short_label'],
                'full_label' => $w['label'],
                'spend' => (float) $matching->sum('net_amount'),
                'orders' => $matching->count(),
            ];
        })->values()->all();

        // -----------------------------------------------------------------
        // Order Fulfillment Distribution
        // -----------------------------------------------------------------
        $fulfillmentDistribution = [
            'labels' => ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered'],
            'values' => [
                $forPurchase,
                $ordered,
                $forPickup,
                $forDelivery,
                $deliveredOrders,
            ],
            'colors' => ['#f59e0b', '#3b82f6', '#8b5cf6', '#06b6d4', '#10b981'],
        ];

        // -----------------------------------------------------------------
        // Top Suppliers & Procurement Sources
        // -----------------------------------------------------------------
        $topSuppliers = PurchaseOrder::query()
            ->selectRaw('supplier_name, COUNT(*) as orders_count, SUM(net_amount) as total_spend')
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '!=', '')
            ->groupBy('supplier_name')
            ->orderByDesc('total_spend')
            ->limit(5)
            ->get()
            ->map(function ($s) use ($totalSpend) {
                $spend = (float) $s->total_spend;
                $share = $totalSpend > 0 ? round(($spend / $totalSpend) * 100) : 0;
                return [
                    'name' => $s->supplier_name,
                    'orders' => (int) $s->orders_count,
                    'spend' => $spend,
                    'share' => $share,
                ];
            });

        // Requisition source breakdown
        $joRequestsCount = (clone $maintenanceRequestBase)->count();
        $restockRequestsCount = MaintenanceRequest::query()
            ->where(function ($q) {
                $q->where('source_type', 'Auto Restock')
                    ->orWhere('job_order_no', 'RESTOCK')
                    ->orWhere('pr_no', 'like', 'RST-%');
            })
            ->count();
        $activeSchedulesCount = ScheduledPurchase::where('status', 'Active')->count();
        $scheduledDueSoon = ScheduledPurchase::where('status', 'Active')
            ->where('next_purchase_date', '<=', now()->addDays(7)->endOfDay())
            ->count();
        $totalEstScheduledSpend = (float) ScheduledPurchase::where('status', 'Active')->sum('estimated_cost');

        return compact(
            'totalRequests',
            'forPurchase',
            'activePurchaseOrders',
            'deliveredOrders',
            'ordered',
            'forPickup',
            'forDelivery',
            'scheduledPurchases',
            'recentPurchaseRequests',
            'recentPurchaseOrders',
            'totalSpend',
            'totalOrdersCount',
            'avgOrderValue',
            'totalPipeline',
            'fulfillmentRate',
            'spendTrendMonthly',
            'spendTrendWeekly',
            'fulfillmentDistribution',
            'topSuppliers',
            'joRequestsCount',
            'restockRequestsCount',
            'activeSchedulesCount',
            'scheduledDueSoon',
            'totalEstScheduledSpend'
        );
    }
}
