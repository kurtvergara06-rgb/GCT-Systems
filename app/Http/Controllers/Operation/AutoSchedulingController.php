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
use App\Services\OperationAiService;

class AutoSchedulingController extends Controller
{
    public function __construct(
        private readonly OperationAiService $operationAi
    ) {
    }


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

                $aiPayload = $this->buildAiPayload(
                    trip: $trip,
                    selectedDriver: $driver,
                    selectedBus: $bus,
                    drivers: $drivers,
                    buses: $buses,
                    existingAssignments:
                        $existingAssignments,
                    generatedRecommendations:
                        $recommendations,
                    driverWorkloads:
                        $driverWorkloads,
                    busWorkloads:
                        $busWorkloads
                );

                $aiResponse =
                    $this->operationAi->recommend(
                        $aiPayload
                    );

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

                    'ai' => [
                        'available' =>
                            $aiResponse !== null,

                        'status' =>
                            data_get(
                                $aiResponse,
                                'status'
                            ),

                        'score' =>
                            data_get(
                                $aiResponse,
                                'analysis.recommendation_score'
                            ),

                        'analysis_status' =>
                            data_get(
                                $aiResponse,
                                'analysis.status'
                            ),

                        'driver_explanation' =>
                            data_get(
                                $aiResponse,
                                'analysis.driver_explanation'
                            ),

                        'bus_explanation' =>
                            data_get(
                                $aiResponse,
                                'analysis.bus_explanation'
                            ),

                        'conflict_explanation' =>
                            data_get(
                                $aiResponse,
                                'analysis.conflict_explanation'
                            ),

                        'warnings' =>
                            data_get(
                                $aiResponse,
                                'analysis.warnings',
                                []
                            ),

                        'alternative_drivers' =>
                            data_get(
                                $aiResponse,
                                'analysis.alternative_drivers',
                                []
                            ),

                        'alternative_buses' =>
                            data_get(
                                $aiResponse,
                                'analysis.alternative_buses',
                                []
                            ),

                        'conflict' =>
                            data_get(
                                $aiResponse,
                                'conflict'
                            ),
                    ],
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
     * Build the request payload sent to the Python
     * Operation AI service.
     */
    private function buildAiPayload(
        TripSchedule $trip,
        ?DriverAttendance $selectedDriver,
        ?Bus $selectedBus,
        Collection $drivers,
        Collection $buses,
        Collection $existingAssignments,
        Collection $generatedRecommendations,
        Collection $driverWorkloads,
        Collection $busWorkloads
    ): array {
        return [
            'trip' => [
                'id' =>
                    (int) $trip->id,

                'trip_code' =>
                    (string) $trip->trip_code,

                'trip_date' =>
                    $trip->trip_date
                        ?->format('Y-m-d')
                    ?? '',

                'shift' =>
                    $trip->shift,

                'route_code' =>
                    $trip->shuttleRoute
                        ?->route_code,

                'route_name' =>
                    $trip->shuttleRoute
                        ?->route_name,

                'departure_time' =>
                    $this->databaseTime(
                        $trip->departure_time
                    ),

                'arrival_time' =>
                    $this->databaseTime(
                        $trip->estimated_arrival_time
                    ),
            ],

            'selected_driver' =>
                $selectedDriver
                    ? $this->formatDriverForAi(
                        driver: $selectedDriver,
                        trip: $trip,
                        existingAssignments:
                            $existingAssignments,
                        generatedRecommendations:
                            $generatedRecommendations,
                        workloads:
                            $driverWorkloads
                    )
                    : null,

            'selected_bus' =>
                $selectedBus
                    ? $this->formatBusForAi(
                        bus: $selectedBus,
                        trip: $trip,
                        existingAssignments:
                            $existingAssignments,
                        generatedRecommendations:
                            $generatedRecommendations,
                        workloads:
                            $busWorkloads
                    )
                    : null,

            'eligible_drivers' =>
                $drivers
                    ->map(
                        fn (
                            DriverAttendance $driver
                        ): array =>
                            $this->formatDriverForAi(
                                driver: $driver,
                                trip: $trip,
                                existingAssignments:
                                    $existingAssignments,
                                generatedRecommendations:
                                    $generatedRecommendations,
                                workloads:
                                    $driverWorkloads
                            )
                    )
                    ->values()
                    ->all(),

            'eligible_buses' =>
                $buses
                    ->map(
                        fn (Bus $bus): array =>
                            $this->formatBusForAi(
                                bus: $bus,
                                trip: $trip,
                                existingAssignments:
                                    $existingAssignments,
                                generatedRecommendations:
                                    $generatedRecommendations,
                                workloads:
                                    $busWorkloads
                            )
                    )
                    ->values()
                    ->all(),
        ];
    }


    /**
     * Convert one driver attendance record into
     * the format expected by Python.
     */
    private function formatDriverForAi(
        DriverAttendance $driver,
        TripSchedule $trip,
        Collection $existingAssignments,
        Collection $generatedRecommendations,
        Collection $workloads
    ): array {
        $hasConflict =
            $this->driverHasConflict(
                trip: $trip,
                driverId: $driver->driver_id,
                existingAssignments:
                    $existingAssignments,
                generatedRecommendations:
                    $generatedRecommendations
            );

        return [
            'id' =>
                (int) $driver->id,

            'name' =>
                (string) $driver->driver_name,

            'status' =>
                (string) $driver->status,

            'shift' =>
                $driver->shift,

            'assigned_trips' =>
                (int) $workloads->get(
                    $driver->driver_id,
                    0
                ),

            'assigned_minutes' =>
                0,

            'has_conflict' =>
                $hasConflict,

            'conflict_end_time' =>
                $hasConflict
                    ? $this->driverConflictEndTime(
                        trip: $trip,
                        driverId: $driver->driver_id,
                        existingAssignments:
                            $existingAssignments,
                        generatedRecommendations:
                            $generatedRecommendations
                    )
                    : null,
        ];
    }


    /**
     * Convert one bus record into the format
     * expected by Python.
     */
    private function formatBusForAi(
        Bus $bus,
        TripSchedule $trip,
        Collection $existingAssignments,
        Collection $generatedRecommendations,
        Collection $workloads
    ): array {
        $hasConflict =
            $this->busHasConflict(
                trip: $trip,
                busId: (int) $bus->id,
                existingAssignments:
                    $existingAssignments,
                generatedRecommendations:
                    $generatedRecommendations
            );

        return [
            'id' =>
                (int) $bus->id,

            'bus_no' =>
                (string) $bus->bus_no,

            'status' =>
                (string) $bus->status,

            'mileage' =>
                $bus->latest_gps_km !== null
                    ? (float) $bus->latest_gps_km
                    : null,

            'next_pms_mileage' =>
                $bus->next_pms_km !== null
                    ? (float) $bus->next_pms_km
                    : null,

            'assigned_trips' =>
                (int) $workloads->get(
                    $bus->id,
                    0
                ),

            'assigned_minutes' =>
                0,

            'has_conflict' =>
                $hasConflict,

            'conflict_end_time' =>
                $hasConflict
                    ? $this->busConflictEndTime(
                        trip: $trip,
                        busId: (int) $bus->id,
                        existingAssignments:
                            $existingAssignments,
                        generatedRecommendations:
                            $generatedRecommendations
                    )
                    : null,
        ];
    }


    /**
     * Return the time when a conflicting driver's
     * overlapping assignment ends.
     */
    private function driverConflictEndTime(
        TripSchedule $trip,
        string $driverId,
        Collection $existingAssignments,
        Collection $generatedRecommendations
    ): ?string {
        $endTimes = collect();

        foreach ($existingAssignments as $assignment) {
            if (
                $assignment->driver_id
                !== $driverId
            ) {
                continue;
            }

            $assignedTrip =
                $assignment->tripSchedule;

            if (!$assignedTrip) {
                continue;
            }

            if (
                !$this->timesOverlap(
                    $trip->departure_time,
                    $trip->estimated_arrival_time,
                    $assignedTrip->departure_time,
                    $assignedTrip
                        ->estimated_arrival_time
                )
            ) {
                continue;
            }

            $endTimes->push(
                $this->databaseTime(
                    $assignedTrip
                        ->estimated_arrival_time
                )
            );
        }

        foreach (
            $generatedRecommendations
            as $recommendation
        ) {
            if (
                ($recommendation['driver_id'] ?? null)
                !== $driverId
            ) {
                continue;
            }

            if (
                !$this->timesOverlap(
                    $trip->departure_time,
                    $trip->estimated_arrival_time,
                    $recommendation[
                        'departure_time'
                    ] ?? null,
                    $recommendation[
                        'estimated_arrival_time'
                    ] ?? null
                )
            ) {
                continue;
            }

            $endTimes->push(
                $this->databaseTime(
                    $recommendation[
                        'estimated_arrival_time'
                    ] ?? null
                )
            );
        }

        return $this->earliestTime(
            $endTimes
        );
    }


    /**
     * Return the time when a conflicting bus's
     * overlapping assignment ends.
     */
    private function busConflictEndTime(
        TripSchedule $trip,
        int $busId,
        Collection $existingAssignments,
        Collection $generatedRecommendations
    ): ?string {
        $endTimes = collect();

        foreach ($existingAssignments as $assignment) {
            if (
                (int) $assignment->bus_id
                !== $busId
            ) {
                continue;
            }

            $assignedTrip =
                $assignment->tripSchedule;

            if (!$assignedTrip) {
                continue;
            }

            if (
                !$this->timesOverlap(
                    $trip->departure_time,
                    $trip->estimated_arrival_time,
                    $assignedTrip->departure_time,
                    $assignedTrip
                        ->estimated_arrival_time
                )
            ) {
                continue;
            }

            $endTimes->push(
                $this->databaseTime(
                    $assignedTrip
                        ->estimated_arrival_time
                )
            );
        }

        foreach (
            $generatedRecommendations
            as $recommendation
        ) {
            if (
                (int) (
                    $recommendation['bus_id']
                    ?? 0
                ) !== $busId
            ) {
                continue;
            }

            if (
                !$this->timesOverlap(
                    $trip->departure_time,
                    $trip->estimated_arrival_time,
                    $recommendation[
                        'departure_time'
                    ] ?? null,
                    $recommendation[
                        'estimated_arrival_time'
                    ] ?? null
                )
            ) {
                continue;
            }

            $endTimes->push(
                $this->databaseTime(
                    $recommendation[
                        'estimated_arrival_time'
                    ] ?? null
                )
            );
        }

        return $this->earliestTime(
            $endTimes
        );
    }


    /**
     * Return the earliest valid time in a collection.
     */
    private function earliestTime(
        Collection $times
    ): ?string {
        return $times
            ->filter(
                fn ($time): bool =>
                    is_string($time)
                    && $time !== ''
            )
            ->sortBy(
                fn (string $time): int =>
                    strtotime($time)
                    ?: PHP_INT_MAX
            )
            ->first();
    }

    /**
 * Apply an AI-assisted resolution after final Laravel validation.
 */
public function resolve(Request $request): JsonResponse
{
    $validated = $request->validate([
        'trip_schedule_id' => [
            'required',
            'integer',
            'exists:trip_schedules,id',
        ],

        'proposed_departure_time' => [
            'required',
            'date_format:H:i:s',
        ],
    ]);

    $result = DB::transaction(
        function () use ($validated): array {
            $trip = TripSchedule::query()
                ->with('shuttleRoute')
                ->lockForUpdate()
                ->findOrFail(
                    (int) $validated[
                        'trip_schedule_id'
                    ]
                );

            if (
                $trip->status !== 'Scheduled'
                || $trip->assignment_status
                    !== 'Unassigned'
            ) {
                throw ValidationException::withMessages([
                    'trip_schedule_id' =>
                        'This trip is no longer available for resolution. Generate the schedule again.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Preserve the original trip duration
            |--------------------------------------------------------------------------
            */

            $originalStart = strtotime(
                $trip->trip_date->format('Y-m-d')
                . ' '
                . $this->databaseTime(
                    $trip->departure_time
                )
            );

            $originalEnd = strtotime(
                $trip->trip_date->format('Y-m-d')
                . ' '
                . $this->databaseTime(
                    $trip->estimated_arrival_time
                )
            );

            if (
                $originalStart === false
                || $originalEnd === false
            ) {
                throw ValidationException::withMessages([
                    'trip_schedule_id' =>
                        'The original trip time is invalid.',
                ]);
            }

            /*
             * Handle trips ending after midnight.
             */
            if ($originalEnd <= $originalStart) {
                $originalEnd += 86400;
            }

            $durationSeconds =
                $originalEnd - $originalStart;

            $proposedStart = strtotime(
                $trip->trip_date->format('Y-m-d')
                . ' '
                . $validated[
                    'proposed_departure_time'
                ]
            );

            if ($proposedStart === false) {
                throw ValidationException::withMessages([
                    'proposed_departure_time' =>
                        'The proposed departure time is invalid.',
                ]);
            }

            $proposedEnd =
                $proposedStart + $durationSeconds;

            /*
            |--------------------------------------------------------------------------
            | Temporarily apply the proposed time
            |--------------------------------------------------------------------------
            |
            | The values are used for conflict checking but are not saved until
            | Laravel finds a valid driver and bus.
            |
            */

            $trip->departure_time =
                date('H:i:s', $proposedStart);

            $trip->estimated_arrival_time =
                date('H:i:s', $proposedEnd);

            $scheduleDate =
                $trip->trip_date->format('Y-m-d');

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
                ->where(
                    'shift',
                    $trip->shift
                )
                ->orderByRaw("
                    CASE
                        WHEN status = 'Present' THEN 1
                        WHEN status = 'Late' THEN 2
                        ELSE 3
                    END
                ")
                ->orderBy('driver_name')
                ->lockForUpdate()
                ->get();

            $buses = Bus::query()
                ->where(
                    'status',
                    'Active'
                )
                ->orderBy('bus_no')
                ->lockForUpdate()
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
                    ->lockForUpdate()
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

            $generatedRecommendations =
                collect();

            $driver =
                $this->findAvailableDriver(
                    trip: $trip,
                    drivers: $drivers,
                    existingAssignments:
                        $existingAssignments,
                    generatedRecommendations:
                        $generatedRecommendations,
                    workloads:
                        $driverWorkloads
                );

            $bus =
                $this->findAvailableBus(
                    trip: $trip,
                    buses: $buses,
                    existingAssignments:
                        $existingAssignments,
                    generatedRecommendations:
                        $generatedRecommendations,
                    workloads:
                        $busWorkloads
                );

            if (!$driver || !$bus) {
                $messages = [];

                if (!$driver) {
                    $messages[] =
                        'No eligible driver is available at the proposed time.';
                }

                if (!$bus) {
                    $messages[] =
                        'No active conflict-free bus is available at the proposed time.';
                }

                throw ValidationException::withMessages([
                    'resolution' =>
                        implode(' ', $messages),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Final conflict check
            |--------------------------------------------------------------------------
            */

            if (
                $this->driverHasConflict(
                    trip: $trip,
                    driverId:
                        $driver->driver_id,
                    existingAssignments:
                        $existingAssignments,
                    generatedRecommendations:
                        collect()
                )
                || $this->busHasConflict(
                    trip: $trip,
                    busId:
                        (int) $bus->id,
                    existingAssignments:
                        $existingAssignments,
                    generatedRecommendations:
                        collect()
                )
            ) {
                throw ValidationException::withMessages([
                    'resolution' =>
                        'The proposed resolution now conflicts with another assignment. Generate the schedule again.',
                ]);
            }

            $assignment =
                TripAssignment::create([
                    'trip_schedule_id' =>
                        $trip->id,

                    'driver_attendance_id' =>
                        $driver->id,

                    'driver_id' =>
                        $driver->driver_id,

                    'driver_name' =>
                        $driver->driver_name,

                    'bus_id' =>
                        $bus->id,

                    'assigned_by' =>
                        auth()->id(),
                ]);

            $trip->update([
                'departure_time' =>
                    date(
                        'H:i:s',
                        $proposedStart
                    ),

                'estimated_arrival_time' =>
                    date(
                        'H:i:s',
                        $proposedEnd
                    ),

                'assignment_status' =>
                    'Assigned',

                'status' =>
                    'Ready',
            ]);

            return [
                'assignment_id' =>
                    $assignment->id,

                'trip_code' =>
                    $trip->trip_code,

                'driver_name' =>
                    $driver->driver_name,

                'bus_no' =>
                    $bus->bus_no,

                'departure_time' =>
                    date(
                        'H:i:s',
                        $proposedStart
                    ),

                'departure_display' =>
                    date(
                        'g:i A',
                        $proposedStart
                    ),

                'arrival_time' =>
                    date(
                        'H:i:s',
                        $proposedEnd
                    ),

                'arrival_display' =>
                    date(
                        'g:i A',
                        $proposedEnd
                    ),
            ];
        }
    );

    return response()->json([
        'success' => true,

        'message' =>
            "Trip {$result['trip_code']} was resolved successfully.",

        'resolution' =>
            $result,

        'redirect_url' =>
            route(
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