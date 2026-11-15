<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\AutoScheduleSlotDecision;
use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationDashboardController extends Controller
{
    private const TERMINAL_INCIDENT_STATUSES = ['Resolved', 'Cancelled'];

    private const TERMINAL_TRIP_STATUSES = ['Completed', 'Cancelled', 'Missed'];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in([
                'all', 'Scheduled', 'Ready', 'Dispatched', 'Pending DDR',
                'Completed', 'Delayed', 'Missed', 'Cancelled',
            ])],
        ]);

        $timezone = config('app.business_timezone', 'Asia/Manila');
        $operationalDate = CarbonImmutable::parse(
            $validated['date'] ?? now($timezone)->toDateString(),
            $timezone
        );
        $today = $operationalDate->toDateString();
        $statusFilter = $validated['status'] ?? 'all';
        $canEditOperation = (bool) $request->user()?->hasSystemPermission('operation', 'edit');

        $trips = TripSchedule::query()
            ->with(['assignments.bus', 'assignments.driverMaster', 'shuttleRoute'])
            ->withCount('assignments')
            ->whereDate('trip_date', $today)
            ->orderBy('departure_time')
            ->orderBy('id')
            ->get();

        $actionableTrips = $trips->whereNotIn('status', self::TERMINAL_TRIP_STATUSES);

        $displayTrips = $statusFilter === 'all'
            ? $trips
            : $trips->where('status', $statusFilter)->values();
        $completedTrips = $trips->where('status', 'Completed')->count();

        $busStatuses = Bus::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $activeBuses = (int) ($busStatuses['Active'] ?? 0);
        $maintenanceBuses = (int) ($busStatuses['Under Maintenance'] ?? 0);
        $inactiveBuses = (int) ($busStatuses['Inactive'] ?? 0);
        $totalBuses = (int) $busStatuses->sum();
        $otherBuses = max(0, $totalBuses - $activeBuses - $maintenanceBuses - $inactiveBuses);

        $attendanceQuery = DriverAttendance::query()->whereDate('attendance_date', $today);
        $attendance = (clone $attendanceQuery)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $attendanceRecords = (clone $attendanceQuery)->count();
        $presentDrivers = (int) ($attendance['Present'] ?? 0);

        $assignedPresent = TripAssignment::query()
            ->whereIn('driver_id', (clone $attendanceQuery)
                ->where('status', 'Present')
                ->select('driver_id'))
            ->whereHas('tripSchedule', fn ($query) => $query
                ->whereDate('trip_date', $today)
                ->whereNotIn('status', self::TERMINAL_TRIP_STATUSES))
            ->distinct()
            ->count('driver_id');

        $totalDrivers = Driver::query()->count();
        $totalRoutes = ShuttleRoute::query()->where('status', 'Active')->count();
        $activeDrivers = Driver::query()->where('employment_status', 'Active')->count();
        $explicitlyUnavailableDriverIds = (clone $attendanceQuery)
            ->whereIn('status', ['Absent', 'On Leave'])
            ->pluck('driver_id')
            ->filter()
            ->unique();
        $driversAvailable = Driver::query()
            ->where('employment_status', 'Active')
            ->when(
                $explicitlyUnavailableDriverIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('driver_id', $explicitlyUnavailableDriverIds)
            )
            ->count();

        $pendingDecisions = AutoScheduleSlotDecision::query()
            ->where('status', 'Pending')
            ->whereHas('tripSchedule', fn ($query) => $query->whereDate('trip_date', $today))
            ->count();

        $requiredSlots = $actionableTrips->sum(fn (TripSchedule $trip) => max(1, (int) $trip->required_buses));
        // Historical rentals remain stored, but the current command center
        // deliberately reports only GCT fleet assignments.
        $assignedSlots = $actionableTrips->sum(fn (TripSchedule $trip) => min(max(1, (int) $trip->required_buses), $trip->assignments->count()));

        $activeIncidents = Incident::query()
            ->whereNotIn('status', self::TERMINAL_INCIDENT_STATUSES)
            ->count();
        $recentIncidents = Incident::query()
            ->with(['bus', 'tripSchedule'])
            ->orderByRaw("CASE WHEN status IN ('Resolved', 'Cancelled') THEN 1 ELSE 0 END")
            ->latest('incident_reported_at')
            ->limit(5)
            ->get();

        $recentReports = DailyDriverReport::query()
            ->with(['bus', 'driver', 'tripSchedule', 'additionalTrips.tripSchedule'])
            ->latest('report_date')
            ->latest('created_at')
            ->limit(5)
            ->get();

        $trendStart = $operationalDate->subDays(6);
        $trendCounts = TripSchedule::query()
            ->whereBetween('trip_date', [$trendStart->toDateString(), $today])
            ->selectRaw('DATE(trip_date) AS service_date, COUNT(*) AS scheduled_total')
            ->selectRaw("SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_total")
            ->groupByRaw('DATE(trip_date)')
            ->get()
            ->keyBy('service_date');

        $tripPerformance = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $trendCounts): array {
            $date = $trendStart->addDays($offset);
            $row = $trendCounts->get($date->toDateString());

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'scheduled' => (int) ($row?->scheduled_total ?? 0),
                'completed' => (int) ($row?->completed_total ?? 0),
            ];
        });

        $trendScheduled = $tripPerformance->sum('scheduled');
        $trendCompleted = $tripPerformance->sum('completed');
        $completionRate = $trendScheduled > 0
            ? (int) round(($trendCompleted / $trendScheduled) * 100)
            : 0;

        $incidentTrendCounts = Incident::query()
            ->whereBetween('incident_reported_at', [
                $trendStart->startOfDay()->toDateTimeString(),
                $operationalDate->endOfDay()->toDateTimeString(),
            ])
            ->selectRaw('DATE(incident_reported_at) AS incident_date, COUNT(*) AS incident_total')
            ->groupByRaw('DATE(incident_reported_at)')
            ->get()
            ->keyBy('incident_date');
        $incidentPerformance = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $incidentTrendCounts): array {
            $date = $trendStart->addDays($offset);
            $row = $incidentTrendCounts->get($date->toDateString());

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'total' => (int) ($row?->incident_total ?? 0),
            ];
        });

        return view('Operation.dashboard-operation', [
            'today' => $today,
            'operationalDate' => $operationalDate,
            'statusFilter' => $statusFilter,
            'canEditOperation' => $canEditOperation,
            'trips' => $trips,
            'displayTrips' => $displayTrips,
            'completedTrips' => $completedTrips,
            'totalBuses' => $totalBuses,
            'activeBuses' => $activeBuses,
            'maintenanceBuses' => $maintenanceBuses,
            'inactiveBuses' => $inactiveBuses,
            'otherBuses' => $otherBuses,
            'attendance' => $attendance,
            'attendanceRecords' => $attendanceRecords,
            'presentDrivers' => $presentDrivers,
            'assignedPresent' => $assignedPresent,
            'unassignedPresent' => max(0, $presentDrivers - $assignedPresent),
            'activeDrivers' => $activeDrivers,
            'isCurrentOperationalDate' => $today === now($timezone)->toDateString(),
            'driversAvailable' => $driversAvailable,
            'pendingDecisions' => $pendingDecisions,
            'requiredSlots' => $requiredSlots,
            'assignedSlots' => $assignedSlots,
            'activeIncidents' => $activeIncidents,
            'recentIncidents' => $recentIncidents,
            'recentReports' => $recentReports,
            'totalDrivers' => $totalDrivers,
            'totalRoutes' => $totalRoutes,
            'tripPerformance' => $tripPerformance,
            'incidentPerformance' => $incidentPerformance,
            'completionRate' => $completionRate,
            'trendScheduled' => $trendScheduled,
            'trendCompleted' => $trendCompleted,
        ]);
    }
}
