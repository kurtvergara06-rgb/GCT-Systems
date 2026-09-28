<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;

class WarehouseDashboardController extends Controller
{
    public function index()
    {
        return view('Warehouse.dashboard-warehouse', $this->data());
    }

    public function data(): array
    {
        $inventoryItems = InventoryItem::query()->get();

        $totalInventory = $inventoryItems->count();
        $availableStock = $inventoryItems->filter(fn ($item) => $item->stock_status === 'In Stock')->count();
        $lowStockItems = $inventoryItems->filter(fn ($item) => $item->stock_status === 'Low Stock')->count();
        $outOfStock = $inventoryItems->filter(fn ($item) => $item->stock_status === 'Critical')->count();

        // Items requiring urgent replenishment attention
        $criticalStockItems = $inventoryItems
            ->filter(fn ($item) => in_array($item->stock_status, ['Critical', 'Low Stock']))
            ->sortBy(fn ($item) => (int) ($item->on_hand ?? $item->quantity_available ?? 0))
            ->take(5)
            ->values();

        // Base query for Maintenance Part Requests (excluding restock & purchase-side copies)
        $maintenanceRequestBase = PurchaseRequest::query()
            ->where(function ($q) {
                $q->whereNull('remarks')
                    ->orWhere('remarks', 'not like', 'Missing parts from %');
            })
            ->where(function ($q) {
                $q->whereNull('job_order_no')
                    ->orWhere('job_order_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('bus_no')
                    ->orWhere('bus_no', '!=', 'RESTOCK');
            })
            ->where(function ($q) {
                $q->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            });

        $pendingPartRequests = (clone $maintenanceRequestBase)
            ->where('status', 'Approved')
            ->count();

        // Active part requests waiting for warehouse issuance or processing
        $activePartRequests = (clone $maintenanceRequestBase)
            ->whereIn('status', ['Approved', 'For Purchase', 'Ordered', 'For Pick-up', 'For Delivery'])
            ->latest()
            ->limit(5)
            ->get();

        // Expected incoming deliveries from Purchasing
        $incomingDeliveriesQuery = PurchaseOrder::query()
            ->whereIn('status', ['For Delivery', 'For Pick-up'])
            ->whereNull('inventory_posted_at');

        $incomingDeliveries = (clone $incomingDeliveriesQuery)->count();

        $expectedDeliveries = (clone $incomingDeliveriesQuery)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $issuedToday = PurchaseRequest::query()
            ->where('status', 'Issued')
            ->whereDate('updated_at', today())
            ->count();

        $recentInventoryItems = InventoryItem::query()
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->each(function (InventoryItem $item) {
                $item->setAttribute('quantity', $item->on_hand ?? $item->quantity_available ?? 0);
            });

        $recentPartRequests = (clone $maintenanceRequestBase)
            ->latest()
            ->limit(5)
            ->get();

        // Recent stock movements (audit trail)
        $recentStockMovements = StockMovement::query()
            ->latest()
            ->limit(5)
            ->get();

        return compact(
            'totalInventory',
            'lowStockItems',
            'pendingPartRequests',
            'incomingDeliveries',
            'availableStock',
            'outOfStock',
            'issuedToday',
            'recentInventoryItems',
            'recentPartRequests',
            'activePartRequests',
            'expectedDeliveries',
            'criticalStockItems',
            'recentStockMovements'
        );
    }
}
