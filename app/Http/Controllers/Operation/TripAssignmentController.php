<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
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

    public function index(Request $request): View
    {
        $query = TripSchedule::query()
            ->with([
                'shuttleRoute',
                'assignment.bus',
                'assignment.driverAttendance',
                'assignment' => fn ($assignmentQuery) => $assignmentQuery
                    ->withCount(['dailyDriverReports', 'incidents']),
            ])
            ->withCount([
                'dailyDriverReports',
                'incidents',
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
        $query->whereDate('trip_date', $selectedTripDate);

        if (
            $request->filled('status')
            && $request->input('status') !== 'all'
        ) {
            $status = $request->input('status');

            if (in_array($status, ['Assigned', 'Unassigned'], true)) {
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

        $trips = $query
            ->orderByDesc('trip_date')
            ->orderBy('departure_time')
            ->paginate(10, ['*'], 'trip_page')
            ->withQueryString();

        $scheduledTripsForDate = TripSchedule::query()
            ->whereDate('trip_date', $selectedTripDate)
            ->whereIn('status', ['Scheduled', 'Ready'])
            ->count();

        $pendingAssignments = TripSchedule::query()
            ->notDeparted()
            ->whereDate('trip_date', $selectedTripDate)
            ->where('assignment_status', 'Unassigned')
            ->whereDoesntHave('assignment')
            ->where('status', 'Scheduled')
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
            ->whereDoesntHave('tripAssignments', function ($assignmentQuery) use ($selectedTripDate): void {
                $assignmentQuery->whereHas('tripSchedule', function ($tripQuery) use ($selectedTripDate): void {
                    $tripQuery
                        ->whereNotIn('status', ['Cancelled', 'Completed'])
                        ->whereDate('trip_date', '<=', $selectedTripDate)
                        ->whereRaw(
                            'COALESCE(estimated_arrival_date, trip_date) >= ?',
                            [$selectedTripDate]
                        );
                });
            })
            ->orderBy('driver_name')
            ->paginate(10, ['*'], 'driver_page')
            ->withQueryString();

        $availableBuses = Bus::query()
            ->where('status', 'Active')
            ->whereDoesntHave('tripAssignments', function ($assignmentQuery) use ($selectedTripDate): void {
                $assignmentQuery->whereHas('tripSchedule', function ($tripQuery) use ($selectedTripDate): void {
                    $tripQuery
                        ->whereNotIn('status', ['Cancelled', 'Completed'])
                        ->whereDate('trip_date', '<=', $selectedTripDate)
                        ->whereRaw(
                            'COALESCE(estimated_arrival_date, trip_date) >= ?',
                            [$selectedTripDate]
                        );
                });
            })
            ->orderBy('bus_no')
            ->paginate(10, ['*'], 'bus_page')
            ->withQueryString();

        $unassignedTrips = TripSchedule::query()
            ->with('shuttleRoute')
            ->notDeparted()
            ->whereDate('trip_date', $selectedTripDate)
            ->where('assignment_status', 'Unassigned')
            ->whereDoesntHave('assignment')
            ->where('status', 'Scheduled')
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

    public function availability(TripSchedule $tripSchedule): JsonResponse
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

        $conflictingAssignments = $this->conflictingAssignmentsForTrip(
            $tripSchedule
        );

        $busyDriverIds = $conflictingAssignments
            ->pluck('driver_id')
            ->filter()
            ->unique()
            ->all();

        $busyBusIds = $conflictingAssignments
            ->pluck('bus_id')
            ->filter()
            ->unique()
            ->all();

        $drivers = DriverAttendance::query()
            ->whereDate('attendance_date', $tripSchedule->trip_date)
            ->where('shift', $tripSchedule->shift)
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
                    $trip->assignment_status !== 'Unassigned'
                    || $trip->status !== 'Scheduled'
                    || $trip->assignment()->exists()
                ) {
                    throw ValidationException::withMessages([
                        'trip_schedule_id' => 'This trip is no longer available for assignment.',
                    ]);
                }

                $this->assertAssignmentMutable($trip);
                $this->assertRouteIsActive($trip);

                $driver = $this->lockEligibleDriver(
                    $validated['driver_attendance_id'],
                    $trip
                );

                $bus = $this->lockEligibleBus($validated['bus_id']);

                $this->ensureNoConflict(
                    $trip,
                    $driver->driver_id,
                    $bus->id
                );

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

                return $trip;
            }
        );

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            $trip->id,
            "{$trip->trip_code} is ready after driver and bus assignment."
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

                return $trip;
            }
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

                $lockedAssignment->delete();

                $trip->update([
                    'assignment_status' => 'Unassigned',
                    'status' => 'Scheduled',
                ]);

                return $trip;
            }
        );

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            $trip->id,
            "{$trip->trip_code} returned to Unassigned after its assignment was removed."
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
            ->where('shift', $trip->shift)
            ->whereIn('status', ['Present', 'Late'])
            ->lockForUpdate()
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'driver_attendance_id' => 'Select a present or late driver attendance record for this trip date and shift.',
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

    private function lockEligibleBus(int $busId): Bus
    {
        $bus = Bus::query()
            ->whereKey($busId)
            ->where('status', 'Active')
            ->lockForUpdate()
            ->first();

        if (! $bus) {
            throw ValidationException::withMessages([
                'bus_id' => 'The selected bus is inactive or unavailable.',
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
