<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\AutoScheduleSlotDecision;
use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\MechanicAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Illuminate\Contracts\View\View;

class OperationDashboardController extends Controller
{
    public function index(): View
    {
        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $trips = TripSchedule::query()
            ->with(['assignments.bus', 'shuttleRoute', 'externalRentals'])
            ->whereDate('trip_date', $today)
            ->orderBy('departure_time')
            ->orderBy('id')
            ->get();

        $busStatuses = Bus::query()->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')->pluck('total', 'status');
        $attendance = DriverAttendance::query()->whereDate('attendance_date', $today)
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $mechanics = MechanicAttendance::query()->whereDate('attendance_date', $today)
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        $pendingDecisions = AutoScheduleSlotDecision::query()
            ->where('status', 'Pending')
            ->whereHas('tripSchedule', fn ($query) => $query->whereDate('trip_date', $today))
            ->count();

        $todayRequired = $trips->sum(fn ($trip) => max(1, (int) $trip->required_buses));
        $todayAssigned = $trips->sum(fn ($trip) => $trip->assigned_buses_count);

        $recentIncidents = Incident::query()
            ->with(['bus', 'reporter'])
            ->latest('incident_reported_at')
            ->take(5)
            ->get();

        $recentReports = DailyDriverReport::query()
            ->with(['bus', 'driver'])
            ->latest('created_at')
            ->take(5)
            ->get();

        $totalDrivers = Driver::query()->count();
        $totalRoutes = ShuttleRoute::query()->count();

        return view('Operation.dashboard-operation', [
            'today' => $today,
            'trips' => $trips,
            'totalBuses' => Bus::query()->count(),
            'activeBuses' => (int) ($busStatuses['Active'] ?? 0),
            'maintenanceBuses' => (int) ($busStatuses['Under Maintenance'] ?? 0),
            'inactiveBuses' => (int) ($busStatuses['Inactive'] ?? 0),
            'driverAttendance' => $attendance,
            'mechanicAttendance' => $mechanics,
            'driversAvailable' => (int) ($attendance['Present'] ?? 0) + (int) ($attendance['Late'] ?? 0),
            'pendingDecisions' => $pendingDecisions,
            'requiredSlots' => $todayRequired,
            'assignedSlots' => $todayAssigned,
            'activeIncidents' => Incident::query()->whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'recentIncidents' => $recentIncidents,
            'recentReports' => $recentReports,
            'totalDrivers' => $totalDrivers,
            'totalRoutes' => $totalRoutes,
        ]);
    }
}
