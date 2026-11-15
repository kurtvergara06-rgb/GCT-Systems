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
            'recentPurchaseOrders'
        );
    }
}
