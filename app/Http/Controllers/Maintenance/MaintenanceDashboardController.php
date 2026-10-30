<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Maintenance\MaintenanceReferral;
use App\Services\Maintenance\PmsStatusService;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MaintenanceDashboardController extends Controller
{
    public function __construct(
        private readonly PmsStatusService $pmsStatusService
    ) {
    }

    /**
     * Display the Maintenance Dashboard with real-time operational metrics.
     */
    public function index(Request $request)
    {
        return view('Maintenance.maintenance-dashboard', $this->buildDashboardData());
    }

    /**
     * Compute and aggregate all real-time maintenance dataset.
     */
    public function buildDashboardData(): array
    {
        // ---------------------------------------------------------------------
        // 1. JOB ORDERS (Active, Breakdown, Overdue, Recent)
        // ---------------------------------------------------------------------
        $allJobOrders = JobOrder::query()
            ->with(['maintenanceReferral.incident', 'incident'])
            ->latest('updated_at')
            ->get();

        $activeJobOrders = $allJobOrders->filter(function (JobOrder $jo) {
            return in_array($jo->status, ['On Going', 'On Hold'], true);
        });

        $totalActiveJobOrders = $activeJobOrders->count();
        $ongoingCount = $activeJobOrders->where('status', 'On Going')->count();
        $onHoldCount = $activeJobOrders->where('status', 'On Hold')->count();
        $completedCount = $allJobOrders
            ->where('status', 'Completed')
            ->where('review_status', 'Approved')
            ->count();

        // Breakdown repairs: active JOs linked to a referral or breakdown incident
        $breakdownRepairs = $activeJobOrders->filter(function (JobOrder $jo) {
            return !is_null($jo->maintenance_referral_id) || !is_null($jo->incident_id);
        });
        $breakdownRepairsCount = $breakdownRepairs->count();

        // Overdue active job orders
        $overdueJobOrders = $activeJobOrders->filter(function (JobOrder $jo) {
            return $jo->is_overdue;
        });
        $overdueJobOrdersCount = $overdueJobOrders->count();

        // Total urgent/breakdown repairs count (union without double counting)
        $urgentRepairsCount = $activeJobOrders->filter(function (JobOrder $jo) {
            return (!is_null($jo->maintenance_referral_id) || !is_null($jo->incident_id)) || $jo->is_overdue;
        })->count();

        // Recent 8 job orders for the main activity table
        $recentJobOrders = $allJobOrders->take(8);

        // ---------------------------------------------------------------------
        // 2. PMS SCHEDULES (Overdue, Due Soon, Attention List)
        // ---------------------------------------------------------------------
        $gpsByBus = $this->pmsStatusService->latestProcessedGpsByBus();

        $allPmsSchedules = PmsSchedule::query()
            ->with(['jobOrders' => function ($query) {
                $query->whereNotIn('status', ['Completed', 'Cancelled']);
            }])
            ->orderBy('bus_no')
            ->orderBy('next_pms_km')
            ->get();

        $pmsAttentionList = collect();
        $pmsOverdueCount = 0;
        $pmsDueSoonCount = 0;

        foreach ($allPmsSchedules as $schedule) {
            $normalizedBusNo = strtoupper(trim($schedule->bus_no));
            $assessment = $this->pmsStatusService->assess(
                $schedule,
                $gpsByBus->get($normalizedBusNo)
            );
            $status = $assessment['status'];

            if ($status === 'Overdue') {
                $pmsOverdueCount++;
            } elseif ($status === 'Due Soon') {
                $pmsDueSoonCount++;
            }

            if (in_array($status, ['Overdue', 'Due Soon'], true)) {
                $activeJobOrder = $schedule->jobOrders->first();

                $pmsAttentionList->push((object) [
                    'schedule' => $schedule,
                    'bus_no' => $schedule->bus_no,
                    'maintenance_type' => $schedule->maintenance_type,
                    'latest_gps_km' => (float) ($assessment['current_km'] ?? 0),
                    'next_pms_km' => $assessment['next_pms_km'],
                    'km_difference' => $assessment['remaining_km']
                        ?? $assessment['next_pms_km'],
                    'recommended_date' => $assessment['recommended_date'],
                    'status' => $status,
                    'active_job_order' => $activeJobOrder,
                    'has_active_jo' => !is_null($activeJobOrder),
                ]);
            }
        }

        // Sort attention list: Overdue first, then by closest KM
        $pmsAttentionList = $pmsAttentionList->sortBy(function ($item) {
            return ($item->status === 'Overdue' ? 0 : 1) . '_' . sprintf('%08d', max(0, (int) $item->km_difference));
        })->values()->take(6);

        $totalPmsAttention = $pmsOverdueCount + $pmsDueSoonCount;

        // ---------------------------------------------------------------------
        // 3. WORKFORCE: MECHANIC ATTENDANCE & AVAILABILITY
        // ---------------------------------------------------------------------
        $activeAssignedMechanics = $activeJobOrders
            ->whereNotNull('assigned_mechanic')
            ->filter(fn (JobOrder $jo) => trim((string)$jo->assigned_mechanic) !== '')
            ->keyBy(fn (JobOrder $jo) => Str::lower(trim($jo->assigned_mechanic)));

        $businessDate = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $todayAttendance = MechanicAttendance::query()
            ->whereDate('attendance_date', $businessDate)
            ->latest('id')
            ->get()
            ->unique('mechanic_id')
            ->keyBy(fn (MechanicAttendance $att) => Str::lower(trim($att->mechanic_name)));

        $allMechanics = Mechanic::query()
            ->where('employment_status', '!=', 'Inactive')
            ->orderBy('mechanic_name')
            ->get();

        $availableMechanicsList = collect();
        $onDutyMechanicsList = collect();
        $otherMechanicsList = collect();

        foreach ($allMechanics as $mechanic) {
            $nameKey = Str::lower(trim($mechanic->mechanic_name));
            $attendance = $todayAttendance->get($nameKey);
            $activeJob = $activeAssignedMechanics->get($nameKey);

            $status = $attendance?->status ?? 'No Attendance';
            $isPresentOrLate = in_array($status, ['Present', 'Late', 'On Duty'], true);

            if ($activeJob) {
                $onDutyMechanicsList->push((object) [
                    'mechanic' => $mechanic,
                    'name' => $mechanic->mechanic_name,
                    'mechanic_id' => $mechanic->mechanic_id,
                    'status' => 'On Duty',
                    'active_jo_no' => $activeJob->job_order_no,
                    'bus_no' => $activeJob->bus_no,
                    'shift' => $mechanic->shift ?? 'Day',
                ]);
            } elseif ($isPresentOrLate) {
                $availableMechanicsList->push((object) [
                    'mechanic' => $mechanic,
                    'name' => $mechanic->mechanic_name,
                    'mechanic_id' => $mechanic->mechanic_id,
                    'status' => $status === 'Late' ? 'Late' : 'Available',
                    'active_jo_no' => null,
                    'bus_no' => null,
                    'shift' => $mechanic->shift ?? 'Day',
                ]);
            } else {
                $otherMechanicsList->push((object) [
                    'mechanic' => $mechanic,
                    'name' => $mechanic->mechanic_name,
                    'mechanic_id' => $mechanic->mechanic_id,
                    'status' => $status,
                    'active_jo_no' => null,
                    'bus_no' => null,
                    'shift' => $mechanic->shift ?? 'Day',
                ]);
            }
        }

        $totalMechanicsCount = $allMechanics->count();
        $availableMechanicsCount = $availableMechanicsList->count();
        $onDutyMechanicsCount = $onDutyMechanicsList->count();
        $hasTodayAttendance = $todayAttendance->isNotEmpty();

        // ---------------------------------------------------------------------
        // 4. MAINTENANCE REFERRALS (Operation -> Maintenance)
        // ---------------------------------------------------------------------
        // Count all referral statuses in SQL while loading only five recent details.
        $referralCounts = MaintenanceReferral::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $referralPendingCount = (int) ($referralCounts['Pending'] ?? 0);
        $referralApprovedCount = (int) ($referralCounts['Approved'] ?? 0);
        $referralRejectedCount = (int) ($referralCounts['Rejected'] ?? 0);
        $referralJobCreatedCount = (int) ($referralCounts['Job Order Created'] ?? 0);

        $recentReferrals = MaintenanceReferral::query()
            ->with(['incident.bus', 'jobOrder'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        // ---------------------------------------------------------------------
        // 5. PARTS & PURCHASE REQUESTS (Bottleneck Tracker)
        // ---------------------------------------------------------------------
        // The dashboard only needs grouped counts, not every historical PR model.
        $purchaseRequestCounts = PurchaseRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $prPipeline = [
            'Submitted' => (int) ($purchaseRequestCounts['Submitted'] ?? 0),
            'Approved' => (int) ($purchaseRequestCounts['Approved'] ?? 0),
            'For Purchase' => (int) ($purchaseRequestCounts['For Purchase'] ?? 0),
            'Ordered' => (int) ($purchaseRequestCounts['Ordered'] ?? 0),
            'In Transit' => (int) ($purchaseRequestCounts['For Delivery'] ?? 0) + (int) ($purchaseRequestCounts['For Pick-up'] ?? 0),
            'Delivered' => (int) ($purchaseRequestCounts['Delivered'] ?? 0) + (int) ($purchaseRequestCounts['Picked Up'] ?? 0),
            'Issued' => (int) ($purchaseRequestCounts['Issued'] ?? 0),
        ];

        // Blocked repairs: active job orders waiting for parts
        $blockedJobOrders = $activeJobOrders->filter(function (JobOrder $jo) {
            return !empty($jo->part_needed)
                && in_array($jo->part_status, [
                    'Requested',
                    'Approved',
                    'Ordered',
                    'Waiting Approval',
                    'Waiting Purchase',
                    'Waiting Delivery',
                    'Ready for Issue',
                    'Delivered'
                ], true);
        })->take(5);

        // ---------------------------------------------------------------------
        // 6. FLEET READINESS
        // ---------------------------------------------------------------------
        $buses = Bus::query()->get();
        $totalBuses = $buses->count();
        $activeBusesCount = $buses->where('status', 'Active')->count();
        $inactiveBusesCount = $buses->where('status', 'Inactive')->count();
        $underMaintenanceBusesCount = $buses->where('status', 'Under Maintenance')->count();

        // Buses currently in the repair bay based on active job orders
        $busesInRepairBay = $activeJobOrders->pluck('bus_no')->filter()->unique()->count();

        $operationalRate = $totalBuses > 0
            ? round(($activeBusesCount / $totalBuses) * 100)
            : 0;

        // ---------------------------------------------------------------------
        // 7. FUEL EFFICIENCY SUMMARY
        // ---------------------------------------------------------------------
        $fuelReportsCount = FuelReport::query()->count();
        $fuelSummary = null;

        if ($fuelReportsCount > 0) {
            $totalFuelLiters = (float) FuelReport::query()->sum('fuel_liters');
            $totalFuelKm = (float) FuelReport::query()->sum('distance_km');
            $fleetAvgKmPerLiter = $totalFuelLiters > 0
                ? round($totalFuelKm / $totalFuelLiters, 2)
                : 0.0;

            $recentFuelReports = FuelReport::query()
                ->latest('report_date')
                ->limit(3)
                ->get();

            $fuelSummary = (object) [
                'total_reports' => $fuelReportsCount,
                'total_liters' => $totalFuelLiters,
                'total_km' => $totalFuelKm,
                'avg_km_per_liter' => $fleetAvgKmPerLiter,
                'recent_reports' => $recentFuelReports,
                'has_data' => true,
            ];
        }

        return [
            // Top KPIs
            'totalActiveJobOrders' => $totalActiveJobOrders,
            'ongoingCount' => $ongoingCount,
            'onHoldCount' => $onHoldCount,
            'completedCount' => $completedCount,
            'breakdownRepairsCount' => $breakdownRepairsCount,
            'overdueJobOrdersCount' => $overdueJobOrdersCount,
            'urgentRepairsCount' => $urgentRepairsCount,
            'totalPmsAttention' => $totalPmsAttention,
            'pmsOverdueCount' => $pmsOverdueCount,
            'pmsDueSoonCount' => $pmsDueSoonCount,
            'availableMechanicsCount' => $availableMechanicsCount,
            'onDutyMechanicsCount' => $onDutyMechanicsCount,
            'totalMechanicsCount' => $totalMechanicsCount,
            'hasTodayAttendance' => $hasTodayAttendance,
            'referralPendingCount' => $referralPendingCount,

            // Active & Recent Job Orders
            'recentJobOrders' => $recentJobOrders,

            // PMS Attention Hub
            'pmsAttentionList' => $pmsAttentionList,

            // Mechanic Roster
            'availableMechanicsList' => $availableMechanicsList,
            'onDutyMechanicsList' => $onDutyMechanicsList,
            'otherMechanicsList' => $otherMechanicsList,

            // Referrals
            'referralApprovedCount' => $referralApprovedCount,
            'referralRejectedCount' => $referralRejectedCount,
            'referralJobCreatedCount' => $referralJobCreatedCount,
            'recentReferrals' => $recentReferrals,

            // Parts & PR Bottleneck
            'prPipeline' => $prPipeline,
            'blockedJobOrders' => $blockedJobOrders,

            // Fleet Readiness
            'totalBuses' => $totalBuses,
            'activeBusesCount' => $activeBusesCount,
            'inactiveBusesCount' => $inactiveBusesCount,
            'underMaintenanceBusesCount' => $underMaintenanceBusesCount,
            'busesInRepairBay' => $busesInRepairBay,
            'operationalRate' => $operationalRate,

            // Fuel
            'fuelSummary' => $fuelSummary,
        ];
    }
}
