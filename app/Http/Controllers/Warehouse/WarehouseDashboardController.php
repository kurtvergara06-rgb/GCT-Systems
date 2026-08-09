<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;

class WarehouseDashboardController extends Controller
{
    public function index()
    {
        $inventoryItems = InventoryItem::query()->get();

        $totalInventory = $inventoryItems->count();
        $availableStock = $inventoryItems->filter(fn ($item) => $item->stock_status === 'In Stock')->count();
        $lowStockItems = $inventoryItems->filter(fn ($item) => $item->stock_status === 'Low Stock')->count();
        $outOfStock = $inventoryItems->filter(fn ($item) => $item->stock_status === 'Critical')->count();

        $pendingPartRequests = PurchaseRequest::query()
            ->where('status', 'Approved')
            ->where(function ($q) {
                $q->whereNull('job_order_no')->orWhere('job_order_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('pr_no')->orWhere('pr_no', 'not like', '%-P%');
            })
            ->count();

        $incomingDeliveries = PurchaseOrder::query()
            ->whereIn('status', ['For Delivery', 'For Pick-up'])
            ->whereNull('inventory_posted_at')
            ->count();

        $issuedToday = PurchaseRequest::query()
            ->where('status', 'Issued')
            ->whereDate('updated_at', today())
            ->count();

        $recentInventoryItems = InventoryItem::query()->latest('updated_at')->limit(5)->get();
        $recentPartRequests = PurchaseRequest::query()
            ->where(function ($q) {
                $q->whereNull('job_order_no')->orWhere('job_order_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('pr_no')->orWhere('pr_no', 'not like', '%-P%');
            })
            ->latest()
            ->limit(5)
            ->get();

        return view('Warehouse.dashboard-warehouse', compact(
            'totalInventory',
            'lowStockItems',
            'pendingPartRequests',
            'incomingDeliveries',
            'availableStock',
            'outOfStock',
            'issuedToday',
            'recentInventoryItems',
            'recentPartRequests'
        ));
    }
}
