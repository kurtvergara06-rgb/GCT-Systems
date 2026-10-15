<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\DailyDriverReportTripEntry;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Support\TableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripRecordController extends Controller
{
    private const RECORDS_PER_PAGE = 50;

    private function historyQuery(Request $request): Builder
    {
        $query = TripSchedule::query()->whereIn('status', ['Completed', 'Delayed', 'Cancelled', 'Missed']);
        // Search
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('trip_code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('shift', 'like', "%{$search}%")
                    ->orWhere('route_code_snapshot', 'like', "%{$search}%")
                    ->orWhere('route_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('route_origin_snapshot', 'like', "%{$search}%")
                    ->orWhere('route_destination_snapshot', 'like', "%{$search}%")
                    ->orWhereHas('shuttleRoute', function ($routeQuery) use ($search) {
                        $routeQuery
                            ->where('route_code', 'like', "%{$search}%")
                            ->orWhere('route_name', 'like', "%{$search}%")
                            ->orWhere('origin', 'like', "%{$search}%")
                            ->orWhere('destination', 'like', "%{$search}%");
                    })
                    ->orWhereHas('assignments', function ($assignmentQuery) use ($search) {
                        $assignmentQuery
                            ->where('driver_name', 'like', "%{$search}%")
                            ->orWhere('driver_id', 'like', "%{$search}%")
                            ->orWhereHas('bus', function ($busQuery) use ($search) {
                                $busQuery
                                    ->where('bus_no', 'like', "%{$search}%")
                                    ->orWhere('plate_no', 'like', "%{$search}%")
                                    ->orWhere('bus_model', 'like', "%{$search}%");
                            });
                    });
            });
        }

        // Date filter
        if ($request->filled('trip_date')) {
            $query->whereDate('trip_date', $request->input('trip_date'));
        }

        // Route filter
        if ($request->filled('route') && $request->input('route') !== 'all') {
            $query->where('shuttle_route_id', $request->integer('route'));
        }

        // Shift filter
        if ($request->filled('shift') && $request->input('shift') !== 'all') {
            $query->where('shift', $request->input('shift'));
        }

        // Status filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('trip_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('trip_date', '<=', $request->input('date_to'));
        }

        return $query;
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'trip_date' => ['nullable', 'date_format:Y-m-d'],
            'shift' => ['nullable', 'in:all,Morning,Afternoon,Night'],
            'status' => ['nullable', 'in:all,Completed,Delayed,Cancelled,Missed'],
            'route' => ['nullable', 'regex:/^(all|[1-9][0-9]*)$/'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Trip ID', 'Date', 'Shift', 'Route', 'Plate Number', 'Driver', 'Scheduled Departure', 'Estimated Arrival', 'Actual Departure', 'Actual Arrival', 'Recorded Distance (km)', 'Planned Distance (km)', 'Status']);
            $this->historyQuery($request)
                ->with(['shuttleRoute', 'assignments.bus'])
                ->withSum('dailyDriverReports as primary_recorded_distance_km', 'km')
                ->withSum('dailyDriverReportTripEntries as additional_recorded_distance_km', 'km')
                ->orderBy('id')->chunkById(250, function ($trips) use ($out): void {
                    foreach ($trips as $trip) {
                        $recordedDistance = (float) ($trip->primary_recorded_distance_km ?? 0)
                            + (float) ($trip->additional_recorded_distance_km ?? 0);
                        $row = [
                            $trip->trip_code, $trip->trip_date?->format('Y-m-d'), $trip->shift,
                            $trip->route_name_snapshot ?: $trip->shuttleRoute?->route_name,
                            $trip->assignments->map(fn ($a) => $a->bus?->plate_no ?: $a->bus?->bus_no ?: '—')->implode('; '),
                            $trip->assignments->pluck('driver_name')->filter()->implode('; '), $trip->departure_time, $trip->estimated_arrival_time,
                            $trip->actual_departure_time, $trip->actual_arrival_time,
                            $recordedDistance ?: null,
                            $trip->planned_distance_km ?? $trip->shuttleRoute?->distance_km,
                            $trip->status,
                        ];
                        // Stop spreadsheet formula injection when CSV opens in Excel.
                        fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^\\s*[=+@-]/', $v) ? "'".$v : $v, $row));
                    }
                });
            fclose($out);
        }, 'trip-history.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function index(Request $request): View
    {
        $historyMode = $request->query('view', 'assignments');
        if ($historyMode !== 'operations') {
            $request->validate([
                'trip_date' => ['nullable', 'date_format:Y-m-d'],
                'search' => ['nullable', 'string', 'max:150'],
                'sort' => ['nullable', 'in:trip_date,trip_code,departure_time,status'],
                'sort_direction' => ['nullable', 'in:asc,desc'],
            ]);

            $now = now(config('app.business_timezone', 'Asia/Manila'));
            $driver = DB::connection()->getDriverName();
            $endSql = $driver === 'sqlite'
                ? "CASE WHEN estimated_arrival_date IS NOT NULL THEN datetime(estimated_arrival_date || ' ' || estimated_arrival_time) ELSE datetime(trip_date || ' ' || estimated_arrival_time, CASE WHEN time(estimated_arrival_time) <= time(departure_time) THEN '+1 day' ELSE '+0 day' END) END"
                : 'CASE WHEN estimated_arrival_date IS NOT NULL THEN TIMESTAMP(estimated_arrival_date, estimated_arrival_time) ELSE DATE_ADD(TIMESTAMP(trip_date, estimated_arrival_time), INTERVAL IF(estimated_arrival_time <= departure_time, 1, 0) DAY) END';
            $query = TripSchedule::query()
                ->with(['shuttleRoute', 'assignments.bus', 'externalRentals'])
                ->where(function ($q) use ($endSql, $now) {
                    $q->whereIn('status', ['Completed', 'Cancelled', 'Missed'])
                        ->orWhereRaw("($endSql) <= ?", [$now->format('Y-m-d H:i:s')]);
                });
            if ($request->filled('search')) {
                $term = trim((string) $request->input('search'));
                $query->where(function ($q) use ($term) {
                    $q->where('trip_code', 'like', "%{$term}%")
                        ->orWhereHas('assignments', fn ($a) => $a->where('driver_name', 'like', "%{$term}%")
                            ->orWhereHas('bus', fn ($b) => $b->where('bus_no', 'like', "%{$term}%")));
                });
            }
            if ($request->filled('trip_date')) {
                $query->whereDate('trip_date', $request->input('trip_date'));
            }
            TableSort::apply(
                $query,
                $request,
                [
                    'trip_date' => 'trip_date',
                    'trip_code' => 'trip_code',
                    'departure_time' => 'departure_time',
                    'status' => 'status',
                ],
                'trip_date',
                'desc'
            );
            $assignmentHistoryTrips = $query
                ->paginate(self::RECORDS_PER_PAGE, ['*'], 'assignments_page')
                ->withQueryString();

            return view('Operation.Trip_Records.assignment-history', compact('assignmentHistoryTrips'));
        }

        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'trip_date' => ['nullable', 'date_format:Y-m-d'],
            'shift' => ['nullable', 'in:all,Morning,Afternoon,Night'],
            'status' => ['nullable', 'in:all,Completed,Delayed,Cancelled,Missed'],
            'route' => ['nullable', 'regex:/^(all|[1-9][0-9]*)$/'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $query = $this->historyQuery($request)->with([
            'shuttleRoute',
            'assignments.bus',
            'assignments.driverAttendance',
            'assignment.bus',
            'assignment.driverAttendance',
        ])
            ->withSum('dailyDriverReports as primary_recorded_distance_km', 'km')
            ->withSum('dailyDriverReportTripEntries as additional_recorded_distance_km', 'km');

        // Order: Most recent trips first, ordered by departure time descending
        TableSort::apply($query, $request, ['trip_date' => 'trip_date', 'trip_code' => 'trip_code', 'departure_time' => 'departure_time', 'status' => 'status'], 'trip_date', 'desc');
        $trips = $query
            ->paginate(self::RECORDS_PER_PAGE, ['*'], 'records_page')
            ->withQueryString();

        // Summary KPI statistics (historical trips only).
        $completedTripsCount = TripSchedule::where('status', 'Completed')->count();
        $delayedTripsCount = TripSchedule::where('status', 'Delayed')->count();

        // Only arrival timestamps can prove punctuality; incomplete data is
        // deliberately excluded from the denominator, never called on-time.
        $timedTrips = TripSchedule::query()
            ->whereIn('status', ['Completed', 'Delayed'])
            ->whereNotNull('actual_departure_time')
            ->whereNotNull('actual_arrival_time')
            ->whereNotNull('estimated_arrival_time')
            ->whereNotNull('departure_time');
        $timedTripsCount = (clone $timedTrips)->count();
        $onTimeSql = DB::connection()->getDriverName() === 'sqlite'
            ? "datetime(date(trip_date, CASE WHEN actual_arrival_time < departure_time THEN '+1 day' ELSE '+0 day' END) || ' ' || actual_arrival_time) <= datetime(COALESCE(estimated_arrival_date, date(trip_date, CASE WHEN estimated_arrival_time <= departure_time THEN '+1 day' ELSE '+0 day' END)) || ' ' || estimated_arrival_time)"
            : 'TIMESTAMP(CASE WHEN actual_arrival_time < departure_time THEN DATE_ADD(trip_date, INTERVAL 1 DAY) ELSE trip_date END, actual_arrival_time) <= TIMESTAMP(COALESCE(estimated_arrival_date, CASE WHEN estimated_arrival_time <= departure_time THEN DATE_ADD(trip_date, INTERVAL 1 DAY) ELSE trip_date END), estimated_arrival_time)';
        $onTimeCount = (clone $timedTrips)->whereRaw($onTimeSql)->count();
        $onTimeRate = $timedTripsCount
            ? round($onTimeCount / $timedTripsCount * 100, 1)
            : null;

        // Only driver-recorded values are presented as distance traveled.
        $totalDistanceKm = DailyDriverReport::query()
            ->whereHas('tripSchedule', fn ($query) => $query->where('status', 'Completed'))
            ->sum('km')
            + DailyDriverReportTripEntry::query()
                ->whereHas('tripSchedule', fn ($query) => $query->where('status', 'Completed'))
                ->sum('km');

        // Active distinct buses used in completed trip assignments
        $activeFleetCount = TripAssignment::query()
            ->whereHas('tripSchedule', fn ($q) => $q->where('status', 'Completed'))
            ->whereNotNull('bus_id')
            ->distinct('bus_id')
            ->count('bus_id');

        // Filter options
        $routes = ShuttleRoute::query()
            ->orderBy('route_code')
            ->get(['id', 'route_code', 'route_name', 'origin', 'destination', 'distance_km']);

        $shifts = ['Morning', 'Afternoon', 'Night'];
        $statuses = ['Completed', 'Delayed', 'Cancelled', 'Missed'];

        return view('Operation.Trip_Records.trip-records', [
            'trips' => $trips,
            'totalCompletedTrips' => $completedTripsCount,
            'onTimeRate' => $onTimeRate,
            'totalDistanceKm' => round($totalDistanceKm, 1),
            'activeFleetCount' => $activeFleetCount,
            'delayedTripsCount' => $delayedTripsCount,
            'routes' => $routes,
            'shifts' => $shifts,
            'statuses' => $statuses,
        ]);
    }
}
