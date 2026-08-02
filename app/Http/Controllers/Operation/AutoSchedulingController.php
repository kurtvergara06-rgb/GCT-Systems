<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutoSchedulingController extends Controller
{
    /**
     * Display the Auto Scheduling page.
     */
    public function index(Request $request): View
    {
        $selectedDate = $request->string(
            'schedule_date',
            now()->toDateString()
        )->toString();

        $selectedShift = $request->string(
            'shift',
            'all'
        )->toString();

        $selectedRoute = $request->input(
            'shuttle_route_id',
            'all'
        );

        $tripQuery = TripSchedule::query()
            ->whereDate(
                'trip_date',
                $selectedDate
            )
            ->where(
                'assignment_status',
                'Unassigned'
            )
            ->where(
                'status',
                'Scheduled'
            );

        if ($selectedShift !== 'all') {
            $tripQuery->where(
                'shift',
                $selectedShift
            );
        }

        if ($selectedRoute !== 'all') {
            $tripQuery->where(
                'shuttle_route_id',
                $selectedRoute
            );
        }

        $tripsToSchedule = (clone $tripQuery)
            ->count();

        $availableDriverQuery =
            DriverAttendance::query()
                ->whereDate(
                    'attendance_date',
                    $selectedDate
                )
                ->whereIn(
                    'status',
                    [
                        'Present',
                        'Late',
                    ]
                );

        if ($selectedShift !== 'all') {
            $availableDriverQuery->where(
                'shift',
                $selectedShift
            );
        }

        $availableDrivers =
            $availableDriverQuery->count();

        $availableBuses = Bus::query()
            ->where(
                'status',
                'Active'
            )
            ->count();

        $unavailableDrivers =
            DriverAttendance::query()
                ->whereDate(
                    'attendance_date',
                    $selectedDate
                )
                ->whereIn(
                    'status',
                    [
                        'Absent',
                        'On Leave',
                        'On Duty',
                    ]
                )
                ->count();

        $unavailableBuses = Bus::query()
            ->whereIn(
                'status',
                [
                    'Inactive',
                    'Under Maintenance',
                ]
            )
            ->count();

        $potentialConflicts = max(
            0,
            $tripsToSchedule
            - min(
                $availableDrivers,
                $availableBuses
            )
        );

        $activeRoutes = ShuttleRoute::query()
            ->where(
                'status',
                'Active'
            )
            ->orderBy('route_code')
            ->get([
                'id',
                'route_code',
                'route_name',
            ]);

        return view(
            'Operation.Scheduling_And_Dispatch.auto-dispatch',
            compact(
                'selectedDate',
                'selectedShift',
                'selectedRoute',
                'tripsToSchedule',
                'availableDrivers',
                'availableBuses',
                'unavailableDrivers',
                'unavailableBuses',
                'potentialConflicts',
                'activeRoutes'
            )
        );
    }


    /**
     * Generate temporary recommendations.
     *
     * This method does not save assignments.
     */
    public function generate(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'schedule_date' => [
                'required',
                'date',
            ],

            'shift' => [
                'nullable',
                'in:all,Morning,Afternoon,Night',
            ],

            'shuttle_route_id' => [
                'nullable',
            ],
        ]);

        $scheduleDate =
            $validated['schedule_date'];

        $selectedShift =
            $validated['shift'] ?? 'all';

        $selectedRoute =
            $validated['shuttle_route_id']
            ?? 'all';

        $trips = TripSchedule::query()
            ->with('shuttleRoute')
            ->whereDate(
                'trip_date',
                $scheduleDate
            )
            ->where(
                'assignment_status',
                'Unassigned'
            )
            ->where(
                'status',
                'Scheduled'
            )
            ->when(
                $selectedShift !== 'all',
                function ($query) use (
                    $selectedShift
                ) {
                    $query->where(
                        'shift',
                        $selectedShift
                    );
                }
            )
            ->when(
                $selectedRoute !== 'all',
                function ($query) use (
                    $selectedRoute
                ) {
                    $query->where(
                        'shuttle_route_id',
                        $selectedRoute
                    );
                }
            )
            ->orderBy('departure_time')
            ->get();

        $drivers = DriverAttendance::query()
            ->whereDate(
                'attendance_date',
                $scheduleDate
            )
            ->whereIn(
                'status',
                [
                    'Present',
                    'Late',
                ]
            )
            ->when(
                $selectedShift !== 'all',
                function ($query) use (
                    $selectedShift
                ) {
                    $query->where(
                        'shift',
                        $selectedShift
                    );
                }
            )
            ->orderByRaw("
                CASE
                    WHEN status = 'Present' THEN 1
                    WHEN status = 'Late' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('driver_name')
            ->get();

        $buses = Bus::query()
            ->where(
                'status',
                'Active'
            )
            ->orderBy('bus_no')
            ->get();

        $existingAssignments =
            TripAssignment::query()
                ->with('tripSchedule')
                ->whereHas(
                    'tripSchedule',
                    function ($query) use (
                        $scheduleDate
                    ) {
                        $query
                            ->whereDate(
                                'trip_date',
                                $scheduleDate
                            )
                            ->whereNotIn(
                                'status',
                                [
                                    'Cancelled',
                                    'Completed',
                                ]
                            );
                    }
                )
                ->get();

        $driverWorkloads =
            $existingAssignments
                ->groupBy('driver_id')
                ->map(
                    fn (Collection $items): int =>
                        $items->count()
                );

        $busWorkloads =
            $existingAssignments
                ->groupBy('bus_id')
                ->map(
                    fn (Collection $items): int =>
                        $items->count()
                );

        $recommendations = collect();
        $conflicts = collect();

        foreach ($trips as $trip) {
            $driver = $this->findAvailableDriver(
                trip: $trip,
                drivers: $drivers,
                existingAssignments:
                    $existingAssignments,
                generatedRecommendations:
                    $recommendations,
                workloads:
                    $driverWorkloads
            );

            $bus = $this->findAvailableBus(
                trip: $trip,
                buses: $buses,
                existingAssignments:
                    $existingAssignments,
                generatedRecommendations:
                    $recommendations,
                workloads:
                    $busWorkloads
            );

            if (!$driver || !$bus) {
                $reasons = [];

                if (!$driver) {
                    $reasons[] =
                        'No eligible driver is available.';
                }

                if (!$bus) {
                    $reasons[] =
                        'No active bus is available.';
                }

                $conflicts->push([
                    'trip_schedule_id' =>
                        $trip->id,

                    'trip_code' =>
                        $trip->trip_code,

                    'departure_time' =>
                        $this->databaseTime(
                            $trip->departure_time
                        ),

                    'departure_display' =>
                        $this->displayTime(
                            $trip->departure_time
                        ),

                    'estimated_arrival_time' =>
                        $this->databaseTime(
                            $trip
                                ->estimated_arrival_time
                        ),

                    'arrival_display' =>
                        $this->displayTime(
                            $trip
                                ->estimated_arrival_time
                        ),

                    'route_code' =>
                        $trip->shuttleRoute
                            ?->route_code
                        ?? '—',

                    'route_name' =>
                        $trip->shuttleRoute
                            ?->route_name
                        ?? 'Unknown route',

                    'result' =>
                        'Conflict',

                    'reason' =>
                        implode(
                            ' ',
                            $reasons
                        ),
                ]);

                continue;
            }

            $recommendation = [
                'trip_schedule_id' =>
                    $trip->id,

                'trip_code' =>
                    $trip->trip_code,

                'trip_date' =>
                    $trip->trip_date
                        ?->format('Y-m-d'),

                'departure_time' =>
                    $this->databaseTime(
                        $trip->departure_time
                    ),

                'departure_display' =>
                    $this->displayTime(
                        $trip->departure_time
                    ),

                'estimated_arrival_time' =>
                    $this->databaseTime(
                        $trip
                            ->estimated_arrival_time
                    ),

                'arrival_display' =>
                    $this->displayTime(
                        $trip
                            ->estimated_arrival_time
                    ),

                'shift' =>
                    $trip->shift,

                'route_code' =>
                    $trip->shuttleRoute
                        ?->route_code
                    ?? '—',

                'route_name' =>
                    $trip->shuttleRoute
                        ?->route_name
                    ?? 'Unknown route',

                'driver_attendance_id' =>
                    $driver->id,

                'driver_id' =>
                    $driver->driver_id,

                'driver_name' =>
                    $driver->driver_name,

                'driver_status' =>
                    $driver->status,

                'bus_id' =>
                    $bus->id,

                'bus_no' =>
                    $bus->bus_no,

                'bus_model' =>
                    $bus->bus_model,

                'result' =>
                    'Ready',
            ];

            $recommendations->push(
                $recommendation
            );

            $driverWorkloads->put(
                $driver->driver_id,
                $driverWorkloads->get(
                    $driver->driver_id,
                    0
                ) + 1
            );

            $busWorkloads->put(
                $bus->id,
                $busWorkloads->get(
                    $bus->id,
                    0
                ) + 1
            );
        }

        return response()->json([
            'success' => true,

            'message' =>
                $trips->isEmpty()
                    ? 'No Scheduled and Unassigned trips were found.'
                    : 'Schedule recommendations generated successfully.',

            'summary' => [
                'trips' =>
                    $trips->count(),

                'drivers' =>
                    $drivers->count(),

                'buses' =>
                    $buses->count(),

                'ready' =>
                    $recommendations->count(),

                'conflicts' =>
                    $conflicts->count(),
            ],

            'recommendations' =>
                $recommendations->values(),

            'conflicts' =>
                $conflicts->values(),
        ]);
    }


    /**
     * Persist reviewed auto-scheduling recommendations.
     *
     * Only record identifiers are accepted from the browser. Every trip,
     * driver, bus, attendance status, and overlap is checked again while the
     * affected rows are locked so stale previews cannot create conflicts.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recommendations' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],
            'recommendations.*.trip_schedule_id' => [
                'required',
                'integer',
                'distinct',
                'exists:trip_schedules,id',
            ],
            'recommendations.*.driver_attendance_id' => [
                'required',
                'integer',
                'exists:driver_attendances,id',
            ],
            'recommendations.*.bus_id' => [
                'required',
                'integer',
                'exists:buses,id',
            ],
        ]);

        $savedCount = DB::transaction(function () use ($validated): int {
            $recommendations = collect(
                $validated['recommendations']
            );

            $trips = TripSchedule::query()
                ->whereIn(
                    'id',
                    $recommendations->pluck('trip_schedule_id')
                )
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $drivers = DriverAttendance::query()
                ->whereIn(
                    'id',
                    $recommendations->pluck('driver_attendance_id')
                )
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $buses = Bus::query()
                ->whereIn(
                    'id',
                    $recommendations->pluck('bus_id')
                )
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $tripDates = $trips
                ->pluck('trip_date')
                ->filter()
                ->map(fn ($date) => $date->format('Y-m-d'))
                ->unique();

            $existingAssignments = TripAssignment::query()
                ->with('tripSchedule')
                ->whereHas(
                    'tripSchedule',
                    fn ($query) => $query
                        ->whereIn('trip_date', $tripDates)
                        ->whereNotIn(
                            'status',
                            ['Cancelled', 'Completed']
                        )
                )
                ->lockForUpdate()
                ->get();

            foreach ($recommendations as $index => $recommendation) {
                $trip = $trips->get(
                    (int) $recommendation['trip_schedule_id']
                );

                $driver = $drivers->get(
                    (int) $recommendation['driver_attendance_id']
                );

                $bus = $buses->get(
                    (int) $recommendation['bus_id']
                );

                $field = "recommendations.{$index}";

                if (
                    !$trip
                    || $trip->assignment_status !== 'Unassigned'
                    || $trip->status !== 'Scheduled'
                ) {
                    throw ValidationException::withMessages([
                        $field => 'A selected trip is no longer available for assignment. Generate the schedule again.',
                    ]);
                }

                if (
                    !$driver
                    || !in_array($driver->status, ['Present', 'Late'], true)
                    || !$driver->attendance_date?->isSameDay($trip->trip_date)
                    || $driver->shift !== $trip->shift
                ) {
                    throw ValidationException::withMessages([
                        $field => 'A selected driver is no longer eligible for this trip. Generate the schedule again.',
                    ]);
                }

                if (!$bus || $bus->status !== 'Active') {
                    throw ValidationException::withMessages([
                        $field => 'A selected bus is no longer active. Generate the schedule again.',
                    ]);
                }

                if (
                    $this->driverHasConflict(
                        trip: $trip,
                        driverId: $driver->driver_id,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: collect()
                    )
                    || $this->busHasConflict(
                        trip: $trip,
                        busId: $bus->id,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: collect()
                    )
                ) {
                    throw ValidationException::withMessages([
                        $field => 'A selected driver or bus now has an overlapping trip. Generate the schedule again.',
                    ]);
                }

                $assignment = TripAssignment::create([
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

                $assignment->setRelation('tripSchedule', $trip);
                $existingAssignments->push($assignment);
            }

            return $recommendations->count();
        });

        return response()->json([
            'success' => true,
            'message' => "{$savedCount} schedule assignment(s) saved successfully.",
            'saved' => $savedCount,
            'redirect_url' => route(
                'driver-bus-assignment',
                [],
                false
            ),
        ]);
    }


    /**
     * Find the best available driver.
     */
    private function findAvailableDriver(
        TripSchedule $trip,
        Collection $drivers,
        Collection $existingAssignments,
        Collection $generatedRecommendations,
        Collection $workloads
    ): ?DriverAttendance {
        return $drivers
            ->filter(
                function (
                    DriverAttendance $driver
                ) use ($trip): bool {
                    return $driver->shift
                        === $trip->shift;
                }
            )
            ->sortBy(
                function (
                    DriverAttendance $driver
                ) use ($workloads): string {
                    $workload =
                        $workloads->get(
                            $driver->driver_id,
                            0
                        );

                    return sprintf(
                        '%08d-%s',
                        $workload,
                        strtolower(
                            $driver->driver_name
                        )
                    );
                }
            )
            ->first(
                function (
                    DriverAttendance $driver
                ) use (
                    $trip,
                    $existingAssignments,
                    $generatedRecommendations
                ): bool {
                    return !$this
                        ->driverHasConflict(
                            trip: $trip,
                            driverId:
                                $driver->driver_id,
                            existingAssignments:
                                $existingAssignments,
                            generatedRecommendations:
                                $generatedRecommendations
                        );
                }
            );
    }


    /**
     * Find the best available bus.
     */
    private function findAvailableBus(
        TripSchedule $trip,
        Collection $buses,
        Collection $existingAssignments,
        Collection $generatedRecommendations,
        Collection $workloads
    ): ?Bus {
        return $buses
            ->sortBy(
                function (
                    Bus $bus
                ) use ($workloads): string {
                    $workload =
                        $workloads->get(
                            $bus->id,
                            0
                        );

                    return sprintf(
                        '%08d-%s',
                        $workload,
                        strtolower(
                            $bus->bus_no
                        )
                    );
                }
            )
            ->first(
                function (
                    Bus $bus
                ) use (
                    $trip,
                    $existingAssignments,
                    $generatedRecommendations
                ): bool {
                    return !$this
                        ->busHasConflict(
                            trip: $trip,
                            busId: $bus->id,
                            existingAssignments:
                                $existingAssignments,
                            generatedRecommendations:
                                $generatedRecommendations
                        );
                }
            );
    }


    /**
     * Check if a driver has an overlapping trip.
     */
    private function driverHasConflict(
        TripSchedule $trip,
        string $driverId,
        Collection $existingAssignments,
        Collection $generatedRecommendations
    ): bool {
        $existingConflict =
            $existingAssignments->contains(
                function (
                    TripAssignment $assignment
                ) use (
                    $trip,
                    $driverId
                ): bool {
                    if (
                        $assignment->driver_id
                        !== $driverId
                    ) {
                        return false;
                    }

                    $assignedTrip =
                        $assignment->tripSchedule;

                    if (!$assignedTrip) {
                        return false;
                    }

                    return $this->timesOverlap(
                        $trip->departure_time,
                        $trip
                            ->estimated_arrival_time,
                        $assignedTrip
                            ->departure_time,
                        $assignedTrip
                            ->estimated_arrival_time
                    );
                }
            );

        if ($existingConflict) {
            return true;
        }

        return $generatedRecommendations
            ->contains(
                function (
                    array $recommendation
                ) use (
                    $trip,
                    $driverId
                ): bool {
                    if (
                        $recommendation[
                            'driver_id'
                        ] !== $driverId
                    ) {
                        return false;
                    }

                    return $this->timesOverlap(
                        $trip->departure_time,
                        $trip
                            ->estimated_arrival_time,
                        $recommendation[
                            'departure_time'
                        ],
                        $recommendation[
                            'estimated_arrival_time'
                        ]
                    );
                }
            );
    }


    /**
     * Check if a bus has an overlapping trip.
     */
    private function busHasConflict(
        TripSchedule $trip,
        int $busId,
        Collection $existingAssignments,
        Collection $generatedRecommendations
    ): bool {
        $existingConflict =
            $existingAssignments->contains(
                function (
                    TripAssignment $assignment
                ) use (
                    $trip,
                    $busId
                ): bool {
                    if (
                        (int) $assignment->bus_id
                        !== $busId
                    ) {
                        return false;
                    }

                    $assignedTrip =
                        $assignment->tripSchedule;

                    if (!$assignedTrip) {
                        return false;
                    }

                    return $this->timesOverlap(
                        $trip->departure_time,
                        $trip
                            ->estimated_arrival_time,
                        $assignedTrip
                            ->departure_time,
                        $assignedTrip
                            ->estimated_arrival_time
                    );
                }
            );

        if ($existingConflict) {
            return true;
        }

        return $generatedRecommendations
            ->contains(
                function (
                    array $recommendation
                ) use (
                    $trip,
                    $busId
                ): bool {
                    if (
                        (int) $recommendation[
                            'bus_id'
                        ] !== $busId
                    ) {
                        return false;
                    }

                    return $this->timesOverlap(
                        $trip->departure_time,
                        $trip
                            ->estimated_arrival_time,
                        $recommendation[
                            'departure_time'
                        ],
                        $recommendation[
                            'estimated_arrival_time'
                        ]
                    );
                }
            );
    }


    /**
     * Determine if two time ranges overlap.
     */
    private function timesOverlap(
        mixed $firstStart,
        mixed $firstEnd,
        mixed $secondStart,
        mixed $secondEnd
    ): bool {
        $firstStartTimestamp =
            strtotime((string) $firstStart);

        $firstEndTimestamp =
            strtotime((string) $firstEnd);

        $secondStartTimestamp =
            strtotime((string) $secondStart);

        $secondEndTimestamp =
            strtotime((string) $secondEnd);

        return (
            $firstStartTimestamp
            < $secondEndTimestamp
            &&
            $firstEndTimestamp
            > $secondStartTimestamp
        );
    }


    /**
     * Return a database-compatible time.
     */
    private function databaseTime(
        mixed $time
    ): string {
        if (!$time) {
            return '';
        }

        return date(
            'H:i:s',
            strtotime((string) $time)
        );
    }


    /**
     * Return a user-friendly time.
     */
    private function displayTime(
        mixed $time
    ): string {
        if (!$time) {
            return '—';
        }

        return date(
            'g:i A',
            strtotime((string) $time)
        );
    }
}
