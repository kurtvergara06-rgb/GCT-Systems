<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\Bus;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TripAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $query = TripSchedule::query()
            ->with([
                'shuttleRoute',
                'assignment.bus',
                'assignment.driverAttendance',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('trip_code', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('assignment_status', 'like', "%{$search}%")
                    ->orWhereHas('shuttleRoute', function ($routeQuery) use ($search) {
                        $routeQuery
                            ->where('route_code', 'like', "%{$search}%")
                            ->orWhere('route_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('assignment', function ($assignmentQuery) use ($search) {
                        $assignmentQuery
                            ->where('driver_name', 'like', "%{$search}%")
                            ->orWhereHas('bus', function ($busQuery) use ($search) {
                                $busQuery->where('bus_no', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($request->filled('trip_date')) {
            $query->whereDate(
                'trip_date',
                $request->input('trip_date')
            );
        }

        if (
            $request->filled('status')
            && $request->input('status') !== 'all'
        ) {
            $status = $request->input('status');

            if (in_array($status, ['Assigned', 'Unassigned'], true)) {
                $query->where('assignment_status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        $trips = $query
            ->orderByDesc('trip_date')
            ->orderBy('departure_time')
            ->paginate(10, ['*'], 'trip_page')
            ->withQueryString();

        $today = now()->toDateString();

        $scheduledTripsToday = TripSchedule::query()
            ->whereDate('trip_date', $today)
            ->count();

        $pendingAssignments = TripSchedule::query()
            ->whereDate('trip_date', $today)
            ->where('assignment_status', 'Unassigned')
            ->count();

        $driverOptions = DriverAttendance::query()
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['Present', 'Late'])
            ->orderBy('driver_name')
            ->get();

        $busOptions = Bus::query()
            ->where('status', 'Active')
            ->orderBy('bus_no')
            ->get();

        $availableDrivers = DriverAttendance::query()
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['Present', 'Late'])
            ->orderBy('driver_name')
            ->paginate(10, ['*'], 'driver_page')
            ->withQueryString();

        $availableBuses = Bus::query()
            ->where('status', 'Active')
            ->orderBy('bus_no')
            ->paginate(10, ['*'], 'bus_page')
            ->withQueryString();

        $unassignedTrips = TripSchedule::query()
            ->with('shuttleRoute')
            ->where('assignment_status', 'Unassigned')
            ->whereNotIn('status', ['Cancelled', 'Completed', 'Dispatched'])
            ->orderBy('trip_date')
            ->orderBy('departure_time')
            ->get();

        return view(
            'Operation.Scheduling_And_Dispatch.driver-bus-assignment',
            compact(
                'trips',
                'scheduledTripsToday',
                'pendingAssignments',
                'availableDrivers',
                'availableBuses',
                'driverOptions',
                'busOptions',
                'unassignedTrips'
            )
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAssignment($request);

        $trip = TripSchedule::query()
            ->whereKey($validated['trip_schedule_id'])
            ->where('assignment_status', 'Unassigned')
            ->whereNotIn('status', ['Cancelled', 'Completed', 'Dispatched'])
            ->firstOrFail();

        $driver = DriverAttendance::query()
            ->whereKey($validated['driver_attendance_id'])
            ->whereDate('attendance_date', $trip->trip_date)
            ->whereIn('status', ['Present', 'Late'])
            ->firstOrFail();

        $bus = Bus::query()
            ->whereKey($validated['bus_id'])
            ->where('status', 'Active')
            ->firstOrFail();

        $this->ensureNoConflict(
            $trip,
            $driver->driver_id,
            $bus->id
        );

        DB::transaction(function () use ($trip, $driver, $bus) {
            TripAssignment::create([
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $driver->id,
                'driver_id' => $driver->driver_id,
                'driver_name' => $driver->driver_name,
                'bus_id' => $bus->id,
                'assigned_by' => auth()->id(),
            ]);

            $trip->update([
                'assignment_status' => 'Assigned',
                'status' => 'Ready',
            ]);
        });

        session()->flash(
            'success',
            'Driver and bus assigned successfully.'
        );

        return new RedirectResponse('/operation/driver-bus-assignment');
    }

    public function update(
        Request $request,
        TripAssignment $tripAssignment
    ): RedirectResponse {
        $tripAssignment->load('tripSchedule');

        $trip = $tripAssignment->tripSchedule;

        if (
            ! $trip
            || in_array($trip->status, ['Dispatched', 'Completed'], true)
        ) {
            session()->flash(
                'error',
                'Dispatched or completed assignments cannot be changed.'
            );

            return new RedirectResponse('/operation/driver-bus-assignment');
        }

        $validated = $this->validateAssignment(
            $request,
            $tripAssignment
        );

        $driver = DriverAttendance::query()
            ->whereKey($validated['driver_attendance_id'])
            ->whereDate('attendance_date', $trip->trip_date)
            ->whereIn('status', ['Present', 'Late'])
            ->firstOrFail();

        $bus = Bus::query()
            ->whereKey($validated['bus_id'])
            ->where('status', 'Active')
            ->firstOrFail();

        $this->ensureNoConflict(
            $trip,
            $driver->driver_id,
            $bus->id,
            $tripAssignment->id
        );

        $tripAssignment->update([
            'driver_attendance_id' => $driver->id,
            'driver_id' => $driver->driver_id,
            'driver_name' => $driver->driver_name,
            'bus_id' => $bus->id,
        ]);

        session()->flash(
            'success',
            'Assignment updated successfully.'
        );

        return new RedirectResponse('/operation/driver-bus-assignment');
    }

    public function destroy(
        TripAssignment $tripAssignment
    ): RedirectResponse {
        $tripAssignment->load('tripSchedule');

        $trip = $tripAssignment->tripSchedule;

        if (
            $trip
            && in_array($trip->status, ['Dispatched', 'Completed'], true)
        ) {
            session()->flash(
                'error',
                'Dispatched or completed assignments cannot be removed.'
            );

            return new RedirectResponse('/operation/driver-bus-assignment');
        }

        DB::transaction(function () use ($tripAssignment, $trip) {
            $tripAssignment->delete();

            $trip?->update([
                'assignment_status' => 'Unassigned',
                'status' => 'Scheduled',
            ]);
        });

        session()->flash(
            'success',
            'Assignment removed successfully.'
        );

        return new RedirectResponse('/operation/driver-bus-assignment');
    }

    private function validateAssignment(
        Request $request,
        ?TripAssignment $tripAssignment = null
    ): array {
        return $request->validate([
            'trip_schedule_id' => [
                $tripAssignment ? 'sometimes' : 'required',
                'integer',
                Rule::exists('trip_schedules', 'id'),
            ],
            'driver_attendance_id' => [
                'required',
                'integer',
                Rule::exists('driver_attendances', 'id'),
            ],
            'bus_id' => [
                'required',
                'integer',
                Rule::exists('buses', 'id'),
            ],
        ]);
    }

    private function ensureNoConflict(
        TripSchedule $trip,
        string $driverId,
        int $busId,
        ?int $ignoreAssignmentId = null
    ): void {
        $query = TripAssignment::query()
            ->whereHas('tripSchedule', function ($tripQuery) use ($trip) {
                $tripQuery
                    ->whereDate('trip_date', $trip->trip_date)
                    ->where('id', '!=', $trip->id)
                    ->whereNotIn('status', ['Cancelled', 'Completed']);
            })
            ->where(function ($builder) use ($driverId, $busId) {
                $builder
                    ->where('driver_id', $driverId)
                    ->orWhere('bus_id', $busId);
            })
            ->whereHas('tripSchedule', function ($tripQuery) use ($trip) {
                $tripQuery
                    ->where('departure_time', '<', $trip->estimated_arrival_time)
                    ->where('estimated_arrival_time', '>', $trip->departure_time);
            });

        if ($ignoreAssignmentId) {
            $query->whereKeyNot($ignoreAssignmentId);
        }

        if ($query->exists()) {
            abort(
                422,
                'The selected driver or bus has an overlapping trip.'
            );
        }
    }
}