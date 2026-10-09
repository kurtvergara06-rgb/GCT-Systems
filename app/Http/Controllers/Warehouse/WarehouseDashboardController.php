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
        $forReorder = $inventoryItems->filter(function ($item) {
            $stock = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
            $reorderLevel = (int) ($item->reorder_level ?? 0);

            return $reorderLevel > 0 && $stock <= $reorderLevel;
        })->count();

        $inventoryCategoryOptions = $inventoryItems
            ->pluck('category')
            ->filter(fn ($category) => filled($category))
            ->unique()
            ->sort()
            ->values();

        $buildInventoryStatus = static function ($items): array {
            $total = $items->count();
            $available = $items->filter(fn ($item) => $item->stock_status === 'In Stock')->count();
            $low = $items->filter(fn ($item) => $item->stock_status === 'Low Stock')->count();
            $out = $items->filter(fn ($item) => $item->stock_status === 'Critical')->count();
            $reorder = $items->filter(function ($item) {
                $stock = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
                $reorderLevel = (int) ($item->reorder_level ?? 0);

                return $reorderLevel > 0 && $stock <= $reorderLevel;
            })->count();

            return compact('total', 'available', 'low', 'out', 'reorder');
        };

        $inventoryStatusByCategory = [
            'All Categories' => $buildInventoryStatus($inventoryItems),
        ];

        foreach ($inventoryCategoryOptions as $category) {
            $inventoryStatusByCategory[$category] = $buildInventoryStatus(
                $inventoryItems->where('category', $category)
            );
        }

        $inventoryAddedThisMonth = $inventoryItems
            ->filter(fn ($item) => $item->created_at?->gte(now()->startOfMonth()))
            ->count();

        $criticalStockItems = $inventoryItems
            ->filter(fn ($item) => in_array($item->stock_status, ['Critical', 'Low Stock'], true))
            ->sortBy(function ($item) {
                $stock = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
                $reorder = max(1, (int) ($item->reorder_level ?? 0));

                return $stock / $reorder;
            })
            ->take(5)
            ->values();

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

        $activeStatuses = ['Approved', 'For Purchase', 'Ordered', 'For Pick-up', 'For Delivery'];

        $activePartRequestCount = (clone $maintenanceRequestBase)
            ->whereIn('status', $activeStatuses)
            ->count();

        $activePartRequests = (clone $maintenanceRequestBase)
            ->whereIn('status', $activeStatuses)
            ->orderByRaw("CASE status
                WHEN 'Approved' THEN 0
                WHEN 'For Purchase' THEN 1
                WHEN 'Ordered' THEN 2
                WHEN 'For Pick-up' THEN 3
                WHEN 'For Delivery' THEN 4
                ELSE 5
            END")
            ->oldest()
            ->limit(5)
            ->get();

        $incomingDeliveriesQuery = PurchaseOrder::query()
            ->whereIn('status', ['For Delivery', 'For Pick-up'])
            ->whereNull('inventory_posted_at');

        $incomingDeliveries = (clone $incomingDeliveriesQuery)->count();
        $incomingToday = (clone $incomingDeliveriesQuery)
            ->whereDate('updated_at', today())
            ->count();

        $expectedDeliveries = (clone $incomingDeliveriesQuery)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $issuedToday = PurchaseRequest::query()
            ->where('status', 'Issued')
            ->whereDate('issued_at', today())
            ->count();

        $recentStockMovements = StockMovement::query()
            ->with('creator')
            ->latest()
            ->limit(5)
            ->get();

        $monthStart = now()->startOfMonth();
        $trendEnd = now()->endOfDay();

        $monthMovements = StockMovement::query()
            ->whereBetween('created_at', [$monthStart, $trendEnd])
            ->oldest()
            ->get();

        $hasCurrentMonthMovements = $monthMovements->isNotEmpty();
        $trendStart = $hasCurrentMonthMovements
            ? $monthStart
            : now()->subDays(13)->startOfDay();

        $activeMovements = $hasCurrentMonthMovements
            ? $monthMovements
            : StockMovement::query()->whereBetween('created_at', [$trendStart, $trendEnd])->oldest()->get();

        $trendPeriodLabel = $hasCurrentMonthMovements
            ? 'This Month'
            : 'Past 14 Days';

        $topIssuedItems = $activeMovements
            ->filter(fn ($movement) => strtolower((string) $movement->movement_type) === 'stock out')
            ->groupBy(fn ($movement) => $movement->item_code ?: $movement->item_name)
            ->map(function ($movements) {
                $first = $movements->first();

                return [
                    'item_code' => $first->item_code ?: '—',
                    'item_name' => $first->item_name ?: 'Inventory Item',
                    'total_issued' => (int) abs($movements->sum('quantity_change')),
                ];
            })
            ->sortByDesc('total_issued')
            ->take(5)
            ->values();

        $movementsByDay = $activeMovements->groupBy(fn ($movement) => $movement->created_at->format('Y-m-d'));
        $movementTrend = [
            'labels' => [],
            'received' => [],
            'issued' => [],
            'adjusted' => [],
        ];

        for ($day = $trendStart->copy(); $day->lte($trendEnd); $day->addDay()) {
            $dateKey = $day->format('Y-m-d');
            $dayMovements = $movementsByDay->get($dateKey, collect());

            $movementTrend['labels'][] = $day->format('M j');
            $movementTrend['received'][] = (int) $dayMovements
                ->filter(fn ($movement) => strtolower((string) $movement->movement_type) === 'stock in')
                ->sum(fn ($movement) => max(0, (int) $movement->quantity_change));
            $movementTrend['issued'][] = (int) abs($dayMovements
                ->filter(fn ($movement) => strtolower((string) $movement->movement_type) === 'stock out')
                ->sum('quantity_change'));
            $movementTrend['adjusted'][] = (int) abs($dayMovements
                ->filter(fn ($movement) => strtolower((string) $movement->movement_type) === 'adjustment')
                ->sum('quantity_change'));
        }

        $warehouseChartData = [
            'inventory' => [
                'labels' => ['Sufficient', 'Low Stock', 'Out of Stock', 'For Reorder'],
                'values' => [$availableStock, $lowStockItems, $outOfStock, $forReorder],
            ],
            'statusDistribution' => [
                'labels' => ['Sufficient Stock', 'Low Stock', 'Out of Stock'],
                'values' => [$availableStock, $lowStockItems, $outOfStock],
            ],
            'statusByCategory' => $inventoryStatusByCategory,
            'movementTrend' => $movementTrend,
        ];

        return compact(
            'totalInventory',
            'lowStockItems',
            'activePartRequestCount',
            'incomingDeliveries',
            'availableStock',
            'outOfStock',
            'forReorder',
            'inventoryAddedThisMonth',
            'incomingToday',
            'inventoryCategoryOptions',
            'inventoryStatusByCategory',
            'issuedToday',
            'activePartRequests',
            'expectedDeliveries',
            'criticalStockItems',
            'recentStockMovements',
            'topIssuedItems',
            'trendPeriodLabel',
            'warehouseChartData'
        );
    }
}
