<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripRecordController extends Controller
{
    public function index(Request $request): View
    {
        $query = TripSchedule::query()
            ->with([
                'shuttleRoute',
                'assignment.bus',
                'assignment.driverAttendance',
            ]);

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

        // Order: Most recent trips first, ordered by departure time descending
        $trips = $query
            ->orderByDesc('trip_date')
            ->orderByDesc('departure_time')
            ->paginate(10)
            ->withQueryString();

        // Summary KPI statistics
        $allTripsCount = TripSchedule::count();
        $completedTripsCount = TripSchedule::where('status', 'Completed')->count();
        $delayedTripsCount = TripSchedule::where('status', 'Delayed')->count();

        // On-time rate: completed trips that are not delayed
        $onTimeRate = $allTripsCount > 0
            ? round((($completedTripsCount) / max(1, ($completedTripsCount + $delayedTripsCount))) * 100, 1)
            : 100;

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
        $statuses = ['Completed', 'Delayed', 'Scheduled', 'Ready', 'Cancelled'];

        return view('Operation.Trip_Records.trip-records', [
            'trips' => $trips,
            'totalCompletedTrips' => $completedTripsCount,
            'onTimeRate' => $onTimeRate,
            'totalDistanceKm' => round($totalDistanceKm, 1),
            'activeFleetCount' => $activeFleetCount,
            'routes' => $routes,
            'shifts' => $shifts,
            'statuses' => $statuses,
        ]);
    }
}
