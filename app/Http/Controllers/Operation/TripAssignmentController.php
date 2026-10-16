<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Support\TableSort;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TripAssignmentController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function index(Request $request): View|RedirectResponse
    {
        $query = TripSchedule::query()
            ->with([
                'shuttleRoute',
                'externalRentals',
                'incidents' => fn ($incidentQuery) => $incidentQuery->where('incident_type', 'Bus Breakdown')->with('replacement'),
                'assignments.bus',
                'assignments.driverAttendance',
                'assignments.reserveApproval',
                'slotDecisions' => fn ($decisionQuery) => $decisionQuery
                    ->where('status', 'Pending')
                    ->orderBy('slot_number'),
                'assignments' => fn ($assignmentQuery) => $assignmentQuery
                    ->withCount(['dailyDriverReports', 'incidents']),
                'assignment.bus',
                'assignment.driverAttendance',
                'assignment.reserveApproval',
                'assignment' => fn ($assignmentQuery) => $assignmentQuery
                    ->withCount(['dailyDriverReports', 'incidents']),
            ])
            ->withCount([
                'dailyDriverReports',
                'incidents',
                'assignments',
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
                    ->orWhereHas('assignments', function ($assignmentQuery) use ($search) {
                        $assignmentQuery
                            ->where('driver_name', 'like', "%{$search}%")
                            ->orWhereHas('bus', function ($busQuery) use ($search) {
                                $busQuery->where('bus_no', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $request->validate([
            'trip_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);

        if ($request->filled('trip_date')) {
            $request->session()->put(
                'operation.selected_trip_date',
                $request->input('trip_date')
            );
        }

        $selectedTripDate = $request->input('trip_date')
            ?: $request->session()->get('operation.selected_trip_date')
            ?: now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        // Historical assignments now live exclusively in Trip History.
        if ($request->query('tab') === 'history') {
            return redirect()->route('trip-records', ['view' => 'assignments']);
        }

        $query->whereDate('trip_date', $selectedTripDate);
        $nowTimestamp = now(config('app.business_timezone', 'Asia/Manila'))->format('Y-m-d H:i:s');
        $driver = DB::connection()->getDriverName();
        $endExpression = $driver === 'sqlite'
            ? "CASE WHEN estimated_arrival_date IS NOT NULL THEN datetime(estimated_arrival_date || ' ' || estimated_arrival_time) ELSE datetime(trip_date || ' ' || estimated_arrival_time, CASE WHEN time(estimated_arrival_time) <= time(departure_time) THEN '+1 day' ELSE '+0 day' END) END"
            : "CASE WHEN estimated_arrival_date IS NOT NULL THEN TIMESTAMP(estimated_arrival_date, estimated_arrival_time) ELSE DATE_ADD(TIMESTAMP(trip_date, estimated_arrival_time), INTERVAL IF(estimated_arrival_time <= departure_time, 1, 0) DAY) END";
        // Missed filtering must include expired unassigned trips, even after ETA.
        // All other views remain scoped to future/ongoing assignment windows.
        if ($request->input('status') !== 'Missed') {
            $query->whereNotIn('status', ['Completed', 'Cancelled', 'Missed'])
                ->whereRaw("($endExpression) > ?", [$nowTimestamp]);
        }

        if (
            $request->filled('status')
            && $request->input('status') !== 'all'
        ) {
            $status = $request->input('status');

            if (in_array($status, ['Assigned', 'Partially Assigned', 'Unassigned'], true)) {
                $query
                    ->where('assignment_status', $status)
                    ->whereNotIn('status', ['Cancelled']);
            } elseif ($status === 'Missed') {
                $now = now(config('app.business_timezone', 'Asia/Manila'))
                    ->startOfMinute();

                $query->where(function ($missedQuery) use ($now): void {
                    $missedQuery->where('status', 'Missed')
                        ->orWhere(function ($overdue) use ($now): void {
                            $overdue->where('assignment_status', 'Unassigned')
                                ->where('status', 'Scheduled')
                                ->where(function ($dateQuery) use ($now): void {
                                    $dateQuery
                                        ->whereDate('trip_date', '<', $now->toDateString())
                                        ->orWhere(function ($sameDay) use ($now): void {
                                            $sameDay->whereDate('trip_date', $now->toDateString())
                                                ->whereTime('departure_time', '<', $now->format('H:i:s'));
                                        });
                                });
                        });
                });
            } else {
                $query->where('status', $status);
            }
        }

        TableSort::apply($query, $request, ['departure_time' => 'departure_time', 'trip_code' => 'trip_code', 'status' => 'status'], 'departure_time');
        $trips = $query
            ->paginate(10, ['*'], 'trip_page')
            ->withQueryString();

        $scheduledTripsForDate = TripSchedule::query()
            ->whereDate('trip_date', $selectedTripDate)
            ->whereIn('status', ['Scheduled', 'Ready'])
            ->whereRaw("($endExpression) > ?", [$nowTimestamp])
            ->count();

        $pendingAssignments = TripSchedule::query()
            ->notDeparted()
            ->whereDate('trip_date', $selectedTripDate)
            ->whereIn('status', ['Scheduled', 'Ready'])
            ->where('assignment_status', '!=', 'Assigned')
            ->whereHas(
                'shuttleRoute',
                fn ($query) => $query->where(
                    'status',
                    'Active'
                )
            )
            ->count();

        $availableDrivers = DriverAttendance::query()
            ->whereDate('attendance_date', $selectedTripDate)
            ->whereIn('status', ['Present', 'Late'])
            ->whereHas('driver', fn ($query) => $query
                ->where('employment_status', 'Active')
                ->where(function ($licenseQuery) use ($selectedTripDate): void {
                    $licenseQuery
                        ->whereNull('license_expiration')
                        ->orWhereDate('license_expiration', '>=', $selectedTripDate);
                }))
            ->orderBy('driver_name')
            ->paginate(10, ['*'], 'driver_page')
            ->withQueryString();

        $availableBuses = Bus::query()
            ->where('status', 'Active')
            ->where('fleet_purpose', 'Regular')
            ->whereNotIn('bus_no', JobOrder::query()->where('status', '!=', 'Completed')->select('bus_no'))
            ->orderBy('bus_no')
            ->paginate(10, ['*'], 'bus_page')
            ->withQueryString();

        $unassignedTrips = TripSchedule::query()
            ->with(['shuttleRoute', 'assignments.bus', 'assignments.driverAttendance'])
            ->withCount('assignments')
            ->notDeparted()
            ->whereDate('trip_date', $selectedTripDate)
            ->whereIn('status', ['Scheduled', 'Ready'])
            ->where('assignment_status', '!=', 'Assigned')
            ->whereHas(
                'shuttleRoute',
                fn ($query) => $query->where(
                    'status',
                    'Active'
                )
            )
            ->orderBy('trip_date')
            ->orderBy('departure_time')
            ->get();

        return view(
            'Operation.Scheduling_And_Dispatch.driver-bus-assignment',
            compact(
                'trips',
                'scheduledTripsForDate',
                'pendingAssignments',
                'availableDrivers',
                'availableBuses',
                'unassignedTrips',
                'selectedTripDate'
            )
        );
    }

    /**
     * Print saved assignments for a selected trip date, without modifying records.
     */
    public function printSheet(Request $request): View
    {
        $validated = $request->validate([
            'trip_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $selectedTripDate = $validated['trip_date'];
        $assignments = TripAssignment::query()
            ->with(['bus', 'reserveApproval', 'tripSchedule.shuttleRoute'])
            ->whereHas('tripSchedule', fn ($query) => $query->whereDate('trip_date', $selectedTripDate))
            ->orderBy('trip_schedule_id')
            ->orderBy('id')
            ->get()
            ->sortBy(fn ($assignment) => $assignment->tripSchedule->departure_time.'|'.str_pad((string) $assignment->trip_schedule_id, 10, '0', STR_PAD_LEFT).'|'.str_pad((string) $assignment->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $slotNumbers = [];
        foreach ($assignments->groupBy('trip_schedule_id') as $group) {
            foreach ($group->values() as $index => $assignment) {
                $slotNumbers[$assignment->id] = $index + 1;
            }
        }

        $externalAssignments = \App\Models\Operation\ExternalBusRental::query()
            ->with('tripSchedule.shuttleRoute')
            ->where('status', 'Assigned')
            ->whereHas('tripSchedule', fn ($query) => $query->whereDate('trip_date', $selectedTripDate))
            ->get();

        return view('Operation.Scheduling_And_Dispatch.assignment-print', compact(
            'assignments',
            'externalAssignments',
            'selectedTripDate',
            'slotNumbers'
        ));
    }

    public function availability(Request $request, TripSchedule $tripSchedule): JsonResponse
    {
        if (
            $tripSchedule->hasDeparted()
            || ! in_array($tripSchedule->status, ['Scheduled', 'Ready'], true)
        ) {
            throw ValidationException::withMessages([
                'trip_schedule_id' => 'This trip is not available for assignment.',
            ]);
        }

        $tripSchedule->loadMissing('shuttleRoute');
        $this->assertRouteIsActive($tripSchedule);

        $ignoreAssignmentId = $request->integer('assignment_id');

        $assignedToThisTrip = $tripSchedule->assignments()
            ->when($ignoreAssignmentId, fn ($q) => $q->where('id', '!=', $ignoreAssignmentId))
            ->get();

        if (! $ignoreAssignmentId && $assignedToThisTrip->count() >= max(1, (int) ($tripSchedule->required_buses ?? 1))) {
            throw ValidationException::withMessages([
                'trip_schedule_id' => 'This trip is already fully assigned ('.$assignedToThisTrip->count().'/'.$tripSchedule->required_buses.' buses).',
            ]);
        }

        $conflictingAssignments = $this->conflictingAssignmentsForTrip(
            $tripSchedule,
            $ignoreAssignmentId
        );

        $busyDriverIds = $conflictingAssignments
            ->pluck('driver_id')
            ->concat($assignedToThisTrip->pluck('driver_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $busyBusIds = $conflictingAssignments
            ->pluck('bus_id')
            ->concat($assignedToThisTrip->pluck('bus_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $drivers = DriverAttendance::query()
            ->whereDate('attendance_date', $tripSchedule->trip_date)
            ->whereIn('status', ['Present', 'Late'])
            ->whereHas('driver', fn ($query) => $query
                ->where('employment_status', 'Active')
                ->where(function ($licenseQuery) use ($tripSchedule): void {
                    $licenseQuery
                        ->whereNull('license_expiration')
                        ->orWhereDate('license_expiration', '>=', $tripSchedule->trip_date);
                }))
            ->orderBy('driver_name')
            ->when(
                $busyDriverIds !== [],
                fn ($query) => $query->whereNotIn('driver_id', $busyDriverIds)
            )
            ->get()
            ->values()
            ->map(fn (DriverAttendance $attendance) => [
                'id' => $attendance->id,
                'driver_id' => $attendance->driver_id,
                'name' => $attendance->driver_name,
                'shift' => $attendance->shift,
                'status' => $attendance->status,
            ]);

        $buses = Bus::query()
            ->where('status', 'Active')
            ->where('fleet_purpose', 'Regular')
            ->whereNotIn('bus_no', JobOrder::query()->where('status', '!=', 'Completed')->select('bus_no'))
            ->when(
                $busyBusIds !== [],
                fn ($query) => $query->whereNotIn('id', $busyBusIds)
            )
            ->orderBy('bus_no')
            ->get()
            ->values()
            ->map(fn (Bus $bus) => [
                'id' => $bus->id,
                'bus_no' => $bus->bus_no,
                'model' => $bus->bus_model,
                'plate_no' => $bus->plate_no,
            ]);

        return response()->json([
            'trip' => [
                'id' => $tripSchedule->id,
                'date' => $tripSchedule->trip_date?->toDateString(),
                'shift' => $tripSchedule->shift,
            ],
            'drivers' => $drivers,
            'buses' => $buses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAssignment($request);

        $trip = DB::transaction(
            function () use ($validated): TripSchedule {
                $trip = TripSchedule::query()
                    ->with('shuttleRoute')
                    ->whereKey($validated['trip_schedule_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $trip->hasDeparted()
                    || ! in_array($trip->status, ['Scheduled', 'Ready'], true)
                    || $trip->assignment_status === 'Assigned'
                ) {
                    throw ValidationException::withMessages([
                        'trip_schedule_id' => 'This trip is no longer available for assignment.',
                    ]);
                }

                $assignedCount = $trip->assignments()->lockForUpdate()->count()
                    + DB::table('external_bus_rentals')
                        ->where('trip_schedule_id', $trip->id)
                        ->where('status', 'Assigned')->count();
                $requiredCount = max(1, (int) ($trip->required_buses ?? 1));

                if ($assignedCount >= $requiredCount) {
                    throw ValidationException::withMessages([
                        'trip_schedule_id' => "This trip is already fully assigned ({$assignedCount}/{$requiredCount} buses).",
                    ]);
                }

                $usedSlots = $trip->assignments()
                    ->lockForUpdate()
                    ->pluck('slot_number')
                    ->merge(DB::table('external_bus_rentals')
                        ->where('trip_schedule_id', $trip->id)
                        ->where('status', 'Assigned')
                        ->pluck('slot_number'))
                    ->filter()
                    ->map(fn ($slot) => (int) $slot);
                $slotNumber = collect(range(1, $requiredCount))
                    ->first(fn (int $slot): bool => ! $usedSlots->contains($slot));

                if ($slotNumber === null) {
                    throw ValidationException::withMessages([
                        'trip_schedule_id' => 'No unfilled assignment slot remains for this trip.',
                    ]);
                }

                if (isset($validated['slot_number']) && (int) $validated['slot_number'] !== $slotNumber) {
                    throw ValidationException::withMessages([
                        'slot_number' => 'The pending slot has changed. Refresh the assignment list and try again.',
                    ]);
                }

                $this->assertAssignmentMutable($trip);
                $this->assertRouteIsActive($trip);

                $driver = $this->lockEligibleDriver(
                    $validated['driver_attendance_id'],
                    $trip
                );

                $bus = $this->lockEligibleBus($validated['bus_id']);
                $this->assertDriverAuthorizedForBus($driver->driver_id, $bus->id);

                if ($trip->assignments()->where('driver_id', $driver->driver_id)->exists()) {
                    throw ValidationException::withMessages([
                        'driver_attendance_id' => 'The selected driver is already assigned to this trip schedule.',
                    ]);
                }

                if ($trip->assignments()->where('bus_id', $bus->id)->exists()) {
                    throw ValidationException::withMessages([
                        'bus_id' => 'The selected bus is already assigned to this trip schedule.',
                    ]);
                }

                $this->ensureNoConflict(
                    $trip,
                    $driver->driver_id,
                    $bus->id
                );

                $assignment = TripAssignment::create([
                    'trip_schedule_id' => $trip->id,
                    'slot_number' => $slotNumber,
                    'driver_attendance_id' => $driver->id,
                    'driver_id' => $driver->driver_id,
                    'driver_name' => $driver->driver_name,
                    'bus_id' => $bus->id,
                    'assigned_by' => auth()->id(),
                ]);

                // A later internal assignment replaces a rental arrangement for this slot.
                DB::table('external_bus_rentals')
                    ->where('trip_schedule_id', $trip->id)
                    ->where('slot_number', $slotNumber)
                    ->whereIn('status', ['Pending', 'Confirmed'])
                    ->update([
                        'status' => 'Superseded',
                        'updated_at' => now(),
                    ]);

                DB::table('auto_schedule_slot_decisions')
                    ->where('trip_schedule_id', $trip->id)
                    ->where('slot_number', $slotNumber)
                    ->where('status', 'Pending')
                    ->update([
                        'status' => 'Resolved',
                        'fulfilled_by_assignment_id' => $assignment->id,
                        'resolved_at' => now(),
                        'updated_at' => now(),
                    ]);

                $trip->syncAssignmentStatus();

                return $trip;
            },
            5
        );

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            $trip->id,
            "{$trip->trip_code} driver and bus assignment was added ({$trip->assignments()->count()}/{$trip->required_buses})."
        );

        session()->flash(
            'success',
            'Driver and bus assigned successfully.'
        );

        return $this->redirectToIndex($request);
    }

    public function update(
        Request $request,
        TripAssignment $tripAssignment
    ): RedirectResponse {
        $validated = $this->validateAssignment(
            $request,
            $tripAssignment
        );

        $trip = DB::transaction(
            function () use ($validated, $tripAssignment): TripSchedule {
                $lockedAssignment = TripAssignment::query()
                    ->whereKey($tripAssignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $trip = TripSchedule::query()
                    ->with('shuttleRoute')
                    ->whereKey($lockedAssignment->trip_schedule_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertAssignmentMutable($trip, $lockedAssignment);
                $this->assertRouteIsActive($trip);

                $driver = $this->lockEligibleDriver(
                    $validated['driver_attendance_id'],
                    $trip
                );

                $bus = $this->lockEligibleBus($validated['bus_id']);
                $this->assertDriverAuthorizedForBus($driver->driver_id, $bus->id);

                if ($trip->assignments()->where('id', '!=', $lockedAssignment->id)->where('driver_id', $driver->driver_id)->exists()) {
                    throw ValidationException::withMessages([
                        'driver_attendance_id' => 'The selected driver is already assigned to another bus on this trip schedule.',
                    ]);
                }

                if ($trip->assignments()->where('id', '!=', $lockedAssignment->id)->where('bus_id', $bus->id)->exists()) {
                    throw ValidationException::withMessages([
                        'bus_id' => 'The selected bus is already assigned to another driver on this trip schedule.',
                    ]);
                }

                $this->ensureNoConflict(
                    $trip,
                    $driver->driver_id,
                    $bus->id,
                    $lockedAssignment->id
                );

                $lockedAssignment->update([
                    'driver_attendance_id' => $driver->id,
                    'driver_id' => $driver->driver_id,
                    'driver_name' => $driver->driver_name,
                    'bus_id' => $bus->id,
                ]);

                $trip->syncAssignmentStatus();

                return $trip;
            },
            5
        );

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            $trip->id,
            "{$trip->trip_code} driver/bus assignment was updated."
        );

        session()->flash(
            'success',
            'Assignment updated successfully.'
        );

        return $this->redirectToIndex($request);
    }

    public function destroy(
        Request $request,
        TripAssignment $tripAssignment
    ): RedirectResponse {
        $trip = DB::transaction(
            function () use ($tripAssignment): TripSchedule {
                $lockedAssignment = TripAssignment::query()
                    ->whereKey($tripAssignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $trip = TripSchedule::query()
                    ->whereKey($lockedAssignment->trip_schedule_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertAssignmentMutable($trip, $lockedAssignment);

                DB::table('auto_schedule_slot_decisions')
                    ->where('fulfilled_by_assignment_id', $lockedAssignment->id)
                    ->update([
                        'status' => 'Pending',
                        'fulfilled_by_assignment_id' => null,
                        'resolved_at' => null,
                        'updated_at' => now(),
                    ]);

                $lockedAssignment->delete();

                $trip->syncAssignmentStatus();

                return $trip;
            },
            5
        );

        $remainingCount = $trip->assignments()->count();
        $message = $remainingCount === 0
            ? "{$trip->trip_code} returned to Unassigned after its assignment was removed."
            : "{$trip->trip_code} has {$remainingCount}/{$trip->required_buses} assigned bus(es) remaining.";

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            $trip->id,
            $message
        );

        session()->flash(
            'success',
            'Assignment removed successfully.'
        );

        return $this->redirectToIndex($request);
    }

    private function assertAssignmentMutable(
        TripSchedule $trip,
        ?TripAssignment $assignment = null
    ): void {
        if ($trip->hasDeparted()) {
            throw ValidationException::withMessages([
                'trip_schedule_id' => 'Past trips are historical records and their assignments cannot be changed.',
            ]);
        }

        if (in_array($trip->status, ['Cancelled', 'Dispatched', 'Completed'], true)) {
            throw ValidationException::withMessages([
                'trip_schedule_id' => 'Cancelled, dispatched, or completed assignments cannot be changed.',
            ]);
        }

        $hasOperationalHistory = $trip->dailyDriverReports()->exists()
            || $trip->incidents()->exists();

        if ($assignment) {
            $hasOperationalHistory = $hasOperationalHistory
                || DB::table('daily_driver_reports')
                    ->where('trip_assignment_id', $assignment->id)
                    ->exists()
                || DB::table('incidents')
                    ->where('trip_assignment_id', $assignment->id)
                    ->exists();
        }

        if ($hasOperationalHistory) {
            throw ValidationException::withMessages([
                'trip_schedule_id' => 'Assignments linked to incidents or Daily Driver Reports are historical records and cannot be changed.',
            ]);
        }
    }

    private function lockEligibleDriver(
        int $attendanceId,
        TripSchedule $trip
    ): DriverAttendance {
        $attendance = DriverAttendance::query()
            ->whereKey($attendanceId)
            ->whereDate('attendance_date', $trip->trip_date)
            ->whereIn('status', ['Present', 'Late'])
            ->lockForUpdate()
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'driver_attendance_id' => 'Select a present driver attendance record for this trip date.',
            ]);
        }

        $activeDriver = Driver::query()
            ->where('driver_id', $attendance->driver_id)
            ->where('employment_status', 'Active')
            ->lockForUpdate()
            ->first();

        if (! $activeDriver) {
            throw ValidationException::withMessages([
                'driver_attendance_id' => 'The selected driver is inactive and cannot be assigned.',
            ]);
        }

        if (
            $activeDriver->license_expiration
            && $activeDriver->license_expiration->toDateString()
                < $trip->trip_date->toDateString()
        ) {
            throw ValidationException::withMessages([
                'driver_attendance_id' => 'The selected driver\'s license expires before this trip date.',
            ]);
        }

        return $attendance;
    }

    private function assertRouteIsActive(
        TripSchedule $trip
    ): void {
        if (
            $trip->shuttleRoute
            && $trip->shuttleRoute->status === 'Active'
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'trip_schedule_id' => 'This trip uses an inactive route. Reactivate the route or move the trip to an active route before assigning a driver and bus.',
        ]);
    }

    private function assertDriverAuthorizedForBus(string $driverId, int $busId): void
    {
        $authorized = Driver::query()
            ->where('driver_id', $driverId)
            ->whereHas('authorizedBuses', fn ($query) => $query->whereKey($busId))
            ->exists();

        if (! $authorized) {
            throw ValidationException::withMessages([
                'driver_attendance_id' => 'This driver is not authorized for the selected bus. Update Authorized Buses in the Driver Master List.',
            ]);
        }
    }

    private function lockEligibleBus(int $busId): Bus
    {
        $bus = Bus::query()
            ->whereKey($busId)
            ->where('status', 'Active')
            ->where('fleet_purpose', 'Regular')
            ->whereNotIn('bus_no', JobOrder::query()->where('status', '!=', 'Completed')->select('bus_no'))
            ->lockForUpdate()
            ->first();

        if (! $bus) {
            throw ValidationException::withMessages([
                'bus_id' => 'The selected bus is inactive or unavailable.',
            ]);
        }

        if (JobOrder::query()
            ->where('bus_no', $bus->bus_no)
            ->where('status', '!=', 'Completed')
            ->exists()) {
            throw ValidationException::withMessages([
                'bus_id' => 'This bus has an unresolved Maintenance Job Order and cannot be assigned until Maintenance clears it.',
            ]);
        }

        return $bus;
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
            'slot_number' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);
    }

    private function ensureNoConflict(
        TripSchedule $trip,
        string $driverId,
        int $busId,
        ?int $ignoreAssignmentId = null
    ): void {
        $conflicts = $this->conflictingAssignmentsForTrip(
            $trip,
            $ignoreAssignmentId,
            true
        )->filter(fn (TripAssignment $assignment): bool => $assignment->driver_id === $driverId
            || (int) $assignment->bus_id === $busId
        );

        $messages = [];

        if ($conflicts->contains('driver_id', $driverId)) {
            $messages['driver_attendance_id'] =
                'The selected driver has an overlapping trip.';
        }

        if ($conflicts->contains('bus_id', $busId)) {
            $messages['bus_id'] =
                'The selected bus has an overlapping trip.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function conflictingAssignmentsForTrip(
        TripSchedule $trip,
        ?int $ignoreAssignmentId = null,
        bool $lock = false
    ): Collection {
        $tripStart = $trip->departureDateTime();
        $tripEnd = $trip->estimatedArrivalDateTime();

        $query = TripAssignment::query()
            ->with('tripSchedule')
            ->whereHas('tripSchedule', function ($query) use (
                $trip,
                $tripStart,
                $tripEnd
            ) {
                $query
                    ->whereKeyNot($trip->id)
                    ->whereNotIn('status', ['Cancelled', 'Completed'])
                    ->whereBetween('trip_date', [
                        $tripStart->copy()->subDay()->toDateString(),
                        $tripEnd->toDateString(),
                    ]);
            });

        if ($ignoreAssignmentId !== null) {
            $query->whereKeyNot($ignoreAssignmentId);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->get()
            ->filter(function (TripAssignment $assignment) use (
                $tripStart,
                $tripEnd
            ): bool {
                $otherTrip = $assignment->tripSchedule;

                return $otherTrip
                    && $tripStart->lt($otherTrip->estimatedArrivalDateTime())
                    && $tripEnd->gt($otherTrip->departureDateTime());
            })
            ->values();
    }

    private function redirectToIndex(Request $request): RedirectResponse
    {
        $context = validator(
            $request->only([
                'return_trip_date',
                'return_search',
                'return_status',
                'trip_page',
                'driver_page',
                'bus_page',
            ]),
            [
                'return_trip_date' => ['nullable', 'date_format:Y-m-d'],
                'return_search' => ['nullable', 'string', 'max:150'],
                'return_status' => [
                    'nullable',
                    Rule::in([
                        'all',
                        'Ready',
                        'Assigned',
                        'Unassigned',
                        'Dispatched',
                        'Completed',
                        'Cancelled',
                        'Missed',
                    ]),
                ],
                'trip_page' => ['nullable', 'integer', 'min:1'],
                'driver_page' => ['nullable', 'integer', 'min:1'],
                'bus_page' => ['nullable', 'integer', 'min:1'],
            ]
        )->validate();

        $query = array_filter([
            'trip_date' => $context['return_trip_date'] ?? null,
            'search' => $context['return_search'] ?? null,
            'status' => $context['return_status'] ?? null,
            'trip_page' => $context['trip_page'] ?? null,
            'driver_page' => $context['driver_page'] ?? null,
            'bus_page' => $context['bus_page'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');

        return redirect()->route('driver-bus-assignment', $query);
    }
}
