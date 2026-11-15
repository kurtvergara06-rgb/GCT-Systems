<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\AutoScheduleSlotDecision;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Services\OperationAiService;
use App\Support\ClientTripTimes;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutoSchedulingController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function __construct(
        private readonly OperationAiService $operationAi
    ) {}

    /**
     * Display the Auto Scheduling page.
     */
    public function index(Request $request): View
    {
        $selectedDate = $request->string(
            'schedule_date',
            now(config('app.business_timezone', 'Asia/Manila'))->toDateString()
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
            ->notDeparted()
            ->whereDate(
                'trip_date',
                $selectedDate
            )
            ->where(
                'assignment_status',
                '!=',
                'Assigned'
            )
            ->whereIn(
                'status',
                ['Scheduled', 'Ready']
            )
            ->whereHas(
                'shuttleRoute',
                fn ($query) => $query->where(
                    'status',
                    'Active'
                )
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

        $availableDriverQuery = Driver::query()
            ->where('employment_status', 'Active');

        $availableDrivers =
            $availableDriverQuery->count();

        // The initial summary counts standard dispatch buses only.
        // Reserve vehicles are conditional Head-approved alternatives, not
        // normal scheduling capacity.
        $availableBuses = Bus::query()
            ->where('fleet_purpose', 'Regular')
            ->where(
                'status',
                'Active'
            )
            ->whereNotIn('bus_no', JobOrder::query()
                ->where('status', '!=', 'Completed')
                ->select('bus_no'))
            ->count();

        $unavailableDrivers =
            Driver::query()
                ->where('employment_status', '!=', 'Active')
                ->count();

        $unavailableBuses = Bus::query()
            ->where('fleet_purpose', 'Regular')
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
     * Proxy the Python model-readiness report for the Auto Scheduling page.
     */
    public function trainingStatus(): JsonResponse
    {
        $status = $this->operationAi->trainingStatus();

        if ($status === null) {
            return response()->json([
                'success' => false,
                'message' => 'Operation AI readiness is unavailable.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'bus_model_ready' => ($status['bus_model_ready'] ?? false) === true,
            'driver_model_ready' => ($status['driver_model_ready'] ?? false) === true,
            'bus_source' => (string) ($status['bus_source'] ?? 'rule_fallback'),
            'driver_source' => (string) ($status['driver_source'] ?? 'rule_fallback'),
            'bus_sample_count' => max(0, (int) ($status['bus_sample_count'] ?? 0)),
            'driver_sample_count' => max(0, (int) ($status['driver_sample_count'] ?? 0)),
            'bus_reason' => (string) ($status['bus_reason'] ?? ''),
            'driver_reason' => (string) ($status['driver_reason'] ?? ''),
        ]);
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
            ->with(['shuttleRoute', 'assignments'])
            ->withCount('assignments')
            ->notDeparted()
            ->whereDate(
                'trip_date',
                $scheduleDate
            )
            ->where(
                'assignment_status',
                '!=',
                'Assigned'
            )
            ->whereIn(
                'status',
                ['Scheduled', 'Ready']
            )
            ->whereHas(
                'shuttleRoute',
                fn ($query) => $query->where(
                    'status',
                    'Active'
                )
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

        $drivers = $this->assumedPresentDrivers($scheduleDate);

        $buses = Bus::query()
            ->whereIn('fleet_purpose', ['Regular', 'Reserve'])
            ->where(
                'status',
                'Active'
            )
            ->whereNotIn('bus_no', JobOrder::query()
                ->where('status', '!=', 'Completed')
                ->select('bus_no'))
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
                            ->whereIn(
                                'trip_date',
                                [
                                    date(
                                        'Y-m-d',
                                        strtotime($scheduleDate.' -1 day')
                                    ),
                                    $scheduleDate,
                                    date(
                                        'Y-m-d',
                                        strtotime($scheduleDate.' +1 day')
                                    ),
                                ]
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

        $workloadAssignments =
            $existingAssignments
                ->filter(
                    fn (TripAssignment $assignment): bool => $assignment->tripSchedule?->trip_date?->format('Y-m-d')
                            === $scheduleDate
                );

        $driverWorkloads =
            $workloadAssignments
                ->groupBy('driver_id')
                ->map(
                    fn (Collection $items): int => $items->count()
                );

        $busWorkloads =
            $workloadAssignments
                ->groupBy('bus_id')
                ->map(
                    fn (Collection $items): int => $items->count()
                );

        $recommendations = collect();
        $conflicts = collect();

        // ML feature histories are per-driver / per-bus aggregates that are
        // independent of the current trip, so compute them once up-front and
        // reuse them for every trip being auto-scheduled.
        $aiHistories = $this->buildAiHistoryAggregates(
            drivers: $drivers,
            buses: $buses
        );

        foreach ($trips as $trip) {
            $requiredBuses = max(1, (int) ($trip->required_buses ?? 1));
            $assignedSlots = $trip->assignments
                ->pluck('slot_number')
                ->filter(fn ($slot): bool => $slot !== null)
                ->map(fn ($slot): int => (int) $slot)
                ->unique();
            $legacyAssignmentCount = $trip->assignments->whereNull('slot_number')->count();
            $openSlots = collect(range(1, $requiredBuses))
                ->reject(fn (int $slot): bool => $assignedSlots->contains($slot));
            $legacySlots = $openSlots->take($legacyAssignmentCount);
            $occupiedSlots = $assignedSlots->merge($legacySlots)->unique();
            $slotNumbers = collect(range(1, $requiredBuses))
                ->reject(fn (int $slot): bool => $occupiedSlots->contains($slot))
                ->values();
            $alreadyAssignedCount = $trip->assignments->count();

            if ($slotNumbers->isEmpty()) {
                continue;
            }

            foreach ($slotNumbers as $slotNumber) {
                [$driver, $bus] = $this->findAvailableAuthorizedPair(
                    $trip, $drivers, $buses, $existingAssignments,
                    $recommendations, $driverWorkloads, $busWorkloads
                );

                $slotLabel = $requiredBuses > 1 ? "Bus {$slotNumber}/{$requiredBuses}" : null;

                if ($bus && $bus->fleet_purpose === 'Reserve' && $driver) {
                    // Reserve use is a reviewable proposal, never an automatically
                    // Ready assignment. Operations selects it from the AI report.
                    $conflicts->push([
                        'trip_schedule_id' => $trip->id,
                        'trip_code' => $trip->trip_code,
                        'base_trip_code' => $trip->trip_code,
                        'slot_label' => $slotLabel,
                        'slot' => $slotNumber,
                        'required_buses' => $requiredBuses,
                        'already_assigned_count' => $alreadyAssignedCount,
                        'direction' => (string) ($trip->direction ?? 'Incoming'),
                        'departure_time' => $this->databaseTime($trip->departure_time),
                        'departure_display' => $this->displayTime($trip->departure_time),
                        'estimated_arrival_time' => $this->databaseTime($trip->estimated_arrival_time),
                        'arrival_display' => $this->displayTime($trip->estimated_arrival_time),
                        'shift' => $trip->shift,
                        'route_code' => $trip->shuttleRoute?->route_code ?? '—',
                        'route_name' => $trip->shuttleRoute?->route_name ?? 'Unknown route',
                    'client_name' => $trip->client_name,
                        'terminal' => ($trip->direction ?? 'Incoming') === 'Outgoing'
                            ? ($trip->shuttleRoute?->destination ?? 'Not specified')
                            : ($trip->shuttleRoute?->origin ?? 'Not specified'),
                        'result' => 'Conflict',
                        'issue_type' => 'reserve_approval_required',
                        'missing_driver' => false,
                        'missing_bus' => false,
                        'suggested_driver_attendance_id' => $driver->id,
                        'suggested_bus_id' => $bus->id,
                        'suggested_bus_no' => $bus->bus_no,
                        'reason' => "Regular Buses are insufficient. Reserve Bus {$bus->bus_no} is available for Operations review and explicit selection.",
                        'ai' => [
                            'available' => true,
                            'status' => 'review_required',
                            'analysis_status' => 'rule_based_reserve_fallback',
                            'driver_explanation' => 'Eligible active driver available under the planning presence assumption.',
                            'bus_explanation' => 'Eligible Reserve Bus suggested after Regular Bus availability checks.',
                            'alternative_drivers' => [[
                                'id' => (int) $driver->id,
                                'label' => (string) $driver->driver_name,
                                'score' => null,
                                'reason' => 'Active, authorized where required, and free of overlapping assignments.',
                                'eligibility_source' => 'laravel_rules',
                                'score_source' => 'rule_fallback',
                            ]],
                            'alternative_buses' => [[
                                'id' => (int) $bus->id,
                                'label' => (string) $bus->bus_no.' (Reserve)',
                                'score' => null,
                                'reason' => 'Active Reserve Bus available for explicit Operations Head review.',
                                'eligibility_source' => 'laravel_rules',
                                'score_source' => 'rule_fallback',
                            ]],
                            'valid_pairs' => [[
                                'driver_attendance_id' => (int) $driver->id,
                                'driver_label' => (string) $driver->driver_name,
                                'driver_score' => null,
                                'driver_score_source' => 'rule_fallback',
                                'bus_id' => (int) $bus->id,
                                'bus_label' => (string) $bus->bus_no.' (Reserve)',
                                'bus_score' => null,
                                'bus_score_source' => 'rule_fallback',
                                'eligibility_source' => 'laravel_rules',
                                'reason' => 'Valid at generation time under active-resource, authorization, maintenance, client, and overlap rules. Final server revalidation still applies.',
                            ]],
                            'ml' => ['bus_source' => 'rule_fallback', 'driver_source' => 'rule_fallback'],
                        ],
                    ]);

                    continue;
                }

                if (! $driver || ! $bus) {
                    $reasons = [];
                    $slotSuffix = $requiredBuses > 1 ? " (Bus {$slotNumber}/{$requiredBuses})" : '';

                    if (! $driver) {
                        $reasons[] = "No eligible driver is available{$slotSuffix}.";
                    }

                    if (! $bus) {
                        $reasons[] = "No active bus is available{$slotSuffix}.";
                    }

                    $reserveAlternatives = $buses
                        ->filter(fn (Bus $candidate) => $candidate->fleet_purpose === 'Reserve'
                            && ! $this->busHasConflict($trip, (int) $candidate->id, $existingAssignments, $recommendations))
                        ->map(fn (Bus $candidate) => [
                            'id' => $candidate->id,
                            'label' => $candidate->bus_no.' (Reserve)',
                            'score' => null,
                            'reason' => 'Standby option. Operations Head may assign directly once an eligible driver and all safety checks are satisfied.',
                        ])
                        ->values()->all();

                    $aiPayload = $this->buildAiPayload(
                        trip: $trip,
                        selectedDriver: $driver,
                        selectedBus: $bus,
                        drivers: $drivers,
                        buses: $buses,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: $recommendations,
                        driverWorkloads: $driverWorkloads,
                        busWorkloads: $busWorkloads,
                        driverHistories: $aiHistories['driver_histories'],
                        busHistories: $aiHistories['bus_histories']
                    );

                    $aiResponse = $this->operationAi->recommend($aiPayload);

                    // Python only ranks candidates; Laravel owns eligibility.
                    // Regular-bus alternatives stay limited to authorized
                    // drivers. Reserve alternatives may use any active,
                    // active, conflict-free driver because Operations Head is
                    // the direct authority and confirmation revalidates all
                    // safeguards transactionally.
                    $allowedDriverAttendanceIds = collect($aiPayload['eligible_drivers'] ?? [])
                        ->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $driverSource = data_get($aiResponse, 'analysis.ml_driver_source', 'rule_fallback');
                    $busSource = data_get($aiResponse, 'analysis.ml_bus_source', 'rule_fallback');
                    $driverSuitability = (array) data_get($aiResponse, 'analysis.driver_suitability', []);
                    $busSuitability = (array) data_get($aiResponse, 'analysis.bus_suitability', []);

                    $aiDriverAlternatives = collect(data_get($aiResponse, 'analysis.alternative_drivers', []))
                            ->filter(fn ($option) => in_array((int) data_get($option, 'id'), $allowedDriverAttendanceIds, true))
                            ->map(fn ($option) => array_merge($option, [
                                'eligibility_source' => 'laravel_rules',
                                'score_source' => $driverSource,
                            ]));

                    $reserveDriverAlternatives = empty($reserveAlternatives)
                        ? collect()
                        : $drivers
                            ->reject(fn (DriverAttendance $candidate): bool => $this->driverHasConflict(
                                trip: $trip,
                                driverId: $candidate->driver_id,
                                existingAssignments: $existingAssignments,
                                generatedRecommendations: $recommendations
                            ))
                            ->sortBy(fn (DriverAttendance $candidate): string => sprintf(
                                '%08d-%s',
                                (int) $driverWorkloads->get($candidate->driver_id, 0),
                                strtolower($candidate->driver_name)
                            ))
                            ->take(5)
                            ->map(function (DriverAttendance $candidate) use (
                                $driverSource,
                                $driverSuitability,
                                $driverWorkloads
                            ): array {
                                $score = data_get($driverSuitability, (string) $candidate->id);

                                return [
                                    'id' => (int) $candidate->id,
                                    'label' => (string) $candidate->driver_name,
                                    'score' => is_numeric($score) ? (int) $score : null,
                                    'reason' => sprintf(
                                        'Active, assumed present for planning, no overlapping assignment, %d scheduled trip(s).',
                                        (int) $driverWorkloads->get($candidate->driver_id, 0)
                                    ),
                                    'eligibility_source' => 'laravel_rules',
                                    'score_source' => is_numeric($score) ? $driverSource : 'rule_fallback',
                                ];
                            });

                    $driverAlternatives = $aiDriverAlternatives
                        ->concat($reserveDriverAlternatives)
                        ->unique(fn ($option) => (int) data_get($option, 'id'))
                        ->values();

                    $busAlternatives = collect($reserveAlternatives)
                        ->map(function (array $option) use ($busSource, $busSuitability): array {
                            $score = data_get($busSuitability, (string) $option['id']);

                            return array_merge($option, [
                                'score' => is_numeric($score) ? (int) $score : null,
                                'eligibility_source' => 'laravel_rules',
                                'score_source' => is_numeric($score) ? $busSource : 'rule_fallback',
                            ]);
                        });

                    // Individually scored alternatives are not automatically valid
                    // combinations. Recheck both resources against confirmed
                    // assignments and all current generated preview slots.
                    $validPairs = $driverAlternatives
                        ->flatMap(fn (array $driverOption) => $busAlternatives
                            ->filter(fn (array $busOption): bool =>
                                $drivers->contains(fn (DriverAttendance $candidate): bool =>
                                    (int) $candidate->id === (int) $driverOption['id']
                                    && ! $this->driverHasConflict($trip, $candidate->driver_id, $existingAssignments, $recommendations)
                                )
                                && $buses->contains(fn (Bus $candidate): bool =>
                                    (int) $candidate->id === (int) $busOption['id']
                                    && $this->busMatchesTripClient($trip, $candidate)
                                    && ! $this->busHasConflict($trip, (int) $candidate->id, $existingAssignments, $recommendations)
                                )
                            )
                            ->map(fn (array $busOption): array => [
                                'driver_attendance_id' => (int) $driverOption['id'],
                                'driver_label' => (string) $driverOption['label'],
                                'driver_score' => $driverOption['score'],
                                'driver_score_source' => $driverOption['score_source'],
                                'bus_id' => (int) $busOption['id'],
                                'bus_label' => (string) $busOption['label'],
                                'bus_score' => $busOption['score'],
                                'bus_score_source' => $busOption['score_source'],
                                'eligibility_source' => 'laravel_rules',
                                'reason' => 'Valid at generation time under active-resource, authorization, maintenance, client, and overlap rules. Final server revalidation still applies.',
                            ])
                        )
                        // Show the highest-scoring eligible pair first.
                        // Missing ML scores are treated as unscored, not fabricated.
                        ->sortByDesc(fn (array $pair): float =>
                            (is_numeric($pair['driver_score']) ? (float) $pair['driver_score'] : 0.0)
                            + (is_numeric($pair['bus_score']) ? (float) $pair['bus_score'] : 0.0)
                        )
                        ->take(50)
                        ->values();

                    $conflicts->push([
                        'trip_schedule_id' => $trip->id,
                        'trip_code' => $trip->trip_code.($requiredBuses > 1 ? " (Bus {$slotNumber}/{$requiredBuses})" : ''),
                        'base_trip_code' => $trip->trip_code,
                        'slot_label' => $slotLabel,
                        'required_buses' => $requiredBuses,
                        'already_assigned_count' => $alreadyAssignedCount,
                        'slot' => $slotNumber,
                        'direction' => (string) ($trip->direction ?? 'Incoming'),
                        'departure_time' => $this->databaseTime($trip->departure_time),
                        'departure_display' => $this->displayTime($trip->departure_time),
                        'estimated_arrival_time' => $this->databaseTime($trip->estimated_arrival_time),
                        'arrival_display' => $this->displayTime($trip->estimated_arrival_time),
                        'shift' => $trip->shift,
                        'route_code' => $trip->shuttleRoute?->route_code ?? '—',
                        'route_name' => $trip->shuttleRoute?->route_name ?? 'Unknown route',
                    'client_name' => $trip->client_name,
                        'terminal' => ($trip->direction ?? 'Incoming') === 'Outgoing'
                            ? ($trip->shuttleRoute?->destination ?? 'Not specified')
                            : ($trip->shuttleRoute?->origin ?? 'Not specified'),
                        'result' => 'Conflict',
                        'issue_type' => 'resource_unavailable',
                        'missing_driver' => ! $driver,
                        'missing_bus' => ! $bus,
                        'reason' => implode(' ', $reasons),
                        'ai' => [
                            'available' => $aiResponse !== null,
                            'status' => data_get($aiResponse, 'status'),
                            'score' => data_get($aiResponse, 'analysis.recommendation_score'),
                            'analysis_status' => data_get($aiResponse, 'analysis.status'),
                            'driver_explanation' => data_get($aiResponse, 'analysis.driver_explanation'),
                            'bus_explanation' => data_get($aiResponse, 'analysis.bus_explanation'),
                            'conflict_explanation' => data_get($aiResponse, 'analysis.conflict_explanation'),
                            'warnings' => data_get($aiResponse, 'analysis.warnings', []),
                            'alternative_drivers' => $driverAlternatives->all(),
                            'alternative_buses' => $busAlternatives->all(),
                            'valid_pairs' => $validPairs->all(),
                            'ml' => [
                                'bus_ready' => (bool) data_get($aiResponse, 'analysis.ml_bus_ready', false),
                                'driver_ready' => (bool) data_get($aiResponse, 'analysis.ml_driver_ready', false),
                                'bus_source' => data_get($aiResponse, 'analysis.ml_bus_source', 'rule_fallback'),
                                'driver_source' => data_get($aiResponse, 'analysis.ml_driver_source', 'rule_fallback'),
                                'bus_suitability' => (array) data_get($aiResponse, 'analysis.bus_suitability', []),
                                'driver_suitability' => (array) data_get($aiResponse, 'analysis.driver_suitability', []),
                            ],
                            'conflict' => data_get($aiResponse, 'conflict'),
                        ],
                    ]);

                    continue;
                }

                $recommendation = [
                    'trip_schedule_id' => $trip->id,
                    'trip_code' => $trip->trip_code,
                    'slot_label' => $slotLabel,
                    'required_buses' => $requiredBuses,
                    'slot' => $slotNumber,
                    'direction' => (string) ($trip->direction ?? 'Incoming'),
                    'trip_date' => $trip->trip_date?->format('Y-m-d'),
                    'departure_time' => $this->databaseTime($trip->departure_time),
                    'departure_display' => $this->displayTime($trip->departure_time),
                    'estimated_arrival_time' => $this->databaseTime($trip->estimated_arrival_time),
                    'arrival_display' => $this->displayTime($trip->estimated_arrival_time),
                    'shift' => $trip->shift,
                    'route_code' => $trip->shuttleRoute?->route_code ?? '—',
                    'route_name' => $trip->shuttleRoute?->route_name ?? 'Unknown route',
                    'client_name' => $trip->client_name,
                    'driver_attendance_id' => $driver->id,
                    'driver_reference_type' => 'driver_master',
                    'driver_id' => $driver->driver_id,
                    'driver_name' => $driver->driver_name,
                    'driver_status' => $driver->status,
                    'bus_id' => $bus->id,
                    'bus_no' => $bus->bus_no,
                    'bus_model' => $bus->bus_model,
                    'bus_purpose' => $bus->fleet_purpose,
                    'reserve_approval_required' => $bus->fleet_purpose === 'Reserve',
                    'result' => 'Ready',
                ];

                $recommendations->push($recommendation);

                $driverWorkloads->put(
                    $driver->driver_id,
                    $driverWorkloads->get($driver->driver_id, 0) + 1
                );

                $busWorkloads->put(
                    $bus->id,
                    $busWorkloads->get($bus->id, 0) + 1
                );
            }
        }

        return response()->json([
            'success' => true,

            'message' => $trips->isEmpty()
                    ? 'No Scheduled and Unassigned trips were found.'
                    : 'Schedule recommendations generated successfully.',

            'summary' => [
                'trips' => $trips->count(),

                'drivers' => $drivers->count(),

                'buses' => $buses->where('fleet_purpose', 'Regular')->count(),

                'ready' => $recommendations->count(),

                'conflicts' => $conflicts->count(),

                'incoming' => [
                    'trips' => $trips->filter(fn ($t) => ($t->direction ?? 'Incoming') === 'Incoming')->count(),
                    'required_buses' => (int) $trips->filter(fn ($t) => ($t->direction ?? 'Incoming') === 'Incoming')->sum(fn ($t) => max(1, (int) ($t->required_buses ?? 1))),
                    'ready' => $recommendations->filter(fn ($r) => ($r['direction'] ?? 'Incoming') === 'Incoming')->count(),
                    'conflicts' => $conflicts->filter(fn ($c) => ($c['direction'] ?? 'Incoming') === 'Incoming')->count(),
                ],

                'outgoing' => [
                    'trips' => $trips->filter(fn ($t) => ($t->direction ?? 'Incoming') === 'Outgoing')->count(),
                    'required_buses' => (int) $trips->filter(fn ($t) => ($t->direction ?? 'Incoming') === 'Outgoing')->sum(fn ($t) => max(1, (int) ($t->required_buses ?? 1))),
                    'ready' => $recommendations->filter(fn ($r) => ($r['direction'] ?? 'Incoming') === 'Outgoing')->count(),
                    'conflicts' => $conflicts->filter(fn ($c) => ($c['direction'] ?? 'Incoming') === 'Outgoing')->count(),
                ],
            ],

            'recommendations' => $recommendations->values(),

            'conflicts' => $conflicts->map(function (array $conflict) use ($recommendations): array {
                $required = max(1, (int) $conflict['required_buses']);
                $alreadyAssigned = max(0, (int) ($conflict['already_assigned_count'] ?? 0));
                $generated = $recommendations->filter(
                    fn (array $recommendation): bool => $recommendation['trip_schedule_id'] === $conflict['trip_schedule_id']
                )->count();
                $conflict['ready_buses'] = min($required, $alreadyAssigned + $generated);

                return $conflict;
            })->values(),
        ]);
    }

    /**
     * Preview-only assignment editor. No operational rows are written here.
     * Final confirmation still performs transactional revalidation.
     */
    public function editPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trip_schedule_id' => ['required', 'integer', 'exists:trip_schedules,id'],
            // The legacy field name is retained for draft/API compatibility;
            // generated previews now carry a Driver master id, not attendance.
            'driver_attendance_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'bus_id' => ['nullable', 'integer', 'exists:buses,id'],
            'recommendations' => ['sometimes', 'array', 'max:200'],
            'recommendations.*.trip_schedule_id' => ['required', 'integer'],
            'recommendations.*.driver_attendance_id' => ['required', 'integer'],
            'recommendations.*.driver_reference_type' => ['sometimes', 'in:driver_master,legacy_attendance'],
            'recommendations.*.bus_id' => ['required', 'integer'],
        ]);
        $trip = TripSchedule::query()->with('shuttleRoute')->findOrFail($validated['trip_schedule_id']);
        if ($trip->hasDeparted() || ! in_array($trip->status, ['Scheduled', 'Ready'], true)
            || $trip->assignment_status === 'Assigned' || $trip->shuttleRoute?->status !== 'Active') {
            throw ValidationException::withMessages(['trip_schedule_id' => 'This trip is no longer available. Regenerate the preview.']);
        }
        $date = $trip->trip_date->toDateString();
        $drivers = $this->assumedPresentDrivers($date);
        $buses = Bus::query()->whereIn('fleet_purpose', ['Regular', 'Reserve'])->where('status', 'Active')
            ->whereNotIn('bus_no', JobOrder::query()->where('status', '!=', 'Completed')->select('bus_no'))
            ->orderBy('bus_no')->get(['id', 'bus_no', 'fleet_purpose'])
            ->filter(fn (Bus $bus) => $this->busMatchesTripClient($trip, $bus))->values();
        // First request without both selections supplies allowed choices.
        if (empty($validated['driver_attendance_id']) || empty($validated['bus_id'])) {
            return response()->json([
                'drivers' => $drivers->map(fn ($d) => ['id' => $d->id, 'name' => $d->driver_name, 'status' => $d->status])->values(),
                'buses' => $buses->map(fn ($b) => ['id' => $b->id, 'name' => $b->bus_no.($b->fleet_purpose === 'Reserve' ? ' (Reserve — Head assignment only)' : ''), 'purpose' => $b->fleet_purpose])->values(),
            ]);
        }
        $driver = $drivers->firstWhere('id', (int) $validated['driver_attendance_id']);
        $bus = $buses->firstWhere('id', (int) $validated['bus_id']);
        if (! $driver || ! $bus) {
            throw ValidationException::withMessages(['assignment' => 'Selected driver or bus is not eligible for this trip.']);
        }
        if ($bus->fleet_purpose === 'Reserve') {
            $actor = auth()->user();
            if (! $actor?->canAssignReserveBus()) {
                throw ValidationException::withMessages([
                    'bus_id' => 'Only Operations Head may select a Reserve Bus.',
                ]);
            }
        }
        $this->assertAuthorizedBusPair($driver->driver_id, (int) $bus->id, 'assignment');
        $this->assertBusMatchesTripClient($trip, $bus, 'assignment');
        $others = collect($validated['recommendations'] ?? []);
        $tripIds = $others->pluck('trip_schedule_id')->push($trip->id)->unique();
        $otherTrips = TripSchedule::query()->whereIn('id', $tripIds)->get()->keyBy('id');
        $assignmentDates = [$trip->trip_date->copy()->subDay()->toDateString(), $date,
            $trip->trip_date->copy()->addDay()->toDateString()];
        $existing = TripAssignment::query()->with('tripSchedule')
            ->whereHas('tripSchedule', fn ($q) => $q->whereIn('trip_date', $assignmentDates)
                ->whereNotIn('status', ['Cancelled', 'Completed']))->get();
        $generated = $others->map(function ($item) use ($otherTrips): array {
            $otherTrip = $otherTrips->get((int) $item['trip_schedule_id']);
            $usesDriverMaster = ($item['driver_reference_type'] ?? 'legacy_attendance') === 'driver_master';
            $attendance = $usesDriverMaster
                ? $this->assumedPresentDrivers(
                    $otherTrip?->trip_date?->toDateString() ?? now()->toDateString()
                )->firstWhere('id', (int) $item['driver_attendance_id'])
                : DriverAttendance::query()->find((int) $item['driver_attendance_id']);

            return [
                'driver_id' => $attendance?->driver_id,
                'bus_id' => (int) $item['bus_id'],
                'departure_time' => $otherTrip?->departure_time,
                'estimated_arrival_time' => $otherTrip?->estimated_arrival_time,
                'trip_schedule_id' => (int) $item['trip_schedule_id'],
                'trip_date' => $otherTrip?->trip_date?->toDateString(),
            ];
        });
        if ($this->driverHasConflict($trip, $driver->driver_id, $existing, $generated)
            || $this->busHasConflict($trip, (int) $bus->id, $existing, $generated)) {
            throw ValidationException::withMessages(['assignment' => 'This driver or bus overlaps another existing or preview assignment.']);
        }
        if ($trip->assignments()->count() + 1 + $others->where('trip_schedule_id', $trip->id)->count()
            > max(1, (int) $trip->required_buses)) {
            throw ValidationException::withMessages(['assignment' => 'All required bus slots for this trip are already occupied.']);
        }

        return response()->json([
            'success' => true,
            'driver' => ['id' => $driver->id, 'driver_id' => $driver->driver_id,
                'name' => $driver->driver_name, 'status' => $driver->status,
                'reference_type' => 'driver_master'],
            'bus' => ['id' => $bus->id, 'name' => $bus->bus_no, 'purpose' => $bus->fleet_purpose],
        ]);
    }

    /**
     * Persist reviewed auto-scheduling recommendations.
     *
     * Only record identifiers are accepted from the browser. Every trip,
     * driver, bus, active status, authorization, and overlap is checked again while the
     * affected rows are locked so stale previews cannot create conflicts.
     */
    /**
     * Drafts are previews only: they never reserve buses or mutate trip assignments.
     * Keep them scoped to the authenticated Operations user and selected filters.
     */
    public function listDrafts(Request $request): JsonResponse
    {
        $drafts = DB::table('auto_schedule_drafts')
            ->where('created_by', $request->user()->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn ($draft) => [
                'schedule_date' => $draft->schedule_date,
                'shift' => $draft->shift,
                'shuttle_route_id' => $draft->route_filter,
                'status' => $draft->status,
                'updated_at' => $draft->updated_at,
                'ready_count' => count(json_decode($draft->recommendations ?: '[]', true) ?: []),
                'review_count' => count(json_decode($draft->conflicts ?: '[]', true) ?: []),
                'decision_count' => count(json_decode($draft->decisions ?: '[]', true) ?: []),
            ]);

        return response()->json(['drafts' => $drafts]);
    }

    public function loadDraft(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'shift' => ['sometimes', 'string', 'max:30'],
            'shuttle_route_id' => ['sometimes', 'string', 'max:50'],
        ]);

        $draft = DB::table('auto_schedule_drafts')
            ->where('created_by', $request->user()->id)
            ->where('schedule_date', $filters['schedule_date'])
            ->where('shift', $filters['shift'] ?? 'all')
            ->where('route_filter', $filters['shuttle_route_id'] ?? 'all')
            ->first();

        return response()->json(['draft' => $draft ? [
            'recommendations' => json_decode($draft->recommendations, true),
            'conflicts' => json_decode($draft->conflicts, true),
            'decisions' => json_decode($draft->decisions ?: '[]', true),
            'summary' => json_decode($draft->summary ?: '{}', true),
            'status' => $draft->status,
            'updated_at' => $draft->updated_at,
        ] : null]);
    }

    public function deleteDraft(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'shift' => ['sometimes', 'string', 'max:30'],
            'shuttle_route_id' => ['sometimes', 'string', 'max:50'],
        ]);
        DB::table('auto_schedule_drafts')
            ->where('created_by', $request->user()->id)
            ->where('schedule_date', $filters['schedule_date'])
            ->where('shift', $filters['shift'] ?? 'all')
            ->where('route_filter', $filters['shuttle_route_id'] ?? 'all')
            ->delete();

        return response()->json(['deleted' => true]);
    }

    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'shift' => ['sometimes', 'string', 'max:30'],
            'shuttle_route_id' => ['sometimes', 'string', 'max:50'],
            'recommendations' => ['present', 'array', 'max:200'],
            'conflicts' => ['present', 'array', 'max:200'],
            'decisions' => ['sometimes', 'array', 'max:200'],
            'decisions.*.trip_schedule_id' => ['required', 'integer', 'exists:trip_schedules,id'],
            'decisions.*.slot' => ['required', 'integer', 'min:1', 'max:30'],
            'decisions.*.decision' => ['required', 'in:manual_later'],
            'decisions.*.notes' => ['nullable', 'string', 'max:1000'],
            'summary' => ['sometimes', 'array'],
        ]);

        $now = now();
        DB::table('auto_schedule_drafts')->upsert([[
            'created_by' => $request->user()->id,
            'schedule_date' => $validated['schedule_date'],
            'shift' => $validated['shift'] ?? 'all',
            'route_filter' => $validated['shuttle_route_id'] ?? 'all',
            'recommendations' => json_encode($validated['recommendations'], JSON_THROW_ON_ERROR),
            'conflicts' => json_encode($validated['conflicts'], JSON_THROW_ON_ERROR),
            'decisions' => json_encode($validated['decisions'] ?? [], JSON_THROW_ON_ERROR),
            'summary' => json_encode($validated['summary'] ?? [], JSON_THROW_ON_ERROR),
            'status' => 'Draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['created_by', 'schedule_date', 'shift', 'route_filter'],
            ['recommendations', 'conflicts', 'decisions', 'summary', 'status', 'updated_at']);

        return response()->json(['saved' => true, 'updated_at' => $now->toIso8601String()]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recommendations' => [
                'present',
                'array',
                'max:200',
            ],
            'recommendations.*.trip_schedule_id' => [
                'required',
                'integer',
                'exists:trip_schedules,id',
            ],
            'recommendations.*.driver_attendance_id' => [
                'required',
                'integer',
            ],
            'recommendations.*.driver_reference_type' => ['sometimes', 'in:driver_master,legacy_attendance'],
            'recommendations.*.bus_id' => [
                'required',
                'integer',
                'exists:buses,id',
            ],
            'recommendations.*.slot' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'decisions' => ['sometimes', 'array', 'max:200'],
            'decisions.*.trip_schedule_id' => ['required', 'integer', 'exists:trip_schedules,id'],
            'decisions.*.slot' => ['required', 'integer', 'min:1', 'max:30'],
            'decisions.*.decision' => ['required', 'in:manual_later'],
            'decisions.*.notes' => ['nullable', 'string', 'max:1000'],
            'conflicts' => ['sometimes', 'array', 'max:200'],
            'draft_filters' => ['sometimes', 'array'],
            'draft_filters.schedule_date' => ['required_with:draft_filters', 'date_format:Y-m-d'],
            'draft_filters.shift' => ['sometimes', 'string', 'max:30'],
            'draft_filters.shuttle_route_id' => ['sometimes', 'string', 'max:50'],
        ]);

        if (! empty($validated['conflicts'])) {
            throw ValidationException::withMessages([
                'conflicts' => 'Resolve every scheduling conflict before confirming the schedule.',
            ]);
        }

        if (empty($validated['recommendations']) && empty($validated['decisions'])) {
            throw ValidationException::withMessages([
                'recommendations' => 'Keep at least one valid assignment or record how an unfilled slot will be handled.',
            ]);
        }

        $submittedTripIds = collect($validated['recommendations'])
            ->pluck('trip_schedule_id')
            ->merge(collect($validated['decisions'] ?? [])->pluck('trip_schedule_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique();

        if (empty($validated['draft_filters'])) {
            $submittedDates = TripSchedule::query()
                ->whereIn('id', $submittedTripIds)
                ->pluck('trip_date')
                ->map(fn ($date): string => Carbon::parse($date)->toDateString())
                ->unique();

            $hasSavedDraft = DB::table('auto_schedule_drafts')
                ->where('created_by', $request->user()->id)
                ->whereIn('schedule_date', $submittedDates)
                ->exists();

            if ($hasSavedDraft) {
                throw ValidationException::withMessages([
                    'draft_filters' => 'This schedule has a saved draft. Reload it before confirming so the saved review can be verified.',
                ]);
            }
        } else {
            $filters = $validated['draft_filters'];
            $savedDraft = DB::table('auto_schedule_drafts')
                ->where('created_by', $request->user()->id)
                ->where('schedule_date', $filters['schedule_date'])
                ->where('shift', $filters['shift'] ?? 'all')
                ->where('route_filter', $filters['shuttle_route_id'] ?? 'all')
                ->first();

            if (! $savedDraft) {
                throw ValidationException::withMessages([
                    'draft_filters' => 'The saved draft could not be found for this operator and filter set. Save the draft again before confirming.',
                ]);
            }

            $savedConflicts = json_decode($savedDraft->conflicts ?: '[]', true) ?: [];
            if ($savedConflicts !== ($validated['conflicts'] ?? [])) {
                throw ValidationException::withMessages([
                    'conflicts' => 'The submitted conflicts do not match the saved draft. Resolve and save the draft again before confirming.',
                ]);
            }

            if ($this->canonicalDraftDecisions(json_decode($savedDraft->decisions ?: '[]', true) ?: [])
                !== $this->canonicalDraftDecisions($validated['decisions'] ?? [])) {
                throw ValidationException::withMessages([
                    'decisions' => 'The submitted handling decisions do not match the saved draft. Save the draft again before confirming.',
                ]);
            }

            if ($this->canonicalDraftRecommendations(json_decode($savedDraft->recommendations ?: '[]', true) ?: [])
                !== $this->canonicalDraftRecommendations($validated['recommendations'])) {
                throw ValidationException::withMessages([
                    'recommendations' => 'The submitted assignments do not match the saved draft. Save the draft again before confirming.',
                ]);
            }
        }

        $savedCount = DB::transaction(function () use ($validated): int {
            $recommendations = collect(
                $validated['recommendations']
            );
            $decisions = collect($validated['decisions'] ?? []);
            $tripIds = $recommendations->pluck('trip_schedule_id')
                ->merge($decisions->pluck('trip_schedule_id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $trips = TripSchedule::query()
                ->with('shuttleRoute')
                ->whereIn(
                    'id',
                    $tripIds
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $candidateDate = $trips->first()?->trip_date?->toDateString()
                ?? now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
            $masterDriverIds = $recommendations
                ->filter(fn (array $item): bool => ($item['driver_reference_type'] ?? 'legacy_attendance') === 'driver_master')
                ->pluck('driver_attendance_id')
                ->map(fn ($id): int => (int) $id);
            $legacyAttendanceIds = $recommendations
                ->reject(fn (array $item): bool => ($item['driver_reference_type'] ?? 'legacy_attendance') === 'driver_master')
                ->pluck('driver_attendance_id')
                ->map(fn ($id): int => (int) $id);
            $drivers = $this->assumedPresentDrivers($candidateDate, lockForUpdate: true)
                ->whereIn('id', $masterDriverIds)
                ->keyBy('id');
            $legacyAttendances = DriverAttendance::query()
                ->with('driver')
                ->whereIn('id', $legacyAttendanceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $buses = Bus::query()
                ->whereIn(
                    'id',
                    $recommendations->pluck('bus_id')
                )
                ->whereIn('fleet_purpose', ['Regular', 'Reserve'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $tripDates = $trips
                ->pluck('trip_date')
                ->filter()
                ->flatMap(function ($date): array {
                    $formatted = $date->format('Y-m-d');

                    return [
                        date(
                            'Y-m-d',
                            strtotime($formatted.' -1 day')
                        ),
                        $formatted,
                        date(
                            'Y-m-d',
                            strtotime($formatted.' +1 day')
                        ),
                    ];
                })
                ->unique()
                ->values();

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

            foreach ($trips as $trip) {
                if ($trip->hasDeparted() || ! in_array($trip->status, ['Scheduled', 'Ready'], true)) {
                    $recommendationIndex = $recommendations->search(
                        fn (array $item): bool => (int) $item['trip_schedule_id'] === (int) $trip->id
                    );
                    throw ValidationException::withMessages([
                        $recommendationIndex === false ? 'decisions' : "recommendations.{$recommendationIndex}" => "{$trip->trip_code} is no longer available. Regenerate the schedule.",
                    ]);
                }

                $requiredCount = max(1, (int) ($trip->required_buses ?? 1));
                $existingAssignmentsForTrip = $trip->assignments()->lockForUpdate()->get();
                $existingCount = $existingAssignmentsForTrip->count();
                $occupiedSlots = $existingAssignmentsForTrip
                    ->pluck('slot_number')
                    ->filter(fn ($slot): bool => $slot !== null)
                    ->map(fn ($slot): int => (int) $slot)
                    ->unique();
                $legacyCount = $existingAssignmentsForTrip->whereNull('slot_number')->count();
                $legacySlots = collect(range(1, $requiredCount))
                    ->reject(fn (int $slot): bool => $occupiedSlots->contains($slot))
                    ->take($legacyCount);
                $occupiedSlots = $occupiedSlots->merge($legacySlots)->unique();
                $plannedAssignments = $recommendations
                    ->where('trip_schedule_id', $trip->id)
                    ->values();
                $tripDecisions = $decisions->where('trip_schedule_id', $trip->id);
                $decisionSlots = $tripDecisions->pluck('slot')->map(fn ($slot) => (int) $slot);
                $assignmentSlots = $plannedAssignments->map(
                    fn (array $item, int $index): int => (int) ($item['slot'] ?? collect(range(1, $requiredCount))
                        ->reject(fn (int $slot): bool => $occupiedSlots->contains($slot))
                        ->values()
                        ->get($index, $requiredCount + 1))
                );
                $reviewedSlots = $assignmentSlots->merge($decisionSlots)->sort()->values();
                $requiredSlots = collect(range(1, $requiredCount))
                    ->reject(fn (int $slot): bool => $occupiedSlots->contains($slot))
                    ->values();

                if ($reviewedSlots->unique()->count() !== $reviewedSlots->count()
                    || $reviewedSlots->all() !== $requiredSlots->all()) {
                    throw ValidationException::withMessages([
                        'decisions' => "Every remaining slot for {$trip->trip_code} must have exactly one assignment or handling decision.",
                    ]);
                }
            }

            foreach ($recommendations as $index => $recommendation) {
                $trip = $trips->get(
                    (int) $recommendation['trip_schedule_id']
                );

                $usesDriverMaster = ($recommendation['driver_reference_type'] ?? 'legacy_attendance') === 'driver_master';
                $driver = ($usesDriverMaster ? $drivers : $legacyAttendances)->get(
                    (int) $recommendation['driver_attendance_id']
                );

                $bus = $buses->get(
                    (int) $recommendation['bus_id']
                );

                $field = "recommendations.{$index}";

                if (
                    ! $trip
                    || $trip->assignment_status === 'Assigned'
                    || ! in_array($trip->status, ['Scheduled', 'Ready'], true)
                    || $trip->hasDeparted()
                    || ! $trip->shuttleRoute
                    || $trip->shuttleRoute->status !== 'Active'
                ) {
                    throw ValidationException::withMessages([
                        $field => 'A selected trip is no longer available for assignment. Generate the schedule again.',
                    ]);
                }

                $assignedCount = $trip->assignments()->count();
                $requiredCount = max(1, (int) ($trip->required_buses ?? 1));
                $slotNumber = (int) ($recommendation['slot'] ?? ($assignedCount + 1));

                if ($assignedCount >= $requiredCount) {
                    throw ValidationException::withMessages([
                        $field => 'This trip is already fully assigned. Generate the schedule again.',
                    ]);
                }

                if ($slotNumber < 1 || $slotNumber > $requiredCount
                    || $trip->assignments()->where('slot_number', $slotNumber)->exists()) {
                    throw ValidationException::withMessages([
                        $field => "Slot {$slotNumber} is no longer available for this trip. Regenerate the schedule.",
                    ]);
                }

                $activeDriver = $driver
                    ? Driver::query()
                        ->where('driver_id', $driver->driver_id)
                        ->where('employment_status', 'Active')
                        ->lockForUpdate()
                        ->first()
                    : null;

                if (
                    ! $driver
                    || ($usesDriverMaster
                        ? $driver->status !== 'Assumed Present'
                        : ($driver->status !== 'Present' || ! $driver->attendance_date?->isSameDay($trip->trip_date)))
                    || ! $activeDriver
                ) {
                    throw ValidationException::withMessages([
                        $field => 'A selected driver is no longer eligible for this trip. Generate the schedule again.',
                    ]);
                }

                if ($bus && $activeDriver) {
                    $this->assertAuthorizedBusPair($activeDriver->driver_id, (int) $bus->id, $field);
                    $this->assertBusMatchesTripClient($trip, $bus, $field);
                }

                if ($bus && JobOrder::query()
                    ->where('bus_no', $bus->bus_no)
                    ->where('status', '!=', 'Completed')
                    ->exists()) {
                    throw ValidationException::withMessages([
                        $field => 'A selected bus has an unresolved Maintenance Job Order. Generate the schedule again.',
                    ]);
                }

                if ($bus && $bus->fleet_purpose === 'Reserve') {
                    $actor = auth()->user();
                    if (! $actor?->canAssignReserveBus()) {
                        throw ValidationException::withMessages([
                            $field => 'Only the Operations Head may assign a Reserve Bus.',
                        ]);
                    }

                    $regularCandidates = Bus::query()
                        ->where('fleet_purpose', 'Regular')
                        ->where('status', 'Active')
                        ->whereNotIn('bus_no', JobOrder::query()
                            ->where('status', '!=', 'Completed')->select('bus_no'))
                        ->lockForUpdate()
                        ->get();

                    if ($regularCandidates->contains(function (Bus $candidate) use (
                        $trip, $trips, $drivers, $legacyAttendances, $recommendations, $index, $existingAssignments
                    ): bool {
                        if (! $this->busMatchesTripClient($trip, $candidate)
                            || $this->busHasConflict($trip, $candidate->id, $existingAssignments, collect())) {
                            return false;
                        }

                        // A Regular Bus already reserved for another overlapping
                        // recommendation in this same confirmation is not available.
                        $busIsPlanned = $recommendations->contains(function (array $planned, int $plannedIndex) use (
                            $candidate, $trip, $trips, $index
                        ): bool {
                            if ($plannedIndex === $index || (int) $planned['bus_id'] !== (int) $candidate->id) {
                                return false;
                            }

                            $plannedTrip = $trips->get((int) $planned['trip_schedule_id']);

                            return $plannedTrip !== null && $this->tripSchedulesOverlap($trip, $plannedTrip);
                        });

                        if ($busIsPlanned) {
                            return false;
                        }

                        // Reserve use is blocked only when a complete Regular
                        // driver-bus pair is currently eligible. A free bus by
                        // itself is not assignable without an active,
                        // authorized, conflict-free driver.
                        $eligibleDrivers = Driver::query()
                            ->where('employment_status', 'Active')
                            ->whereHas('authorizedBuses', fn ($busQuery) => $busQuery->whereKey($candidate->id))
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                        return $eligibleDrivers->contains(function (Driver $eligibleDriver) use (
                            $trip, $trips, $drivers, $legacyAttendances, $recommendations, $index, $existingAssignments
                        ): bool {
                            if ($this->driverHasConflict(
                                $trip,
                                $eligibleDriver->driver_id,
                                $existingAssignments,
                                collect()
                            )) {
                                return false;
                            }

                            return ! $recommendations->contains(function (array $planned, int $plannedIndex) use (
                                $eligibleDriver, $trip, $trips, $drivers, $legacyAttendances, $index
                            ): bool {
                                if ($plannedIndex === $index) {
                                    return false;
                                }

                                $plannedUsesMaster = ($planned['driver_reference_type'] ?? 'legacy_attendance') === 'driver_master';
                                $plannedAttendance = ($plannedUsesMaster ? $drivers : $legacyAttendances)
                                    ->get((int) $planned['driver_attendance_id']);
                                $plannedTrip = $trips->get((int) $planned['trip_schedule_id']);

                                return $plannedAttendance !== null
                                    && $plannedTrip !== null
                                    && $plannedAttendance->driver_id === $eligibleDriver->driver_id
                                    && $this->tripSchedulesOverlap($trip, $plannedTrip);
                            });
                        });
                    })) {
                        throw ValidationException::withMessages([
                            $field => 'An eligible Regular Bus is now available. Regenerate the schedule before using a Reserve Bus.',
                        ]);
                    }
                }

                if (! $bus || $bus->status !== 'Active') {
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
                    'slot_number' => $slotNumber,
                    'driver_attendance_id' => $usesDriverMaster ? null : $driver->id,
                    'driver_master_id' => $activeDriver->id,
                    'driver_id' => $driver->driver_id,
                    'driver_name' => $driver->driver_name,
                    'bus_id' => $bus->id,
                    'assigned_by' => auth()->id(),
                ]);

                AutoScheduleSlotDecision::query()
                    ->where('trip_schedule_id', $trip->id)
                    ->where('slot_number', $slotNumber)
                    ->where('status', 'Pending')
                    ->update([
                        'status' => 'Resolved',
                        'fulfilled_by_assignment_id' => $assignment->id,
                        'resolved_at' => now(),
                    ]);

                if ($bus->fleet_purpose === 'Reserve') {
                    DB::table('auto_schedule_reserve_approvals')->insert([
                        'trip_assignment_id' => $assignment->id,
                        'trip_schedule_id' => $trip->id,
                        'bus_id' => $bus->id,
                        'trip_code' => $trip->trip_code,
                        'bus_no' => $bus->bus_no,
                        'recorded_by' => auth()->id(),
                        'approved_by' => 'Operations Head (direct assignment)',
                        'approval_method' => 'Direct',
                        'approved_at' => now(),
                        'remarks' => 'Direct Reserve assignment by authorized Head.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $assignment->setRelation('tripSchedule', $trip);
                $existingAssignments->push($assignment);
            }

            foreach ($decisions as $decision) {
                AutoScheduleSlotDecision::query()->updateOrCreate(
                    [
                        'trip_schedule_id' => (int) $decision['trip_schedule_id'],
                        'slot_number' => (int) $decision['slot'],
                    ],
                    [
                        'decision' => $decision['decision'],
                        'status' => 'Pending',
                        'notes' => $decision['notes'] ?? null,
                        'decided_by' => auth()->id(),
                        'decided_at' => now(),
                        'resolved_at' => null,
                    ]
                );
            }

            foreach ($trips as $trip) {
                $trip->syncAssignmentStatus();
            }

            return $recommendations->count();
        }, 5);

        if (! empty($validated['draft_filters'])) {
            $filters = $validated['draft_filters'];
            $draftQuery = DB::table('auto_schedule_drafts')
                ->where('created_by', $request->user()->id)
                ->where('schedule_date', $filters['schedule_date'])
                ->where('shift', $filters['shift'] ?? 'all')
                ->where('route_filter', $filters['shuttle_route_id'] ?? 'all');

            if (! empty($validated['decisions'])) {
                $draftQuery->update([
                    'recommendations' => '[]',
                    'conflicts' => '[]',
                    'decisions' => json_encode($validated['decisions'], JSON_THROW_ON_ERROR),
                    'status' => 'Partially Confirmed',
                    'updated_at' => now(),
                ]);
            } else {
                $draftQuery->delete();
            }
        }

        $firstTripId = (int) collect($validated['recommendations'])
            ->pluck('trip_schedule_id')
            ->merge(collect($validated['decisions'] ?? [])->pluck('trip_schedule_id'))
            ->first();

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            $firstTripId,
            "{$savedCount} auto-scheduled assignment(s) were saved; pending slot decisions remain tracked."
        );

        return response()->json([
            'success' => true,
            'message' => ! empty($validated['decisions'])
                ? "{$savedCount} assignment(s) confirmed. Unfilled slots remain pending for manual or external handling."
                : "{$savedCount} schedule assignment(s) saved successfully.",
            'saved' => $savedCount,
            'pending_decisions' => count($validated['decisions'] ?? []),
            'draft_preserved' => ! empty($validated['decisions']),
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
        Collection $busWorkloads,
        array $driverHistories = [],
        array $busHistories = []
    ): array {
        return [
            'trip' => [
                'id' => (int) $trip->id,

                'trip_code' => (string) $trip->trip_code,

                'trip_date' => $trip->trip_date
                    ?->format('Y-m-d')
                    ?? '',

                'shift' => $trip->shift,

                'route_code' => $trip->shuttleRoute
                    ?->route_code,

                'route_name' => $trip->shuttleRoute
                    ?->route_name,
                    'client_name' => $trip->client_name,

                'departure_time' => $this->databaseTime(
                    $trip->departure_time
                ),

                'arrival_time' => $this->databaseTime(
                    $trip->estimated_arrival_time
                ),
            ],

            'selected_driver' => $selectedDriver
                    ? $this->formatDriverForAi(
                        driver: $selectedDriver,
                        trip: $trip,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: $generatedRecommendations,
                        workloads: $driverWorkloads
                    )
                    : null,

            'selected_bus' => ($selectedBus && $this->busMatchesTripClient($trip, $selectedBus))
                    ? $this->formatBusForAi(
                        bus: $selectedBus,
                        trip: $trip,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: $generatedRecommendations,
                        workloads: $busWorkloads
                    )
                    : null,

            'eligible_drivers' => $drivers
                // Driver suitability is trip-specific: only drivers whose
                // permanent Regular bus serves this client belong in the AI
                // recommendation pool. Reserve units are review-only.
                ->filter(function (DriverAttendance $candidate) use ($trip, $buses): bool {
                    return $buses->contains(function (Bus $bus) use ($candidate, $trip): bool {
                        return $bus->fleet_purpose === 'Regular'
                            && $this->busMatchesTripClient($trip, $bus)
                            && Driver::query()->where('driver_id', $candidate->driver_id)
                                ->where('employment_status', 'Active')
                                ->whereHas('authorizedBuses', fn ($q) => $q->whereKey($bus->id))
                                ->exists();
                    });
                })
                ->map(
                    fn (
                        DriverAttendance $driver
                    ): array => $this->formatDriverForAi(
                        driver: $driver,
                        trip: $trip,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: $generatedRecommendations,
                        workloads: $driverWorkloads
                    )
                )
                ->values()
                ->all(),

            'eligible_buses' => $buses
                ->filter(fn (Bus $candidate) => $this->busMatchesTripClient($trip, $candidate))
                ->map(
                    fn (Bus $bus): array => $this->formatBusForAi(
                        bus: $bus,
                        trip: $trip,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: $generatedRecommendations,
                        workloads: $busWorkloads
                    )
                )
                ->values()
                ->all(),

            'driver_histories' => (object) $driverHistories,

            'bus_histories' => (object) $busHistories,
        ];
    }

    /**
     * Build the per-driver and per-bus historical feature aggregates used by
     * the Python ML recommender.
     *
     * - driver_histories are keyed by the DriverAttendance row id (the same id
     *   sent for each candidate driver) and are computed from the driver's real
     *   attendance records across all dates.
     * - bus_histories are keyed by the Bus id and are computed from the bus's
     *   real GPS trip records, fuel reports, and open maintenance job orders.
     *
     * These are legitimate database-derived signal; nothing is fabricated.
     */
    private function buildAiHistoryAggregates(
        Collection $drivers,
        Collection $buses
    ): array {
        // ---- Driver histories (keyed by DriverAttendance row id) ----
        $driverHistories = [];

        if ($drivers->isNotEmpty()) {
            $driverIds = $drivers
                ->pluck('driver_id')
                ->unique()
                ->values();

            $attendanceRows = DriverAttendance::query()
                ->whereIn('driver_id', $driverIds)
                ->get(['driver_id', 'attendance_date', 'status']);

            $attendanceByDriver = $attendanceRows
                ->groupBy('driver_id');

            foreach ($drivers as $driver) {
                $rows = $attendanceByDriver->get(
                    $driver->driver_id,
                    collect()
                );

                $total = $rows->count();

                if ($total === 0) {
                    $driverHistories[(int) $driver->id] = [
                        'attendance_rate' => 0.0,
                        'total_attendance' => 0.0,
                        'distinct_dates' => 0.0,
                        'has_history' => 0.0,
                    ];

                    continue;
                }

                $reliable = $rows->filter(
                    fn ($row): bool => strtolower((string) $row->status) === 'present'
                )->count();

                $distinctDates = $rows
                    ->pluck('attendance_date')
                    ->map(
                        fn ($d): string => $d instanceof Carbon
                                ? $d->toDateString()
                                : (string) $d
                    )
                    ->unique()
                    ->count();

                $driverHistories[(int) $driver->id] = [
                    'attendance_rate' => round($reliable / max($total, 1), 6),
                    'total_attendance' => (float) $total,
                    'distinct_dates' => (float) $distinctDates,
                    'has_history' => 1.0,
                ];
            }
        }

        // ---- Bus histories (keyed by Bus id) ----
        $busHistories = [];

        if ($buses->isNotEmpty()) {
            $busNos = $buses->pluck('bus_no')->values();

            // Averaged fuel efficiency per bus_no.
            $fuelByBus = FuelReport::query()
                ->whereIn('bus_no', $busNos)
                ->whereNotNull('km_per_liter')
                ->get(['bus_no', 'km_per_liter'])
                ->groupBy('bus_no')
                ->map(
                    fn (Collection $rows): float => round($rows->avg('km_per_liter'), 6)
                );

            // Averaged GPS trip profile per bus_no.
            $gpsByBus = GpsTripRecord::query()
                ->whereIn('bus_no', $busNos)
                ->get([
                    'bus_no',
                    'mileage_km',
                    'engine_hours',
                    'idling_minutes',
                    'total_minutes',
                ])
                ->groupBy('bus_no');

            // Maintenance job order profile per bus_no.
            $activeStatuses = [
                'On Going',
                'On Hold',
                'For Parts',
                'Pending',
            ];
            $jobByBus = JobOrder::query()
                ->whereIn('bus_no', $busNos)
                ->get(['bus_no', 'status'])
                ->groupBy('bus_no');

            foreach ($buses as $bus) {
                $busNo = (string) $bus->bus_no;

                $gpsRows = $gpsByBus->get($busNo, collect());
                $jobRows = $jobByBus->get($busNo, collect());

                $totalJobs = $jobRows->count();
                $activeJobs = $jobRows->filter(
                    fn ($row): bool => in_array(
                        (string) $row->status,
                        $activeStatuses,
                        true
                    )
                )->count();

                $gpsCount = $gpsRows->count();

                $busHistories[(int) $bus->id] = [
                    'capacity' => $bus->capacity !== null
                            ? (float) $bus->capacity
                            : 0.0,
                    'fuel_efficiency_avg' => (float) ($fuelByBus->get($busNo, 0.0) ?? 0.0),
                    'active_job_orders' => (float) $activeJobs,
                    'total_job_orders' => (float) $totalJobs,
                    'avg_trip_mileage_km' => $gpsCount > 0
                            ? round($gpsRows->avg('mileage_km') ?? 0.0, 6)
                            : 0.0,
                    'avg_trip_engine_hours' => $gpsCount > 0
                            ? round($gpsRows->avg('engine_hours') ?? 0.0, 6)
                            : 0.0,
                    'avg_trip_idling_minutes' => $gpsCount > 0
                            ? round($gpsRows->avg('idling_minutes') ?? 0.0, 6)
                            : 0.0,
                    'avg_trip_total_minutes' => $gpsCount > 0
                            ? round($gpsRows->avg('total_minutes') ?? 0.0, 6)
                            : 0.0,
                    'has_history' => $gpsCount > 0 ? 1.0 : 0.0,
                ];
            }
        }

        return [
            'driver_histories' => $driverHistories,
            'bus_histories' => $busHistories,
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
                existingAssignments: $existingAssignments,
                generatedRecommendations: $generatedRecommendations
            );

        return [
            'id' => (int) $driver->id,

            'name' => (string) $driver->driver_name,

            'status' => (string) $driver->status,

            // Driver Attendance no longer owns a working shift. Trip shift is
            // deliberately not copied onto the driver candidate because doing
            // so manufactures a perfect feature that does not exist in data.
            'shift' => null,

            'assigned_trips' => (int) $workloads->get(
                $driver->driver_id,
                0
            ),

            'assigned_minutes' => 0,

            'has_conflict' => $hasConflict,

            'conflict_end_time' => $hasConflict
                    ? $this->driverConflictEndTime(
                        trip: $trip,
                        driverId: $driver->driver_id,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: $generatedRecommendations
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
                existingAssignments: $existingAssignments,
                generatedRecommendations: $generatedRecommendations
            );

        return [
            'id' => (int) $bus->id,

            'bus_no' => (string) $bus->bus_no,

            'status' => (string) $bus->status,

            'mileage' => $bus->latest_gps_km !== null
                    ? (float) $bus->latest_gps_km
                    : null,

            'next_pms_mileage' => $bus->next_pms_km !== null
                    ? (float) $bus->next_pms_km
                    : null,

            'assigned_trips' => (int) $workloads->get(
                $bus->id,
                0
            ),

            'assigned_minutes' => 0,

            'has_conflict' => $hasConflict,

            'conflict_end_time' => $hasConflict
                    ? $this->busConflictEndTime(
                        trip: $trip,
                        busId: (int) $bus->id,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: $generatedRecommendations
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

            if (! $assignedTrip) {
                continue;
            }

            if (
                ! $this->tripSchedulesOverlap(
                    $trip,
                    $assignedTrip
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
            $generatedRecommendations as $recommendation
        ) {
            if (
                ($recommendation['driver_id'] ?? null)
                !== $driverId
            ) {
                continue;
            }

            if (
                ! $this->timesOverlap(
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

            if (! $assignedTrip) {
                continue;
            }

            if (
                ! $this->tripSchedulesOverlap(
                    $trip,
                    $assignedTrip
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
            $generatedRecommendations as $recommendation
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
                ! $this->timesOverlap(
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
                fn ($time): bool => is_string($time)
                    && $time !== ''
            )
            ->sortBy(
                fn (string $time): int => strtotime($time)
                    ?: PHP_INT_MAX
            )
            ->first();
    }

    /**
     * Reduce draft decisions to the fields that define the operator's choice.
     */
    private function canonicalDraftDecisions(array $decisions): array
    {
        return collect($decisions)
            ->map(fn (array $decision): array => [
                'trip_schedule_id' => (int) ($decision['trip_schedule_id'] ?? 0),
                'slot' => (int) ($decision['slot'] ?? 0),
                'decision' => (string) ($decision['decision'] ?? ''),
                'notes' => trim((string) ($decision['notes'] ?? '')),
            ])
            ->sortBy(fn (array $decision): string => $decision['trip_schedule_id'].'-'.$decision['slot'])
            ->values()
            ->all();
    }

    /**
     * Reduce draft recommendations to assignment and approval identity fields.
     */
    private function canonicalDraftRecommendations(array $recommendations): array
    {
        return collect($recommendations)
            ->map(function (array $recommendation): array {
                $approval = is_array($recommendation['oic_approval'] ?? null)
                    ? $recommendation['oic_approval']
                    : [];

                return [
                    'trip_schedule_id' => (int) ($recommendation['trip_schedule_id'] ?? 0),
                    'slot' => (int) ($recommendation['slot'] ?? 0),
                    'driver_attendance_id' => (int) ($recommendation['driver_attendance_id'] ?? 0),
                    'bus_id' => (int) ($recommendation['bus_id'] ?? 0),
                    'oic_approval' => [
                        'method' => (string) ($approval['method'] ?? ''),
                        'approved_at' => (string) ($approval['approved_at'] ?? ''),
                        'remarks' => trim((string) ($approval['remarks'] ?? '')),
                    ],
                ];
            })
            ->sortBy(fn (array $recommendation): string => $recommendation['trip_schedule_id'].'-'.$recommendation['slot'])
            ->values()
            ->all();
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
            'slot' => ['sometimes', 'integer', 'min:1', 'max:30'],
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
                    ! in_array($trip->status, ['Scheduled', 'Ready'], true)
                    || $trip->assignment_status === 'Assigned'
                    || $trip->hasDeparted()
                    || ! $trip->shuttleRoute
                    || $trip->shuttleRoute->status !== 'Active'
                ) {
                    throw ValidationException::withMessages([
                        'trip_schedule_id' => 'This trip is no longer available for resolution. Generate the schedule again.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Preserve the original trip duration
                |--------------------------------------------------------------------------
                */

                $originalStart =
                    $trip->departureDateTime()->timestamp;

                $originalEnd =
                    $trip->estimatedArrivalDateTime()->timestamp;

                if ($originalEnd <= $originalStart) {
                    throw ValidationException::withMessages([
                        'trip_schedule_id' => 'The original trip timing is invalid.',
                    ]);
                }

                $durationSeconds =
                    $originalEnd - $originalStart;

                $proposedDeparture = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $trip->trip_date->format('Y-m-d')
                        .' '
                        .$validated['proposed_departure_time'],
                    config('app.business_timezone', 'Asia/Manila')
                );
                $proposedStart = $proposedDeparture->timestamp;

                if (
                    $proposedStart
                    < now(config('app.business_timezone', 'Asia/Manila'))->startOfMinute()->timestamp
                ) {
                    throw ValidationException::withMessages([
                        'proposed_departure_time' => 'The proposed departure must be the current time or a future time.',
                    ]);
                }

                // A resolution may only move a trip to a time the client's
                // trip time configuration allows. Unconfigured clients keep
                // the unrestricted behavior.
                ClientTripTimes::assertTripDepartureAllowed(
                    $trip->client_name,
                    $trip->direction,
                    $proposedDeparture->format('H:i'),
                    'proposed_departure_time'
                );

                $proposedArrival = $proposedDeparture
                    ->copy()
                    ->addSeconds($durationSeconds);

                /*
                |--------------------------------------------------------------------------
                | Temporarily apply the proposed time
                |--------------------------------------------------------------------------
                |
                | The values are used for conflict checking but are not saved until
                | Laravel finds a valid driver and bus.
                |
                */

                $trip->departure_time = $proposedDeparture->format('H:i:s');
                $trip->estimated_arrival_time = $proposedArrival->format('H:i:s');
                $trip->estimated_arrival_date = $proposedArrival->toDateString();
                $trip->shift = TripSchedule::shiftForDeparture($proposedDeparture);

                $scheduleDate =
                    $trip->trip_date->format('Y-m-d');

                $drivers = $this->assumedPresentDrivers($scheduleDate, lockForUpdate: true);

                $buses = Bus::query()
                    ->with('operationClients:id,name')
                    ->where('fleet_purpose', 'Regular')
                    ->where(
                        'status',
                        'Active'
                    )
                    ->whereNotIn('bus_no', JobOrder::query()->where('status', '!=', 'Completed')->select('bus_no'))
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
                                    ->whereIn(
                                        'trip_date',
                                        [
                                            date(
                                                'Y-m-d',
                                                strtotime($scheduleDate.' -1 day')
                                            ),
                                            $scheduleDate,
                                            date(
                                                'Y-m-d',
                                                strtotime($scheduleDate.' +1 day')
                                            ),
                                        ]
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

                $workloadAssignments =
                    $existingAssignments
                        ->filter(
                            fn (TripAssignment $assignment): bool => $assignment->tripSchedule?->trip_date?->format('Y-m-d')
                                    === $scheduleDate
                        );

                $driverWorkloads =
                    $workloadAssignments
                        ->groupBy('driver_id')
                        ->map(
                            fn (Collection $items): int => $items->count()
                        );

                $busWorkloads =
                    $workloadAssignments
                        ->groupBy('bus_id')
                        ->map(
                            fn (Collection $items): int => $items->count()
                        );

                $generatedRecommendations =
                    collect();

                [$driver, $bus] = $this->findAvailableAuthorizedPair(
                    $trip, $drivers, $buses, $existingAssignments,
                    $generatedRecommendations, $driverWorkloads, $busWorkloads
                );

                if ($driver && ! Driver::query()
                    ->where('driver_id', $driver->driver_id)
                    ->where('employment_status', 'Active')
                    ->lockForUpdate()
                    ->first()
                ) {
                    $driver = null;
                }

                if ($driver && $bus) {
                    $this->assertAuthorizedBusPair($driver->driver_id, (int) $bus->id, 'resolution');
                    $this->assertBusMatchesTripClient($trip, $bus, 'resolution');
                }

                if (! $driver || ! $bus) {
                    $messages = [];

                    if (! $driver) {
                        $messages[] =
                            'No eligible driver is available at the proposed time.';
                    }

                    if (! $bus) {
                        $messages[] =
                            'No active conflict-free bus is available at the proposed time.';
                    }

                    throw ValidationException::withMessages([
                        'resolution' => implode(' ', $messages),
                    ]);
                }

                $requiredCount = max(1, (int) ($trip->required_buses ?? 1));
                $usedSlots = $trip->assignments()
                    ->lockForUpdate()
                    ->pluck('slot_number')
                    ->filter(fn ($slot): bool => $slot !== null)
                    ->map(fn ($slot): int => (int) $slot);
                $slotNumber = isset($validated['slot'])
                    ? (int) $validated['slot']
                    : collect(range(1, $requiredCount))
                        ->first(fn (int $slot): bool => ! $usedSlots->contains($slot));

                if ($slotNumber === null || $slotNumber > $requiredCount || $usedSlots->contains($slotNumber)) {
                    throw ValidationException::withMessages([
                        'slot' => "Slot {$slotNumber} is no longer available. Generate the schedule again.",
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
                        driverId: $driver->driver_id,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: collect()
                    )
                    || $this->busHasConflict(
                        trip: $trip,
                        busId: (int) $bus->id,
                        existingAssignments: $existingAssignments,
                        generatedRecommendations: collect()
                    )
                ) {
                    throw ValidationException::withMessages([
                        'resolution' => 'The proposed resolution now conflicts with another assignment. Generate the schedule again.',
                    ]);
                }

                $assignment =
                    TripAssignment::create([
                        'trip_schedule_id' => $trip->id,

                        'slot_number' => $slotNumber,

                        'driver_attendance_id' => null,

                        'driver_master_id' => $driver->id,

                        'driver_id' => $driver->driver_id,

                        'driver_name' => $driver->driver_name,

                        'bus_id' => $bus->id,

                        'assigned_by' => auth()->id(),
                    ]);

                AutoScheduleSlotDecision::query()
                    ->where('trip_schedule_id', $trip->id)
                    ->where('slot_number', $slotNumber)
                    ->where('status', 'Pending')
                    ->update([
                        'status' => 'Resolved',
                        'fulfilled_by_assignment_id' => $assignment->id,
                        'resolved_at' => now(),
                    ]);

                $trip->update([
                    'departure_time' => $proposedDeparture->format('H:i:s'),
                    'estimated_arrival_time' => $proposedArrival->format('H:i:s'),
                    'estimated_arrival_date' => $proposedArrival->toDateString(),
                    'shift' => $trip->shift,
                ]);

                // A resolved slot must not mark a multi-bus trip fully assigned.
                $trip->syncAssignmentStatus();

                return [
                    'assignment_id' => $assignment->id,

                    'trip_code' => $trip->trip_code,

                    'driver_name' => $driver->driver_name,

                    'bus_no' => $bus->bus_no,

                    'departure_time' => $proposedDeparture->format('H:i:s'),
                    'departure_display' => $proposedDeparture->format('g:i A'),
                    'arrival_time' => $proposedArrival->format('H:i:s'),
                    'arrival_display' => $proposedArrival->format('g:i A'),
                    'arrival_date' => $proposedArrival->toDateString(),
                    'shift' => $trip->shift,
                ];
            }
        );

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            (int) $validated['trip_schedule_id'],
            "Trip {$result['trip_code']} was resolved and assigned."
        );

        return response()->json([
            'success' => true,

            'message' => "Trip {$result['trip_code']} was resolved successfully.",

            'resolution' => $result,

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
                    return ! $this
                        ->driverHasConflict(
                            trip: $trip,
                            driverId: $driver->driver_id,
                            existingAssignments: $existingAssignments,
                            generatedRecommendations: $generatedRecommendations
                        );
                }
            );
    }

    /**
     * A dedicated client trip uses only that client's buses. A general
     * service uses only unallocated buses; borrowing needs explicit review.
     */
    private function busMatchesTripClient(TripSchedule $trip, Bus $bus): bool
    {
        // Reserve units are unallocated shared emergency assets. They may be
        // proposed for any client, but never automatically marked Ready.
        if ($bus->fleet_purpose === 'Reserve') {
            return true;
        }

        $client = trim((string) ($trip->client_name ?? ''));
        $allocated = $bus->operationClients->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)));

        if ($client === '') {
            return $allocated->isEmpty();
        }

        return $allocated->contains(mb_strtolower($client));
    }

    private function assertBusMatchesTripClient(TripSchedule $trip, Bus $bus, string $field): void
    {
        if (! $this->busMatchesTripClient($trip, $bus)) {
            throw ValidationException::withMessages([
                $field => 'This bus is not allocated to the trip client. Choose a client-compatible bus and regenerate the schedule.',
            ]);
        }
    }

    /**
     * Choose a compatible bus and an authorized, conflict-free driver together.
     * Regular buses are preferred before Reserve buses.
     *
     * @return array{0: ?DriverAttendance, 1: ?Bus}
     */
    private function findAvailableAuthorizedPair(
        TripSchedule $trip,
        Collection $drivers,
        Collection $buses,
        Collection $existingAssignments,
        Collection $generatedRecommendations,
        Collection $driverWorkloads,
        Collection $busWorkloads
    ): array {
        // Fixed daily rotation: lowest bus number first, skipping resources
        // excluded by client compatibility, maintenance, or overlaps below.
        // Reserve units remain a last-resort, review-only proposal.
        $orderedBuses = $buses->sort(function (Bus $first, Bus $second): int {
            $purpose = ($first->fleet_purpose === 'Reserve' ? 1 : 0)
                <=> ($second->fleet_purpose === 'Reserve' ? 1 : 0);

            return $purpose !== 0
                ? $purpose
                : strnatcasecmp((string) $first->bus_no, (string) $second->bus_no);
        });

        foreach ($orderedBuses as $bus) {
            if (! $this->busMatchesTripClient($trip, $bus)) {
                continue;
            }
            if ($this->busHasConflict($trip, (int) $bus->id, $existingAssignments, $generatedRecommendations)) {
                continue;
            }

            // Reserve buses have no permanent driver authorization. They are
            // review-only proposals that Operations Head must explicitly approve.
            // Regular buses must still use their authorized driver list.
            $eligibleDrivers = $bus->fleet_purpose === 'Reserve'
                ? $drivers
                : $drivers->whereIn('driver_id', Driver::query()
                    ->whereHas('authorizedBuses', fn ($query) => $query->whereKey($bus->id))
                    ->pluck('driver_id')
                    ->all());

            $driver = $this->findAvailableDriver(
                $trip,
                $eligibleDrivers,
                $existingAssignments,
                $generatedRecommendations,
                $driverWorkloads
            );

            if ($driver) {
                return [$driver, $bus];
            }
        }

        return [null, $this->findAvailableBus(
            $trip,
            $buses->filter(fn (Bus $bus) => $this->busMatchesTripClient($trip, $bus)),
            $existingAssignments, $generatedRecommendations, $busWorkloads
        )];
    }

    private function assertAuthorizedBusPair(string $driverId, int $busId, string $field): void
    {
        // Reserve units have no permanent driver. Explicitly reviewed reserve
        // selections require direct Operations Head authorization at confirmation.
        if (Bus::query()->whereKey($busId)->where('fleet_purpose', 'Reserve')->exists()) {
            return;
        }
        if (! Driver::query()
            ->where('driver_id', $driverId)
            ->whereHas('authorizedBuses', fn ($query) => $query->whereKey($busId))
            ->exists()) {
            throw ValidationException::withMessages([
                $field => 'The driver is not authorized for this bus. Update Authorized Buses in Driver Master List and regenerate the schedule.',
            ]);
        }
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
            ->sort(function (Bus $first, Bus $second): int {
                $purpose = ($first->fleet_purpose === 'Reserve' ? 1 : 0)
                    <=> ($second->fleet_purpose === 'Reserve' ? 1 : 0);

                return $purpose !== 0
                    ? $purpose
                    : strnatcasecmp((string) $first->bus_no, (string) $second->bus_no);
            })
            ->first(
                function (
                    Bus $bus
                ) use (
                    $trip,
                    $existingAssignments,
                    $generatedRecommendations
                ): bool {
                    return ! $this
                        ->busHasConflict(
                            trip: $trip,
                            busId: $bus->id,
                            existingAssignments: $existingAssignments,
                            generatedRecommendations: $generatedRecommendations
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

                    if (! $assignedTrip) {
                        return false;
                    }

                    return $this->tripSchedulesOverlap(
                        $trip,
                        $assignedTrip
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

                    if (! $assignedTrip) {
                        return false;
                    }

                    return $this->tripSchedulesOverlap(
                        $trip,
                        $assignedTrip
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

    private function tripSchedulesOverlap(
        TripSchedule $first,
        TripSchedule $second
    ): bool {
        $firstStart = $first->departureDateTime();
        $firstEnd = $first->estimatedArrivalDateTime();
        $secondStart = $second->departureDateTime();
        $secondEnd = $second->estimatedArrivalDateTime();

        return $firstStart->lt($secondEnd)
            && $firstEnd->gt($secondStart);
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
        $firstStartSeconds =
            $this->secondsFromMidnight($firstStart);

        $firstEndSeconds =
            $this->secondsFromMidnight($firstEnd);

        $secondStartSeconds =
            $this->secondsFromMidnight($secondStart);

        $secondEndSeconds =
            $this->secondsFromMidnight($secondEnd);

        if (
            $firstStartSeconds === null
            || $firstEndSeconds === null
            || $secondStartSeconds === null
            || $secondEndSeconds === null
        ) {
            return false;
        }

        if ($firstEndSeconds <= $firstStartSeconds) {
            $firstEndSeconds += 86400;
        }

        if ($secondEndSeconds <= $secondStartSeconds) {
            $secondEndSeconds += 86400;
        }

        foreach ([-86400, 0, 86400] as $shift) {
            $shiftedSecondStart =
                $secondStartSeconds + $shift;

            $shiftedSecondEnd =
                $secondEndSeconds + $shift;

            if (
                $firstStartSeconds < $shiftedSecondEnd
                && $firstEndSeconds > $shiftedSecondStart
            ) {
                return true;
            }
        }

        return false;
    }

    private function secondsFromMidnight(
        mixed $time
    ): ?int {
        if (! $time) {
            return null;
        }

        $timestamp = strtotime((string) $time);

        if ($timestamp === false) {
            return null;
        }

        return (
            ((int) date('H', $timestamp)) * 3600
        ) + (
            ((int) date('i', $timestamp)) * 60
        ) + (int) date('s', $timestamp);
    }

    /**
     * Build non-persistent scheduling candidates from the Driver master.
     *
     * DriverAttendance remains historical DDR evidence. These transient
     * objects preserve the existing preview/ML contract without inserting or
     * altering an attendance row. Their id is the Driver master primary key.
     */
    private function assumedPresentDrivers(
        string $scheduleDate,
        bool $lockForUpdate = false
    ): Collection {
        $query = Driver::query()
            ->with('authorizedBuses:id')
            ->where('employment_status', 'Active')
            ->orderBy('driver_name');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()->map(function (Driver $driver) use ($scheduleDate): DriverAttendance {
            $candidate = new DriverAttendance([
                'driver_id' => $driver->driver_id,
                'driver_name' => $driver->driver_name,
                'attendance_date' => $scheduleDate,
                'status' => 'Assumed Present',
            ]);
            $candidate->setAttribute('id', $driver->id);
            $candidate->exists = false;
            $candidate->setRelation('driver', $driver);

            return $candidate;
        })->values();
    }

    /**
     * Return a database-compatible time.
     */
    private function databaseTime(
        mixed $time
    ): string {
        if (! $time) {
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
        if (! $time) {
            return '—';
        }

        return date(
            'g:i A',
            strtotime((string) $time)
        );
    }
}
