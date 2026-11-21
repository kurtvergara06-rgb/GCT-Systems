<?php

namespace App\Services;

use App\Models\Admin\ActivityLog;
use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\MechanicAttendance;
use App\Models\Operation\TripSchedule;
use App\Models\Purchase\PurchaseOrder;
use App\Models\TopbarNotification;
use App\Models\Warehouse\InventoryItem;
use App\Services\Warehouse\InventoryThresholdCalculator;
use Illuminate\Support\Facades\DB;
use App\Models\Warehouse\StockMovement;
use Carbon\Carbon;
use Throwable;

class AdminDashboardService
{
    public function data(): array
    {
        // -------------------------------------------------------------
        // 1. Cross-Department Record Totals & Distribution
        // -------------------------------------------------------------
        $maintenanceTotal = JobOrder::count() + MechanicAttendance::count();
        $warehouseTotal = InventoryItem::count();
        $purchaseTotal = PurchaseOrder::count();
        $operationsTotal = TripSchedule::count() + DriverAttendance::count();

        $departmentDistribution = [
            'Maintenance' => $maintenanceTotal,
            'Warehouse' => $warehouseTotal,
            'Purchase' => $purchaseTotal,
            'Operations' => $operationsTotal,
        ];

        $totalRecords = array_sum($departmentDistribution);

        // -------------------------------------------------------------
        // 2. Activity Trends (6-Month Volume & 7-Day Velocity)
        // -------------------------------------------------------------
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

        // -------------------------------------------------------------
        // 3. Operational Department Metrics & Supervision
        // -------------------------------------------------------------
        $activeJobOrders = JobOrder::whereNotIn('status', ['Completed', 'Cancelled', 'Canceled'])->count();
        $completedJobOrders = JobOrder::where('status', 'Completed')->count();
        $onHoldJobOrders = JobOrder::where('status', 'On Hold')->count();

        // Apply Warehouse's historical issuance/manual thresholds and JO
        // reservations with two grouped queries, not per-item queries.
        $inventory = InventoryItem::query()->get();
        $calculator = app(InventoryThresholdCalculator::class);
        $lowStockItems = 0;
        if ($inventory->isNotEmpty()) {
            [$start, $endExclusive] = $calculator->completeMonthWindow();
            $issuedByItem = DB::table('inventory_issuance_items as lines')
                ->join('inventory_issuances as issues', 'issues.id', '=', 'lines.inventory_issuance_id')
                ->whereNotNull('issues.handover_confirmed_at')
                ->whereNotNull('issues.signed_slip_path')
                ->where('issues.signed_slip_path', '!=', '')
                ->whereNotNull('issues.received_by')
                ->where('issues.received_by', '!=', '')
                ->where('issues.issued_at', '>=', $start)
                ->where('issues.issued_at', '<', $endExclusive)
                ->selectRaw('lines.inventory_item_id, SUM(lines.quantity) AS issued_units')
                ->groupBy('lines.inventory_item_id')
                ->pluck('issued_units', 'lines.inventory_item_id');
            $reservedByItem = DB::table('job_order_part_reservations')
                ->selectRaw('inventory_item_id, SUM(quantity) AS reserved')
                ->groupBy('inventory_item_id')
                ->pluck('reserved', 'inventory_item_id');

            foreach ($inventory as $item) {
                $issued = (int) $issuedByItem->get($item->getKey(), 0);
                $threshold = $calculator->hasEnoughHistory($item->created_at, $start, $issued)
                    ? $calculator->roundedMonthlyUsage($issued)
                    : ((int) $item->reorder_level > 0 ? (int) $item->reorder_level : null);
                $state = $calculator->stockStatus(
                    (int) ($item->quantity_available ?? $item->on_hand ?? 0),
                    (int) $reservedByItem->get($item->getKey(), 0),
                    $threshold
                );
                if (in_array($state, ['Critical', 'Low Stock'], true)) {
                    $lowStockItems++;
                }
            }
        }
        $healthyStockItems = max(0, $warehouseTotal - $lowStockItems);
        $totalStockMovements = StockMovement::count();

        $activePurchaseOrders = PurchaseOrder::whereIn('status', ['Ordered', 'For Pick-up', 'For Delivery', 'For Review'])->count();
        $completedPurchaseOrders = PurchaseOrder::whereIn('status', ['Completed', 'Delivered', 'Received'])->count();

        $driversToday = DriverAttendance::whereDate('created_at', today())->count();
        $mechanicsToday = MechanicAttendance::whereDate('created_at', today())->count();
        $attendanceToday = $driversToday + $mechanicsToday;
        $tripsToday = TripSchedule::whereDate('trip_date', today())->count();
        $totalTrips = TripSchedule::count();

        // -------------------------------------------------------------
        // 4. Admin Governance, Identity & Access Metrics
        // -------------------------------------------------------------
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'Active')->count();
        $pendingApplications = User::where('status', 'Pending')->count();
        $rejectedApplications = User::where('status', 'Rejected')->count();
        $inactiveUsers = User::where('status', 'Inactive')->count();

        $usersByDepartment = [
            'Admin' => User::where('department', 'Admin')->count(),
            'Maintenance' => User::where('department', 'Maintenance')->count(),
            'Warehouse' => User::where('department', 'Warehouse')->count(),
            'Purchase' => User::where('department', 'Purchase')->count(),
            'Operation' => User::where('department', 'Operation')->count(),
        ];

        // -------------------------------------------------------------
        // 5. Data Pipelines & Batch Ingestion Telemetry
        // -------------------------------------------------------------
        try {
            $totalBatchUploads = BatchUpload::count();
            $inReviewBatches = BatchUpload::whereIn('status', ['In Review', 'Processing', 'Pending'])->count();
            $processedBatches = BatchUpload::whereIn('status', ['Processed', 'Completed'])->count();
            $totalGpsRecords = GpsTripRecord::count();
            $recentBatches = BatchUpload::query()->latest()->limit(4)->get();
        } catch (Throwable) {
            $totalBatchUploads = 0;
            $inReviewBatches = 0;
            $processedBatches = 0;
            $totalGpsRecords = 0;
            $recentBatches = collect();
        }

        // -------------------------------------------------------------
        // 6. System Audit & Activity Logs Telemetry
        // -------------------------------------------------------------
        try {
            $todayLogs = ActivityLog::whereDate('created_at', today());
            $activitiesToday = (clone $todayLogs)->count();
            $securityEventsToday = (clone $todayLogs)
                ->whereIn('event_type', ['Login', 'Logout', 'Security', 'Deleted'])
                ->count();
            $userActionsToday = (clone $todayLogs)
                ->whereNotIn('event_type', ['Login', 'Logout'])
                ->count();
            $recentActivityLogs = ActivityLog::query()
                ->with('user:id,name,avatar_path')
                ->latest()
                ->limit(6)
                ->get();
        } catch (Throwable) {
            $activitiesToday = 0;
            $securityEventsToday = 0;
            $userActionsToday = 0;
            $recentActivityLogs = collect();
        }

        // -------------------------------------------------------------
        // 7. Fleet Health & Predictive Readiness Telemetry
        // -------------------------------------------------------------
        try {
            $totalBuses = Bus::count();
            $operationalBuses = Bus::where('status', 'Active')->count();
            $maintenanceBuses = Bus::where('status', 'Under Maintenance')->count();
            $fleetReadinessPct = $totalBuses > 0 ? round(($operationalBuses / $totalBuses) * 100, 1) : 0.0;
        } catch (Throwable) {
            $totalBuses = 0;
            $operationalBuses = 0;
            $maintenanceBuses = 0;
            $fleetReadinessPct = 0.0;
        }

        return [
            // Standard backward-compatible metrics
            'departmentMetrics' => [
                'maintenance' => $activeJobOrders,
                'warehouse' => $lowStockItems,
                'purchase' => $activePurchaseOrders,
                'operations' => $attendanceToday,
            ],
            'departmentDistribution' => $departmentDistribution,
            'totalRecords' => $totalRecords,
            'monthLabels' => $monthLabels,
            'monthlyActivity' => $monthlyActivity,
            'dayLabels' => $dayLabels,
            'dailyActivity' => $dailyActivity,
            'recentUsers' => User::query()->latest('updated_at')->limit(8)->get(),
            'recentActivity' => TopbarNotification::query()->latest()->limit(7)->get(),
            'totalUsers' => $totalUsers,

            // Comprehensive Admin Governance & Audit Telemetry
            'activeUsers' => $activeUsers,
            'pendingApplications' => $pendingApplications,
            'rejectedApplications' => $rejectedApplications,
            'inactiveUsers' => $inactiveUsers,
            'usersByDepartment' => $usersByDepartment,

            'activitiesToday' => $activitiesToday,
            'securityEventsToday' => $securityEventsToday,
            'userActionsToday' => $userActionsToday,
            'recentActivityLogs' => $recentActivityLogs,

            // Data Management & Batch Processing Pipelines
            'batchMetrics' => [
                'total' => $totalBatchUploads,
                'in_review' => $inReviewBatches,
                'processed' => $processedBatches,
                'total_gps_records' => $totalGpsRecords,
            ],
            'recentBatches' => $recentBatches,

            // Detailed Department Sub-Metrics
            'metricsDetail' => [
                'maintenance' => [
                    'active' => $activeJobOrders,
                    'completed' => $completedJobOrders,
                    'on_hold' => $onHoldJobOrders,
                    'total' => $maintenanceTotal,
                ],
                'warehouse' => [
                    'low_stock' => $lowStockItems,
                    'healthy' => $healthyStockItems,
                    'movements' => $totalStockMovements,
                    'total' => $warehouseTotal,
                ],
                'purchase' => [
                    'active' => $activePurchaseOrders,
                    'completed' => $completedPurchaseOrders,
                    'total' => $purchaseTotal,
                ],
                'operations' => [
                    'today' => $attendanceToday,
                    'drivers' => $driversToday,
                    'mechanics' => $mechanicsToday,
                    'trips_today' => $tripsToday,
                    'total_trips' => $totalTrips,
                    'total' => $operationsTotal,
                ],
            ],

            // Fleet Telemetry & Predictive Readiness
            'fleetMetrics' => [
                'total_buses' => $totalBuses,
                'operational' => $operationalBuses,
                'maintenance' => $maintenanceBuses,
                'readiness_pct' => $fleetReadinessPct,
            ],
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
