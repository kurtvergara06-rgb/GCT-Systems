<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class TripRecordController extends Controller
{
    private const RECORDS_PER_PAGE = 50;

    private function historyQuery(Request $request): \Illuminate\Database\Eloquent\Builder
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
                    ->orWhereHas('shuttleRoute', function ($routeQuery) use ($search) {
                        $routeQuery
                            ->where('route_code', 'like', "%{$search}%")
                            ->orWhere('route_name', 'like', "%{$search}%")
                            ->orWhere('origin', 'like', "%{$search}%")
                            ->orWhere('destination', 'like', "%{$search}%");
                    })
                    ->orWhereHas('assignment', function ($assignmentQuery) use ($search) {
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


        if ($request->filled('date_from')) $query->whereDate('trip_date', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('trip_date', '<=', $request->input('date_to'));
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
            fputcsv($out, ['Trip ID', 'Date', 'Shift', 'Route', 'Plate Number', 'Driver', 'Scheduled Departure', 'Estimated Arrival', 'Actual Departure', 'Actual Arrival', 'Distance (km)', 'Status']);
            $this->historyQuery($request)->with(['shuttleRoute', 'assignment.bus'])->orderBy('id')->chunkById(250, function ($trips) use ($out): void {
                foreach ($trips as $trip) {
                    $row = [
                        $trip->trip_code, $trip->trip_date?->format('Y-m-d'), $trip->shift,
                        $trip->shuttleRoute?->route_name, $trip->assignment?->bus?->plate_no,
                        $trip->assignment?->driver_name, $trip->departure_time, $trip->estimated_arrival_time,
                        $trip->actual_departure_time, $trip->actual_arrival_time,
                        $trip->shuttleRoute?->distance_km, $trip->status,
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
                'assignment.bus',
                'assignment.driverAttendance',
            ]);

        // Order: Most recent trips first, ordered by departure time descending
        $trips = $query
            ->orderByDesc('trip_date')
            ->orderByDesc('departure_time')
            ->paginate(self::RECORDS_PER_PAGE, ['*'], 'records_page')
            ->withQueryString();

        // Summary KPI statistics (historical trips only).
        $completedTripsCount = TripSchedule::where('status', 'Completed')->count();
        $delayedTripsCount = TripSchedule::where('status', 'Delayed')->count();

        // Only arrival timestamps can prove punctuality; incomplete data is
        // deliberately excluded from the denominator, never called on-time.
        $timedTrips = TripSchedule::query()
            ->where('status', 'Completed')
            ->whereNotNull('actual_arrival_time')
            ->whereNotNull('estimated_arrival_time')
            ->whereNotNull('departure_time')
            ->get(['trip_date', 'estimated_arrival_date', 'departure_time', 'estimated_arrival_time', 'actual_arrival_time']);
        $onTimeCount = $timedTrips->filter(function (TripSchedule $trip): bool {
            $scheduled = $trip->estimatedArrivalDateTime();
            $actual = \Carbon\Carbon::parse($trip->actual_arrival_time, config('app.business_timezone', 'Asia/Manila'));
            if (strlen((string) $trip->actual_arrival_time) <= 8) {
                $actual = $trip->trip_date->copy()->setTimeFromTimeString($trip->actual_arrival_time);
                if ($actual->lt($trip->departureDateTime())) $actual->addDay();
            }
            return $actual->lessThanOrEqualTo($scheduled);
        })->count();
        $onTimeRate = $timedTrips->count() ? round($onTimeCount / $timedTrips->count() * 100, 1) : null;

        // Total operational distance logged from completed trips
        $totalDistanceKm = TripSchedule::query()
            ->where('trip_schedules.status', 'Completed')
            ->join('shuttle_routes', 'trip_schedules.shuttle_route_id', '=', 'shuttle_routes.id')
            ->sum('shuttle_routes.distance_km');

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
