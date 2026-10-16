<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\IncidentReplacement;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use App\Support\TableSort;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TripScheduleController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private const MIN_ROUTE_DEPARTURE_INTERVAL_MINUTES = 15;

    public function index(Request $request): View
    {
        $query = TripSchedule::query()
            ->with([
                'shuttleRoute',
                'assignments.bus',
                'assignments.driverAttendance',
                'assignment.bus',
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
                            ->orWhere('route_name', 'like', "%{$search}%")
                            ->orWhere('origin', 'like', "%{$search}%")
                            ->orWhere('destination', 'like', "%{$search}%");
                    });
            });
        }

        // Trip Schedule opens on the current operational day by default.
        // Explicit date selections continue to show historical/future trips.
        $request->validate([
            'trip_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
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
        // Schedule History is an all-dates audit by default; an explicit date
        // filter still narrows it. Active planning keeps the selected date.
        if ($request->input('tab') !== 'history' || $request->filled('trip_date')) {
            $query->whereDate('trip_date', $selectedTripDate);
        }

        if (
            $request->filled('route')
            && $request->input('route') !== 'all'
        ) {
            $query->where(
                'shuttle_route_id',
                $request->integer('route')
            );
        }

        $request->validate([
            'direction' => ['sometimes', Rule::in(['all', 'Incoming', 'Outgoing'])],
        ]);

        if ($request->filled('direction') && $request->input('direction') !== 'all') {
            $query->where('direction', $request->input('direction'));
        }

        $request->validate([
            'tab' => ['sometimes', Rule::in(['active', 'history'])],
        ]);
        $historyTab = $request->input('tab') === 'history';
        $query->whereIn('status', $historyTab
            ? ['Completed', 'Delayed', 'Cancelled', 'Missed']
            : ['Scheduled', 'Ready', 'Dispatched']);

        if (
            $request->filled('status')
            && $request->input('status') !== 'all'
        ) {
            $query->where(
                'status',
                $request->input('status')
            );
        }

        TableSort::apply($query, $request, ['departure_time' => 'departure_time', 'trip_code' => 'trip_code', 'trip_date' => 'trip_date', 'required_buses' => 'required_buses', 'status' => 'status'], $historyTab ? 'trip_date' : 'departure_time', $historyTab ? 'desc' : 'asc');
        $trips = $query->get();

        // Read-only reserve deployments: count actual replacement records linked to
        // the filtered schedule rows. A reserve deployment does not increase required buses.
        $reserveDeployedByTrip = IncidentReplacement::query()
            ->whereNotNull('dispatched_at')
            ->whereHas('incident', fn ($query) => $query
                ->whereIn('trip_schedule_id', $trips->pluck('id'))
                ->whereNull('deleted_at'))
            ->with('incident:id,trip_schedule_id')
            ->get()
            ->groupBy(fn (IncidentReplacement $replacement) => $replacement->incident?->trip_schedule_id)
            ->map(fn ($replacements) => $replacements->count());

        $activeRoutes = ShuttleRoute::query()
            ->where('status', 'Active')
            ->orderBy('route_code')
            ->get([
                'id',
                'route_code',
                'route_name',
                'origin',
                'destination',
                'estimated_time_minutes',
                'calculated_time_minutes',
            ]);

        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        $operationalTodayTrips = TripSchedule::query()
            ->whereDate('trip_date', $today)
            ->where('status', '!=', 'Cancelled');

        $totalTripsToday = (clone $operationalTodayTrips)->count();

        $assignedTrips = (clone $operationalTodayTrips)
            ->where(function ($query) {
                $query
                    ->where('assignment_status', 'Assigned')
                    ->orWhereHas('assignment');
            })
            ->count();

        $pendingAssignments = (clone $operationalTodayTrips)
            ->where('assignment_status', 'Unassigned')
            ->whereDoesntHave('assignment')
            ->where('status', 'Scheduled')
            ->count();

        $activeRoutesUsed = (clone $operationalTodayTrips)
            ->distinct()
            ->count('shuttle_route_id');

        // Summary cards reflect the selected operational date, not today's unrelated data.
        $selectedDateTrips = TripSchedule::query()
            ->whereDate('trip_date', $selectedTripDate)
            ->where('status', '!=', 'Cancelled');

        $scheduledServices = (clone $selectedDateTrips)->count();
        $incomingServices = (clone $selectedDateTrips)->where('direction', 'Incoming')->count();
        $outgoingServices = (clone $selectedDateTrips)->where('direction', 'Outgoing')->count();
        $totalBusesRequired = (int) (clone $selectedDateTrips)->sum('required_buses');

        return view(
            'Operation.Scheduling_And_Dispatch.trip-schedule',
            compact(
                'trips',
                'activeRoutes',
                'totalTripsToday',
                'assignedTrips',
                'pendingAssignments',
                'activeRoutesUsed',
                'selectedTripDate',
                'scheduledServices',
                'incomingServices',
                'outgoingServices',
                'totalBusesRequired',
                'reserveDeployedByTrip'
            )
        );
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $validated = $this->validateTrip($request);

            $trip = DB::transaction(function () use ($validated): TripSchedule {
                $route = ShuttleRoute::query()
                    ->whereKey($validated['shuttle_route_id'])
                    ->where('status', 'Active')
                    ->lockForUpdate()
                    ->firstOrFail();

                $departure = $this->departureDateTime(
                    $validated['trip_date'],
                    $validated['departure_time']
                );

                $this->assertMinimumRouteDepartureInterval(
                    $validated['trip_date'],
                    $route,
                    $departure
                );

                $estimatedArrival = $this->resolveArrivalDateTime(
                    $departure,
                    $this->routeDurationMinutes($route)
                );

                $trip = TripSchedule::create([
                    'trip_code' => 'TMP-'.Str::upper(Str::random(20)),
                    'trip_date' => $validated['trip_date'],
                    'shuttle_route_id' => $route->id,
                    'client_name' => $validated['client_name'] ?? null,
                    'direction' => $validated['direction'] ?? null,
                    'required_buses' => $validated['required_buses'] ?? 1,
                    'departure_time' => $departure->format('H:i:s'),
                    'estimated_arrival_time' => $estimatedArrival->format('H:i:s'),
                    'estimated_arrival_date' => $estimatedArrival->toDateString(),
                    'shift' => $this->detectShift($departure),
                    'assignment_status' => 'Unassigned',
                    'status' => 'Scheduled',
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                $trip->update([
                    'trip_code' => $this->tripCodeFromId($trip->id),
                ]);

                return $trip;
            });
        } catch (ValidationException $exception) {
            $this->rememberTripValidation($request, 'create');

            throw $exception;
        }

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'created',
            $trip->id,
            "{$trip->trip_code} was added to Trip Schedule."
        );

        // Show the date just created, including after leaving and returning.
        $request->session()->put('operation.selected_trip_date', $validated['trip_date']);

        session()->flash(
            'success',
            'Trip schedule created successfully.'
        );

        return $this->scheduleReturnRedirect($request);
    }

    public function update(
        Request $request,
        TripSchedule $tripSchedule
    ): RedirectResponse {
        if (! $this->tripCanBeEdited($tripSchedule)) {
            session()->flash(
                'error',
                'Only unassigned Scheduled/Cancelled trips without operational history can be edited.'
            );

            return $this->scheduleReturnRedirect($request);
        }

        try {
            $validated = $this->validateTrip(
                $request,
                $tripSchedule
            );

            $updatedTrip = DB::transaction(
                function () use ($validated, $tripSchedule): TripSchedule {
                    $route = ShuttleRoute::query()
                        ->whereKey($validated['shuttle_route_id'])
                        ->where('status', 'Active')
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedTrip = TripSchedule::query()
                        ->whereKey($tripSchedule->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->assertEditableTripState($lockedTrip);

                    $departure = $this->departureDateTime(
                        $validated['trip_date'],
                        $validated['departure_time']
                    );

                    if ($validated['status'] !== 'Cancelled') {
                        $this->assertMinimumRouteDepartureInterval(
                            $validated['trip_date'],
                            $route,
                            $departure,
                            $lockedTrip->id
                        );
                    }

                    $estimatedArrival = $this->resolveArrivalDateTime(
                        $departure,
                        $this->routeDurationMinutes($route)
                    );

                    $newRequiredBuses = max(1, (int) ($validated['required_buses'] ?? $lockedTrip->required_buses ?? 1));
                    $assignedCount = $lockedTrip->assignments()->count();

                    if ($newRequiredBuses < $assignedCount) {
                        throw ValidationException::withMessages([
                            'required_buses' => "Required buses cannot be reduced below the {$assignedCount} existing assigned bus(es). Remove assignments first.",
                        ]);
                    }

                    $lockedTrip->update([
                        'trip_date' => $validated['trip_date'],
                        'shuttle_route_id' => $route->id,
                        'client_name' => filled($validated['client_name'] ?? null)
                            ? $validated['client_name']
                            : $lockedTrip->client_name,
                        'direction' => $validated['direction'] ?? $lockedTrip->direction,
                        'required_buses' => $newRequiredBuses,
                        'departure_time' => $departure->format('H:i:s'),
                        'estimated_arrival_time' => $estimatedArrival->format('H:i:s'),
                        'estimated_arrival_date' => $estimatedArrival->toDateString(),
                        'shift' => $this->detectShift($departure),
                        'status' => $validated['status'],
                        'notes' => $validated['notes'] ?? null,
                    ]);

                    $lockedTrip->syncAssignmentStatus();

                    return $lockedTrip;
                }
            );
        } catch (ValidationException $exception) {
            if (! array_key_exists('trip_schedule', $exception->errors())) {
                $this->rememberTripValidation(
                    $request,
                    'edit',
                    $tripSchedule->id,
                    $tripSchedule->trip_code
                );
            }

            throw $exception;
        }

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'updated',
            $updatedTrip->id,
            "{$updatedTrip->trip_code} was updated in Trip Schedule."
        );

        session()->flash(
            'success',
            'Trip schedule updated successfully.'
        );

        return $this->scheduleReturnRedirect($request);
    }

    public function destroy(
        Request $request,
        TripSchedule $tripSchedule
    ): RedirectResponse {
        $deleteError = DB::transaction(
            function () use ($tripSchedule): ?string {
                $lockedTrip = TripSchedule::query()
                    ->whereKey($tripSchedule->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedTrip->loadMissing(['assignments', 'assignment']);
                $lockedTrip->loadCount([
                    'dailyDriverReports',
                    'incidents',
                ]);

                if (
                    ! in_array(
                        $lockedTrip->status,
                        ['Scheduled', 'Cancelled'],
                        true
                    )
                ) {
                    return 'Only Scheduled or Cancelled trips may be deleted.';
                }

                if (
                    ! in_array($lockedTrip->assignment_status, ['Unassigned', null], true)
                    || $lockedTrip->assignments()->exists()
                    || $lockedTrip->assignment !== null
                ) {
                    return 'Remove all driver and bus assignments before deleting this trip.';
                }

                if (
                    $lockedTrip->daily_driver_reports_count > 0
                    || $lockedTrip->incidents_count > 0
                ) {
                    return 'This trip already has operational history and cannot be deleted. Cancel it instead.';
                }

                $lockedTrip->delete();

                return null;
            }
        );

        if ($deleteError !== null) {
            session()->flash('error', $deleteError);

            return $this->scheduleReturnRedirect($request);
        }

        $this->broadcastSystemDataUpdated(
            'Operation',
            'TripSchedule',
            'deleted',
            $tripSchedule->id,
            "{$tripSchedule->trip_code} was removed from Trip Schedule."
        );

        session()->flash(
            'success',
            'Trip schedule deleted successfully.'
        );

        return $this->scheduleReturnRedirect($request);
    }

    private function scheduleReturnRedirect(Request $request): RedirectResponse
    {
        $context = $request->validate([
            'return_trip_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'return_search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'return_status' => ['sometimes', Rule::in(['all', 'Scheduled', 'Ready', 'Dispatched', 'Completed', 'Cancelled'])],
        ]);

        $filters = array_filter([
            'trip_date' => $context['return_trip_date'] ?? null,
            'search' => $context['return_search'] ?? null,
            'status' => $context['return_status'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return new RedirectResponse('/operation/trip-schedule'
            .($filters ? '?'.http_build_query($filters) : ''));
    }

    private function validateTrip(
        Request $request,
        ?TripSchedule $tripSchedule = null
    ): array {
        $allowsHistoricalCorrection = $tripSchedule !== null
            && $tripSchedule->hasDeparted()
            && $this->tripCanBeEdited($tripSchedule);

        $tripDateRules = [
            'required',
            'date',
        ];

        if (! $allowsHistoricalCorrection) {
            $tripDateRules[] = 'after_or_equal:'
                .now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        }

        $rules = [
            'trip_date' => $tripDateRules,
            'shuttle_route_id' => [
                'required',
                'integer',
                Rule::exists('shuttle_routes', 'id')
                    ->where('status', 'Active'),
            ],
            'departure_time' => [
                'required',
                'date_format:H:i',
            ],
            'client_name' => ['nullable', 'string', 'max:255'],
            'direction' => [$tripSchedule === null ? 'required' : 'nullable', Rule::in(['Incoming', 'Outgoing'])],
            'required_buses' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];

        if ($tripSchedule !== null) {
            $rules['status'] = [
                'required',
                Rule::in([
                    'Scheduled',
                    'Cancelled',
                ]),
            ];
        }

        $validated = $request->validate($rules);

        $requestedDeparture = $this->departureDateTime(
            $validated['trip_date'],
            $validated['departure_time']
        );

        if (
            $requestedDeparture->lt(now(config('app.business_timezone', 'Asia/Manila'))->startOfMinute())
            && ! $allowsHistoricalCorrection
        ) {
            throw ValidationException::withMessages([
                'departure_time' => 'Trip departure must be the current time or a future time.',
            ]);
        }

        return $validated;
    }

    private function assertMinimumRouteDepartureInterval(
        string $tripDate,
        ShuttleRoute $route,
        Carbon $departure,
        ?int $ignoreTripId = null
    ): void {
        $conflictingTrip = $this->findRouteDepartureConflict(
            $tripDate,
            $route,
            $departure,
            $ignoreTripId
        );

        if ($conflictingTrip === null) {
            return;
        }

        $existingDeparture = Carbon::parse(
            $conflictingTrip->departure_time
        );

        $differenceMinutes = abs(
            $this->minutesFromMidnight($existingDeparture)
            - $this->minutesFromMidnight($departure)
        );

        if ($differenceMinutes === 0) {
            throw ValidationException::withMessages([
                'departure_time' => sprintf(
                    'Duplicate trip: %s is already scheduled on %s at %s.',
                    $route->route_code,
                    Carbon::parse($tripDate)->format('M d, Y'),
                    $departure->format('g:i A')
                ),
            ]);
        }

        throw ValidationException::withMessages([
            'departure_time' => sprintf(
                'Departure too close: %s already has a trip at %s. Keep at least %d minutes between departures for the same route.',
                $route->route_code,
                $existingDeparture->format('g:i A'),
                self::MIN_ROUTE_DEPARTURE_INTERVAL_MINUTES
            ),
        ]);
    }

    private function findRouteDepartureConflict(
        string $tripDate,
        ShuttleRoute $route,
        Carbon $departure,
        ?int $ignoreTripId = null
    ): ?TripSchedule {
        $query = TripSchedule::query()
            ->whereDate('trip_date', $tripDate)
            ->where('shuttle_route_id', $route->id)
            ->where('status', '!=', 'Cancelled')
            ->lockForUpdate();

        if ($ignoreTripId !== null) {
            $query->where('id', '!=', $ignoreTripId);
        }

        $departureMinutes = $this->minutesFromMidnight(
            $departure
        );

        return $query
            ->get([
                'id',
                'departure_time',
            ])
            ->first(
                function (TripSchedule $trip) use ($departureMinutes): bool {
                    $existingDeparture = Carbon::parse(
                        $trip->departure_time
                    );

                    $differenceMinutes = abs(
                        $this->minutesFromMidnight($existingDeparture)
                        - $departureMinutes
                    );

                    return $differenceMinutes
                        < self::MIN_ROUTE_DEPARTURE_INTERVAL_MINUTES;
                }
            );
    }

    private function assertEditableTripState(
        TripSchedule $tripSchedule
    ): void {
        if (! $this->tripCanBeEdited($tripSchedule)) {
            throw ValidationException::withMessages([
                'trip_schedule' => 'This trip is no longer editable because it is assigned, dispatched, completed, or already has operational history.',
            ]);
        }
    }

    private function tripCanBeEdited(
        TripSchedule $tripSchedule
    ): bool {
        return $tripSchedule->canBeManagedFromSchedule();
    }

    private function rememberTripValidation(
        Request $request,
        string $mode,
        ?int $tripId = null,
        ?string $tripCode = null
    ): void {
        session()->flash('trip_validation_mode', $mode);
        session()->flash('trip_validation_id', $tripId);
        session()->flash('trip_validation_code', $tripCode);
        session()->flashInput(
            $request->except([
                '_token',
                '_method',
            ])
        );
    }

    private function departureDateTime(
        string $tripDate,
        string $departureTime
    ): Carbon {
        return Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$tripDate} {$departureTime}",
            config('app.business_timezone', 'Asia/Manila')
        );
    }

    private function resolveArrivalDateTime(
        Carbon $departure,
        int $routeDurationMinutes
    ): Carbon {
        return $departure
            ->copy()
            ->addMinutes(
                max(1, $routeDurationMinutes)
            );
    }

    private function routeDurationMinutes(
        ShuttleRoute $route
    ): int {
        return max(
            1,
            (int) (
                $route->calculated_time_minutes
                ?: $route->estimated_time_minutes
                ?: 60
            )
        );
    }

    private function minutesFromMidnight(
        Carbon $time
    ): int {
        return (
            ((int) $time->format('H')) * 60
        ) + (int) $time->format('i');
    }

    private function tripCodeFromId(
        int $tripId
    ): string {
        return 'T-'.str_pad(
            (string) $tripId,
            3,
            '0',
            STR_PAD_LEFT
        );
    }

    private function detectShift(Carbon $departure): string
    {
        return TripSchedule::shiftForDeparture($departure);
    }
}
