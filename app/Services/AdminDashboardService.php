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

        $now = now();
        $activityCounts = $this->activityCountsByDate(
            $now->copy()->subMonths(5)->startOfMonth(),
            $now->copy()->endOfDay()
        );

        $monthLabels = [];
        $monthlyActivity = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->copy()->subMonths($offset);
            $monthKey = $month->format('Y-m');
            $monthLabels[] = $month->format('M');
            $monthlyActivity[] = (int) ($activityCounts['monthly'][$monthKey] ?? 0);
        }

        $dayLabels = [];
        $dailyActivity = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $now->copy()->subDays($offset);
            $dayLabels[] = $day->format('M j');
            $dailyActivity[] = (int) ($activityCounts['daily'][$day->toDateString()] ?? 0);
        }

        $activeJobOrders = JobOrder::whereNotIn('status', ['Completed', 'Cancelled', 'Canceled'])->count();
        $lowStockItems = InventoryItem::query()
            ->where(function ($query) {
                $stock = 'COALESCE(quantity_available, on_hand, 0)';

                $query->whereRaw($stock.' <= 0')
                    ->orWhere(function ($lowStock) use ($stock) {
                        $lowStock->whereRaw($stock.' > 0')
                            ->where('reorder_level', '>', 0)
                            ->whereRaw($stock.' <= reorder_level');
                    });
            })
            ->count();
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

    /**
     * Read recent dashboard activity with one grouped query per source table,
     * instead of running one count query for every displayed day and month.
     *
     * @return array{daily: array<string, int>, monthly: array<string, int>}
     */
    private function activityCountsByDate(Carbon $start, Carbon $end): array
    {
        $daily = [];
        $monthly = [];

        foreach ([
            JobOrder::class,
            StockMovement::class,
            PurchaseOrder::class,
            DriverAttendance::class,
            MechanicAttendance::class,
        ] as $model) {
            $counts = $model::query()
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) AS activity_date, COUNT(*) AS activity_count')
                ->groupBy('activity_date')
                ->pluck('activity_count', 'activity_date');

            foreach ($counts as $date => $count) {
                $count = (int) $count;
                $daily[$date] = ($daily[$date] ?? 0) + $count;
                $month = substr((string) $date, 0, 7);
                $monthly[$month] = ($monthly[$month] ?? 0) + $count;
            }
        }

        return ['daily' => $daily, 'monthly' => $monthly];
    }
}
