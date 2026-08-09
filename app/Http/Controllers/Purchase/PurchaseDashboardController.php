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
        $activeRequestStatuses = ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery'];

        $requestBase = MaintenanceRequest::query()
            ->where(function ($q) {
                $q->whereNull('pr_no')->orWhere('pr_no', 'not like', '%-P%');
            });

        $totalRequests = (clone $requestBase)->whereIn('status', $activeRequestStatuses)->count();
        $forPurchase = (clone $requestBase)->where('status', 'For Purchase')->count();

        $activePurchaseOrders = PurchaseOrder::query()
            ->whereIn('status', ['Ordered', 'For Pick-up', 'For Delivery'])
            ->count();
        $deliveredOrders = PurchaseOrder::query()
            ->whereIn('status', ['Delivered', 'Picked Up'])
            ->count();
        $ordered = PurchaseOrder::where('status', 'Ordered')->count();
        $forPickup = PurchaseOrder::where('status', 'For Pick-up')->count();
        $forDelivery = PurchaseOrder::where('status', 'For Delivery')->count();

        $scheduledPurchases = ScheduledPurchase::query()
            ->where('status', 'Active')
            ->count();

        $recentPurchaseRequests = (clone $requestBase)
            ->whereIn('status', ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up'])
            ->latest()
            ->limit(5)
            ->get();

        $recentPurchaseOrders = PurchaseOrder::query()
            ->latest()
            ->limit(5)
            ->get();

        return view('Purchase.dashboard-purchase', compact(
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
        ));
    }
}
