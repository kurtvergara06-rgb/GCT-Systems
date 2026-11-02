<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ActivityLog;
use App\Models\Admin\GpsTripRecord;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Incident;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Models\TopbarNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DescriptiveAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $activeSummary = $request->input('view') === 'administrative' ? 'administrative' : 'operational';

        $allowedPeriods = ['this-month', 'last-30-days', 'last-3-months', 'this-year'];
        $period = in_array((string) $request->input('period'), $allowedPeriods, true)
            ? (string) $request->input('period')
            : 'this-month';

        $selectedBus = strtoupper(trim((string) $request->input('bus', 'all')));
        if ($selectedBus === '') {
            $selectedBus = 'ALL';
        }

        [$start, $end, $periodLabel] = $this->periodBounds($period);
        $dateRangeDisplay = $start->format('M j, Y') . ' — ' . $end->format('M j, Y');

        $buses = Bus::query()->orderBy('bus_no')->get();
        $totalBuses = $buses->count();
        $activeBuses = $buses->where('status', 'Active')->count();
        $underMaintenance = $buses->where('status', 'Under Maintenance')->count();
        $inactiveBuses = $buses->where('status', 'Inactive')->count();
        $fleetAvailability = $totalBuses > 0 ? round(($activeBuses / $totalBuses) * 100, 1) : 0.0;

        $dateBuckets = $this->createDateBuckets($start, $end, $period);

        if ($activeSummary === 'operational') {
            $data = $this->getOperationalData($selectedBus, $start, $end, $dateBuckets);
        } else {
            $data = $this->getAdministrativeData($start, $end, $dateBuckets);
        }

        return view('Admin.Analytics.descriptive.layout', array_merge([
            'activeSummary' => $activeSummary,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'dateRangeDisplay' => $dateRangeDisplay,
            'selectedBus' => $selectedBus,
            'buses' => $buses,
            'totalBuses' => $totalBuses,
            'activeBuses' => $activeBuses,
            'underMaintenance' => $underMaintenance,
            'inactiveBuses' => $inactiveBuses,
            'fleetAvailability' => $fleetAvailability,
        ], $data));
    }

    /**
     * Compute Thesis Objective 6.1.1 Operational Summary Data.
     */
    private function getOperationalData(string $selectedBus, Carbon $start, Carbon $end, array $dateBuckets): array
    {
        // 1. Scheduled Trips
        $tripsQuery = TripSchedule::query()
            ->whereBetween('trip_date', [$start->toDateString(), $end->toDateString()]);

        if ($selectedBus !== 'ALL') {
            $tripsQuery->whereHas('assignments.bus', function ($q) use ($selectedBus): void {
                $q->whereRaw('UPPER(TRIM(bus_no)) = ?', [$selectedBus]);
            });
        }

        $allTrips = $tripsQuery->with(['assignments.bus', 'shuttleRoute'])->get();
        $totalScheduledTrips = $allTrips->count();
        $completedTrips = $allTrips->where('status', 'Completed')->count();
        $scheduledOnlyTrips = $allTrips->where('status', 'Scheduled')->count();
        $pendingDdrTrips = $allTrips->where('status', 'Pending DDR')->count();
        $missedTrips = $allTrips->where('status', 'Missed')->count();
        $cancelledTrips = $allTrips->where('status', 'Cancelled')->count();
        $tripCompletionRate = $totalScheduledTrips > 0 ? round(($completedTrips / $totalScheduledTrips) * 100, 1) : 0.0;

        // 2. Assignments
        $totalRequiredBuses = (int) $allTrips->sum('required_buses');
        $totalAssignedBuses = (int) $allTrips->sum(fn ($t) => $t->assignments->count());
        $assignmentFulfillmentRate = $totalRequiredBuses > 0 ? round(($totalAssignedBuses / $totalRequiredBuses) * 100, 1) : 0.0;

        // 3. GPS Telemetry Trips
        $gpsRecords = $this->tripQuery($selectedBus, $start, $end)->orderBy('beginning_at')->get();
        $gpsMetrics = $this->tripMetrics($gpsRecords);

        // 4. Incidents
        $incidentsQuery = Incident::query()
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('incident_reported_at', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end): void {
                      $q2->whereNull('incident_reported_at')->whereBetween('created_at', [$start, $end]);
                  });
            });

        if ($selectedBus !== 'ALL') {
            $incidentsQuery->whereHas('bus', function ($q) use ($selectedBus): void {
                $q->whereRaw('UPPER(TRIM(bus_no)) = ?', [$selectedBus]);
            });
        }

        $allIncidents = $incidentsQuery->with(['bus', 'replacement.replacementBus'])->get();
        $totalIncidents = $allIncidents->count();
        $breakdownsCount = $allIncidents->where('incident_type', 'Bus Breakdown')->count();
        $accidentsCount = $allIncidents->filter(fn ($i) => str_contains((string) $i->incident_type, 'Accident'))->count();
        $otherIncidentsCount = max(0, $totalIncidents - $breakdownsCount - $accidentsCount);
        $resolvedIncidents = $allIncidents->where('status', 'Resolved')->count();
        $replacementsDispatched = $allIncidents->filter(fn ($i) => $i->replacement !== null || $i->status === 'Replacement Bus Dispatched')->count();

        // 5. Trend Over Time (Scheduled vs GPS vs Incidents)
        $operationalTrend = collect($dateBuckets)->map(function (array $bucket) use ($allTrips, $gpsRecords, $allIncidents): array {
            $bStart = $bucket['start'];
            $bEnd = $bucket['end'];

            $scheduledInBucket = $allTrips->filter(function (TripSchedule $t) use ($bStart, $bEnd): bool {
                $tDate = Carbon::parse($t->trip_date)->startOfDay();
                return $tDate->betweenIncluded($bStart->copy()->startOfDay(), $bEnd->copy()->endOfDay());
            })->count();

            $completedInBucket = $allTrips->filter(function (TripSchedule $t) use ($bStart, $bEnd): bool {
                if ($t->status !== 'Completed') {
                    return false;
                }
                $tDate = Carbon::parse($t->trip_date)->startOfDay();
                return $tDate->betweenIncluded($bStart->copy()->startOfDay(), $bEnd->copy()->endOfDay());
            })->count();

            $gpsInBucket = $gpsRecords->filter(function (GpsTripRecord $g) use ($bStart, $bEnd): bool {
                return $g->beginning_at && $g->beginning_at->betweenIncluded($bStart, $bEnd);
            })->count();

            $incidentsInBucket = $allIncidents->filter(function (Incident $i) use ($bStart, $bEnd): bool {
                $ts = $i->incident_reported_at ?: $i->created_at;
                return $ts && $ts->betweenIncluded($bStart, $bEnd);
            })->count();

            return [
                'label' => $bucket['label'],
                'scheduled' => $scheduledInBucket,
                'completed' => $completedInBucket,
                'gps' => $gpsInBucket,
                'incidents' => $incidentsInBucket,
            ];
        });

        // 6. Distributions for Charts
        $tripStatusDistribution = [
            'Completed' => $completedTrips,
            'Scheduled' => $scheduledOnlyTrips,
            'Pending DDR' => $pendingDdrTrips,
            'Missed' => $missedTrips,
        ];
        if ($cancelledTrips > 0) {
            $tripStatusDistribution['Cancelled'] = $cancelledTrips;
        }

        $incidentTypeDistribution = $allIncidents->groupBy(fn ($i) => $i->incident_type ?: 'Unspecified')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // 7. Recent Records
        $recentTrips = $allTrips->sortByDesc(fn ($t) => Carbon::parse($t->trip_date)->timestamp)->take(6)->values();
        $recentIncidents = $allIncidents->sortByDesc(fn ($i) => ($i->incident_reported_at ?: $i->created_at)->timestamp)->take(6)->values();

        return [
            'totalScheduledTrips' => $totalScheduledTrips,
            'completedTrips' => $completedTrips,
            'scheduledOnlyTrips' => $scheduledOnlyTrips,
            'pendingDdrTrips' => $pendingDdrTrips,
            'missedTrips' => $missedTrips,
            'cancelledTrips' => $cancelledTrips,
            'tripCompletionRate' => $tripCompletionRate,
            'totalRequiredBuses' => $totalRequiredBuses,
            'totalAssignedBuses' => $totalAssignedBuses,
            'assignmentFulfillmentRate' => $assignmentFulfillmentRate,
            'gpsTripCount' => $gpsMetrics['tripCount'],
            'gpsTotalDistance' => $gpsMetrics['totalDistance'],
            'gpsTotalIdleMinutes' => $gpsMetrics['totalIdleMinutes'],
            'gpsAverageSpeed' => $gpsMetrics['averageSpeed'],
            'gpsAverageDuration' => $gpsMetrics['averageTripDuration'],
            'totalIncidents' => $totalIncidents,
            'breakdownsCount' => $breakdownsCount,
            'accidentsCount' => $accidentsCount,
            'otherIncidentsCount' => $otherIncidentsCount,
            'resolvedIncidents' => $resolvedIncidents,
            'replacementsDispatched' => $replacementsDispatched,
            'operationalTrend' => $operationalTrend,
            'tripStatusDistribution' => $tripStatusDistribution,
            'incidentTypeDistribution' => $incidentTypeDistribution,
            'recentTrips' => $recentTrips,
            'recentIncidents' => $recentIncidents,
        ];
    }

    /**
     * Compute Thesis Objective 6.1.2 Administrative Summary Data.
     */
    private function getAdministrativeData(Carbon $start, Carbon $end, array $dateBuckets): array
    {
        // 1. Users
        $allUsers = User::query()->get();
        $totalUsers = $allUsers->count();
        $activeUsers = $allUsers->where('status', 'Active')->count();
        $inactiveUsers = $allUsers->where('status', '!=', 'Active')->count();

        $usersByDepartment = $allUsers->groupBy(fn ($u) => trim((string) $u->department) ?: 'Unassigned')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $usersByRole = $allUsers->groupBy(fn ($u) => ucfirst(trim((string) $u->role)) ?: 'Staff')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $userStatusDistribution = [
            'Active' => $activeUsers,
            'Pending / Inactive' => $inactiveUsers,
        ];

        // 2. User Activities (Audit Logs)
        $activitiesQuery = ActivityLog::query()
            ->whereBetween('created_at', [$start, $end]);

        $totalActivities = $activitiesQuery->count();
        $allActivities = $activitiesQuery->orderBy('created_at', 'desc')->get();

        $activityByModule = $allActivities->groupBy(fn ($a) => trim((string) $a->module) ?: 'General')
            ->map(fn ($group) => $group->count())
            ->sortByDesc(fn ($cnt) => $cnt)
            ->toArray();

        $activityByEventType = $allActivities->groupBy(fn ($a) => ucfirst(strtolower(trim((string) $a->event_type))) ?: 'Action')
            ->map(fn ($group) => $group->count())
            ->sortByDesc(fn ($cnt) => $cnt)
            ->take(6)
            ->toArray();

        // 3. System Notifications
        $notificationsQuery = TopbarNotification::query()
            ->whereBetween('created_at', [$start, $end]);

        $totalNotifications = $notificationsQuery->count();
        $allNotifications = $notificationsQuery->orderBy('created_at', 'desc')->get();

        $notificationsByModule = $allNotifications->groupBy(fn ($n) => trim((string) $n->module) ?: 'General')
            ->map(fn ($group) => $group->count())
            ->sortByDesc(fn ($cnt) => $cnt)
            ->toArray();

        $notificationsByAction = $allNotifications->groupBy(fn ($n) => ucfirst(strtolower(str_replace('_', ' ', trim((string) $n->action)))))
            ->map(fn ($group) => $group->count())
            ->sortByDesc(fn ($cnt) => $cnt)
            ->take(6)
            ->toArray();

        // 4. Trend Over Time (User Activities vs Notifications)
        $adminTrend = collect($dateBuckets)->map(function (array $bucket) use ($allActivities, $allNotifications): array {
            $bStart = $bucket['start'];
            $bEnd = $bucket['end'];

            $actInBucket = $allActivities->filter(fn (ActivityLog $a) => $a->created_at && $a->created_at->betweenIncluded($bStart, $bEnd))->count();
            $notifInBucket = $allNotifications->filter(fn (TopbarNotification $n) => $n->created_at && $n->created_at->betweenIncluded($bStart, $bEnd))->count();

            return [
                'label' => $bucket['label'],
                'activities' => $actInBucket,
                'notifications' => $notifInBucket,
            ];
        });

        // 5. Recent Records
        $recentActivities = $allActivities->take(8)->values();
        $recentNotifications = $allNotifications->take(8)->values();

        return [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => $inactiveUsers,
            'userStatusDistribution' => $userStatusDistribution,
            'usersByDepartment' => $usersByDepartment,
            'usersByRole' => $usersByRole,
            'totalActivities' => $totalActivities,
            'activityByModule' => $activityByModule,
            'activityByEventType' => $activityByEventType,
            'totalNotifications' => $totalNotifications,
            'notificationsByModule' => $notificationsByModule,
            'notificationsByAction' => $notificationsByAction,
            'adminTrend' => $adminTrend,
            'recentActivities' => $recentActivities,
            'recentNotifications' => $recentNotifications,
        ];
    }

    private function tripQuery(string $selectedBus, Carbon $start, Carbon $end)
    {
        return GpsTripRecord::query()
            ->whereBetween('beginning_at', [$start, $end])
            ->whereHas('batchUpload', fn ($query) => $query->where('status', 'Processed'))
            ->when(
                $selectedBus !== '' && $selectedBus !== 'ALL',
                fn ($query) => $query->whereRaw('UPPER(TRIM(bus_no)) = ?', [$selectedBus])
            );
    }

    private function tripMetrics(Collection $records): array
    {
        $tripCount = $records->count();
        $totalDistance = (float) $records->sum('mileage_km');
        $totalIdleMinutes = (float) $records->sum('idling_minutes');
        $totalMotionMinutes = (float) $records->sum('in_motion_minutes');
        $durations = $records
            ->map(fn (GpsTripRecord $record) => $this->durationMinutes($record))
            ->filter(fn (float $minutes) => $minutes > 0);
        $averageTripDuration = $durations->isNotEmpty() ? (float) $durations->average() : 0.0;
        $speedMinutes = $totalMotionMinutes > 0 ? $totalMotionMinutes : (float) $durations->sum();
        $averageSpeed = $speedMinutes > 0 ? $totalDistance / ($speedMinutes / 60) : 0.0;

        return compact(
            'tripCount',
            'totalDistance',
            'totalIdleMinutes',
            'averageTripDuration',
            'averageSpeed'
        );
    }

    private function durationMinutes(GpsTripRecord $record): float
    {
        $duration = (float) ($record->duration_minutes ?? 0);
        if ($duration > 0) {
            return $duration;
        }

        $total = (float) ($record->total_minutes ?? 0);
        if ($total > 0) {
            return $total;
        }

        return $record->beginning_at && $record->ending_at
            ? (float) $record->beginning_at->diffInMinutes($record->ending_at)
            : 0.0;
    }

    private function periodBounds(string $period): array
    {
        $now = now();

        return match ($period) {
            'last-30-days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'Last 30 Days'],
            'last-3-months' => [$now->copy()->subMonths(3)->startOfDay(), $now->copy()->endOfDay(), 'Last 3 Months'],
            'this-year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay(), 'This Year'],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'This Month'],
        };
    }

    private function createDateBuckets(Carbon $start, Carbon $end, string $period): array
    {
        if ($period === 'this-year') {
            $buckets = [];
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $bStart = $cursor->copy()->startOfMonth();
                $bEnd = $cursor->copy()->endOfMonth();
                if ($bEnd->gt($end)) {
                    $bEnd = $end->copy();
                }
                $buckets[] = [
                    'label' => $bStart->format('M'),
                    'start' => $bStart,
                    'end' => $bEnd,
                ];
                $cursor->addMonth();
            }
            return $buckets;
        }

        $totalDays = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        if ($totalDays <= 7) {
            $buckets = [];
            $cursor = $start->copy()->startOfDay();
            while ($cursor->lte($end)) {
                $bStart = $cursor->copy()->startOfDay();
                $bEnd = $cursor->copy()->endOfDay();
                $buckets[] = [
                    'label' => $bStart->format('M j'),
                    'start' => $bStart,
                    'end' => $bEnd,
                ];
                $cursor->addDay();
            }
            return $buckets;
        }

        $bucketCount = min(7, $totalDays);
        $daysPerBucket = (int) ceil($totalDays / $bucketCount);
        $buckets = [];

        for ($i = 0; $i < $bucketCount; $i++) {
            $bStart = $start->copy()->startOfDay()->addDays($i * $daysPerBucket);
            if ($bStart->gt($end)) {
                break;
            }
            $bEnd = $bStart->copy()->addDays($daysPerBucket - 1)->endOfDay();
            if ($bEnd->gt($end) || $i === $bucketCount - 1) {
                $bEnd = $end->copy();
            }
            $buckets[] = [
                'label' => $bStart->format('M j'),
                'start' => $bStart,
                'end' => $bEnd,
            ];
        }

        return $buckets;
    }
}
