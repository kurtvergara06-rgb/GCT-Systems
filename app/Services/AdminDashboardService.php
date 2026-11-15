<?php

namespace App\Services;

use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\MechanicAttendance;
use App\Models\Purchase\PurchaseOrder;
use App\Models\TopbarNotification;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use Carbon\Carbon;

class AdminDashboardService
{
    public function data(): array
    {
        $maintenanceTotal = JobOrder::count();
        $warehouseTotal = InventoryItem::count();
        $purchaseTotal = PurchaseOrder::count();
        $operationsTotal = DriverAttendance::count() + MechanicAttendance::count();

        $departmentDistribution = [
            'Maintenance' => $maintenanceTotal,
            'Warehouse' => $warehouseTotal,
            'Purchase' => $purchaseTotal,
            'Operations' => $operationsTotal,
        ];

        $monthLabels = [];
        $monthlyActivity = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->copy()->subMonths($offset);
            $monthLabels[] = $month->format('M');
            $monthlyActivity[] = $this->countCreatedDuringMonth($month);
        }

        $dayLabels = [];
        $dailyActivity = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $day = now()->copy()->subDays($offset);
            $dayLabels[] = $day->format('M j');
            $dailyActivity[] = $this->countCreatedOnDate($day);
        }

        $activeJobOrders = JobOrder::whereNotIn('status', ['Completed', 'Cancelled', 'Canceled'])->count();
        $lowStockItems = InventoryItem::query()->get()->filter(
            fn ($item) => in_array($item->stock_status, ['Low Stock', 'Critical'], true)
        )->count();
        $activePurchaseOrders = PurchaseOrder::whereIn('status', ['Ordered', 'For Pick-up', 'For Delivery'])->count();
        $attendanceToday = DriverAttendance::whereDate('created_at', today())->count()
            + MechanicAttendance::whereDate('created_at', today())->count();

        return [
            'departmentMetrics' => [
                'maintenance' => $activeJobOrders,
                'warehouse' => $lowStockItems,
                'purchase' => $activePurchaseOrders,
                'operations' => $attendanceToday,
            ],
            'departmentDistribution' => $departmentDistribution,
            'monthLabels' => $monthLabels,
            'monthlyActivity' => $monthlyActivity,
            'dayLabels' => $dayLabels,
            'dailyActivity' => $dailyActivity,
            'recentUsers' => User::query()->latest('updated_at')->limit(5)->get(),
            'recentActivity' => TopbarNotification::query()->latest()->limit(5)->get(),
            'totalUsers' => User::count(),
        ];
    }

    private function countCreatedDuringMonth(Carbon $month): int
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return JobOrder::whereBetween('created_at', [$start, $end])->count()
            + StockMovement::whereBetween('created_at', [$start, $end])->count()
            + PurchaseOrder::whereBetween('created_at', [$start, $end])->count()
            + DriverAttendance::whereBetween('created_at', [$start, $end])->count()
            + MechanicAttendance::whereBetween('created_at', [$start, $end])->count();
    }

    private function countCreatedOnDate(Carbon $day): int
    {
        return JobOrder::whereDate('created_at', $day)->count()
            + StockMovement::whereDate('created_at', $day)->count()
            + PurchaseOrder::whereDate('created_at', $day)->count()
            + DriverAttendance::whereDate('created_at', $day)->count()
            + MechanicAttendance::whereDate('created_at', $day)->count();
    }
}
