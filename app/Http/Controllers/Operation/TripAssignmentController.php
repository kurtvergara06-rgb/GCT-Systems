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
use Illuminate\Pagination\LengthAwarePaginator;
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
        // Keep assigned trips visible while awaiting physical DDRs, even after ETA.
        // ETA is only a planning estimate and must never retire an assignment.
        // Historical Completed/Cancelled/Missed trips remain in Trip Records.
        if ($request->input('status') !== 'Missed') {
            $query->whereNotIn('status', ['Completed', 'Cancelled', 'Missed'])
                ->where(function ($active) use ($endExpression, $nowTimestamp): void {
                    $active->whereRaw("($endExpression) > ?", [$nowTimestamp])
                        ->orWhere(function ($awaitingDdr): void {
                            $awaitingDdr->whereIn('status', ['Ready', 'Dispatched', 'Pending DDR', 'Delayed'])
                                ->whereHas('assignments');
                        });
                });
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

        // Calendar dots cover every stored trip status; this page intentionally
        // displays only active/manageable assignments. Explain dates with
        // historical trips rather than presenting a misleading empty table.
        $allTripsForDate = TripSchedule::query()
            ->whereDate('trip_date', $selectedTripDate)
            ->count();
        $hiddenTripsForDate = max(0, $allTripsForDate - $trips->total());

        $replacementHistoryByAssignment = DB::table('operation_trip_change_audits as audit')
            ->leftJoin('users as approver', 'approver.id', '=', 'audit.performed_by')
            ->whereIn('audit.trip_schedule_id', $trips->getCollection()->pluck('id'))
            ->where('audit.action', 'ready_replacement')
            ->select('audit.*', 'approver.name as approved_by_name')
            ->orderByDesc('audit.created_at')
            ->get()
            ->groupBy('trip_assignment_id');

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

        // Attendance is confirmed after DDR submission, not before scheduling.
        $availableDrivers = Driver::query()
            ->with('authorizedBuses')
            ->where('employment_status', 'Active')
            ->where(function ($licenseQuery) use ($selectedTripDate): void {
                $licenseQuery->whereNull('license_expiration')
                    ->orWhereDate('license_expiration', '>=', $selectedTripDate);
            })
            ->orderBy('driver_name')
            ->paginate(10, ['*'], 'driver_page')
            ->withQueryString();

        // Body numbers must be ordered naturally (2, 9, 10, 20), not
        // lexicographically (10, 2, 20, 9). Sort before paginating so a
        // smaller body number never appears on a later page.
        $orderedEligibleBuses = Bus::query()
            ->where('status', 'Active')
            ->where('fleet_purpose', 'Regular')
            ->whereNotIn('bus_no', JobOrder::query()->blocksDispatch()->select('bus_no'))
            ->get()
            ->sort(fn (Bus $a, Bus $b): int =>
                (($a->fleet_purpose === 'Reserve' ? 1 : 0) <=> ($b->fleet_purpose === 'Reserve' ? 1 : 0))
                ?: strnatcasecmp((string) $a->bus_no, (string) $b->bus_no)
                ?: ($a->id <=> $b->id))
            ->values();
        $busPage = LengthAwarePaginator::resolveCurrentPage('bus_page');
        $availableBuses = (new LengthAwarePaginator(
            $orderedEligibleBuses->forPage($busPage, 10)->values(),
            $orderedEligibleBuses->count(),
            10,
            $busPage,
            ['pageName' => 'bus_page', 'path' => $request->url()]
        ))->withQueryString();

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
                'replacementHistoryByAssignment',
                'scheduledTripsForDate',
                'pendingAssignments',
                'availableDrivers',
                'availableBuses',
                'unassignedTrips',
                'selectedTripDate',
                'allTripsForDate',
                'hiddenTripsForDate'
            )
        );
    }

    /**
     * Month-level assignment overview for the date picker.
     * Uses the stored assignment state and counts each trip once.
     * Operational statuses take precedence over assignment indicators.
     */
    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $start = \Carbon\Carbon::createFromFormat('!Y-m', $validated['month'])
            ->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rows = TripSchedule::query()
            ->whereBetween('trip_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('trip_date, status, assignment_status, COUNT(*) as trip_count')
            ->groupBy('trip_date', 'status', 'assignment_status')
            ->get();

        $dates = [];
        foreach ($rows as $row) {
            $date = $row->trip_date->toDateString();
            $status = match ($row->status) {
                'Missed' => 'missed',
                'Completed' => 'completed',
                'Cancelled', 'Canceled' => 'cancelled',
                'Dispatched', 'In Progress' => 'in_progress',
                'Pending DDR' => 'pending_ddr',
                'Delayed' => 'delayed',
                'Scheduled', 'Ready' => match ($row->assignment_status) {
                    'Assigned' => 'assigned',
                    'Partially Assigned' => 'partial',
                    default => 'unassigned',
                },
                default => 'history',
            };
            $count = (int) $row->trip_count;
            if (! isset($dates[$date])) {
                $dates[$date] = ['total' => 0, 'statuses' => []];
            }
            $dates[$date]['total'] += $count;
            $dates[$date]['statuses'][$status] = ($dates[$date]['statuses'][$status] ?? 0) + $count;
        }

        return response()->json([
            'month' => $validated['month'],
            'dates' => $dates,
        ]);
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
            ->with(['bus', 'reserveApproval', 'incidents.replacement', 'tripSchedule.shuttleRoute'])
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
        if ($ignoreAssignmentId
            && ! $tripSchedule->assignments()->whereKey($ignoreAssignmentId)->exists()) {
            throw ValidationException::withMessages([
                'assignment_id' => 'The assignment does not belong to this trip.',
            ]);
        }

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

        $drivers = Driver::query()
            ->with('authorizedBuses')
            ->where('employment_status', 'Active')
            ->where(function ($query) use ($tripSchedule): void {
                $query->whereNull('license_expiration')
                    ->orWhereDate('license_expiration', '>=', $tripSchedule->trip_date);
            })
            ->when($busyDriverIds !== [], fn ($query) => $query->whereNotIn('driver_id', $busyDriverIds))
            ->orderBy('driver_name')
            ->get()
            ->map(fn (Driver $driver) => [
                'id' => $driver->driver_id,
                'driver_id' => $driver->driver_id,
                'name' => $driver->driver_name,
                'status' => 'Assumed Available',
                'authorized_bus_ids' => $driver->authorizedBuses->pluck('id')->all(),
            ])
            ->values();

        $tripClient = mb_strtolower(trim((string) ($tripSchedule->client_name ?? '')));

        $canSelectReserve = auth()->user()?->canApproveReadyTripChanges() ?? false;

        $buses = Bus::query()
            ->with('operationClients:id,name')
            ->where('status', 'Active')
            ->whereIn('fleet_purpose', $canSelectReserve ? ['Regular', 'Reserve'] : ['Regular'])
            ->whereNotIn('bus_no', JobOrder::query()->blocksDispatch()->select('bus_no'))
            ->when(
                $busyBusIds !== [],
                fn ($query) => $query->whereNotIn('id', $busyBusIds)
            )
            ->get()
            ->filter(function (Bus $bus) use ($tripClient): bool {
                $allocations = $bus->operationClients
                    ->pluck('name')
                    ->map(fn ($name) => mb_strtolower(trim((string) $name)));

                if ($bus->fleet_purpose === 'Reserve') {
                    return true;
                }

                return $tripClient === ''
                    ? $allocations->isEmpty()
                    : $allocations->contains($tripClient);
            })
            ->sort(fn (Bus $a, Bus $b): int =>
                strnatcasecmp((string) $a->bus_no, (string) $b->bus_no)
                ?: ($a->id <=> $b->id))
            ->values()
            ->map(fn (Bus $bus) => [
                'id' => $bus->id,
                'bus_no' => $bus->bus_no,
                'model' => $bus->bus_model,
                'plate_no' => $bus->plate_no,
                'purpose' => $bus->fleet_purpose,
            ]);

        // Reserve buses have no permanently authorized drivers. They are
        // offered only when no valid Regular driver/bus pair is available.
        // Server-side write validation independently enforces this condition.
        $hasRegularPair = $buses->contains(function ($bus) use ($drivers): bool {
            if ($bus['purpose'] !== 'Regular') {
                return false;
            }

            return $drivers->contains(fn ($driver) =>
                in_array($bus['id'], $driver['authorized_bus_ids'], true));
        });
        if ($hasRegularPair) {
            $buses = $buses->filter(fn ($bus) => $bus['purpose'] !== 'Reserve')->values();
        }

        return response()->json([
            'trip' => [
                'id' => $tripSchedule->id,
                'date' => $tripSchedule->trip_date?->toDateString(),
                'shift' => $tripSchedule->shift,
                'trip_code' => $tripSchedule->trip_code,
                'route_code' => $tripSchedule->shuttleRoute?->route_code,
                'client_name' => $tripSchedule->client_name,
                'direction' => $tripSchedule->direction,
                'departure_time' => $tripSchedule->departure_time,
                'required_buses' => max(1, (int) ($tripSchedule->required_buses ?? 1)),
                'assigned_slots' => $assignedToThisTrip->count(),
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
                    $validated['driver_attendance_id'] ?? null,
                    $trip,
                    $validated['driver_id'] ?? null
                );

                $bus = $this->lockEligibleBus($validated['bus_id']);
                $this->assertDriverAuthorizedForBus($driver->driver_id, $bus->id);
                $this->assertBusAllocatedToTripClient($trip, $bus);
                if ($bus->fleet_purpose === 'Reserve') {
                    $this->assertReserveAssignmentAllowed($trip);
                }

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
                    'driver_attendance_id' => $driver->id ?: null,
                    'driver_id' => $driver->driver_id,
                    'driver_name' => $driver->driver_name,
                    'bus_id' => $bus->id,
                    'assigned_by' => auth()->id(),
                ]);

                if ($bus->fleet_purpose === 'Reserve') {
                    $this->recordReserveApproval($trip, $assignment, $bus);
                }

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
        $request->validate([
            'replacement_reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ]);

        $trip = DB::transaction(
            function () use ($validated, $tripAssignment, $request): TripSchedule {
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
                $isReadyReplacement = $trip->status === 'Ready';
                $replacementReason = trim((string) $request->input('replacement_reason', ''));
                if ($isReadyReplacement) {
                    if (! (auth()->user()?->canApproveReadyTripChanges() ?? false)) {
                        throw ValidationException::withMessages([
                            'trip_schedule_id' => 'Only the Operations Head may approve a Ready-trip replacement.',
                        ]);
                    }
                    if (mb_strlen($replacementReason) < 10 || mb_strlen($replacementReason) > 1000) {
                        throw ValidationException::withMessages([
                            'replacement_reason' => 'Provide a replacement reason between 10 and 1000 characters.',
                        ]);
                    }
                }
                $this->assertRouteIsActive($trip);

                $driver = $this->lockEligibleDriver(
                    $validated['driver_attendance_id'] ?? null,
                    $trip,
                    $validated['driver_id'] ?? null
                );

                $bus = $this->lockEligibleBus($validated['bus_id']);
                $this->assertDriverAuthorizedForBus($driver->driver_id, $bus->id);
                $this->assertBusAllocatedToTripClient($trip, $bus);
                if ($bus->fleet_purpose === 'Reserve') {
                    $this->assertReserveAssignmentAllowed($trip, $lockedAssignment->id);
                }

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

                if ($isReadyReplacement) {
                    if ((string) $lockedAssignment->driver_id === (string) $driver->driver_id
                        && (int) $lockedAssignment->bus_id === (int) $bus->id) {
                        throw ValidationException::withMessages([
                            'trip_schedule_id' => 'Select a different driver or bus for the replacement.',
                        ]);
                    }
                    DB::table('operation_trip_change_audits')->insert([
                        'trip_schedule_id' => $trip->id,
                        'trip_assignment_id' => $lockedAssignment->id,
                        'performed_by' => auth()->id(),
                        'action' => 'ready_replacement',
                        'previous_status' => $trip->status,
                        'new_status' => 'Ready',
                        'previous_driver_id' => $lockedAssignment->driver_id,
                        'new_driver_id' => $driver->driver_id,
                        'previous_driver_name' => $lockedAssignment->driver_name,
                        'new_driver_name' => $driver->driver_name,
                        'previous_bus_id' => $lockedAssignment->bus_id,
                        'new_bus_id' => $bus->id,
                        'previous_bus_label' => $lockedAssignment->bus?->bus_no,
                        'new_bus_label' => $bus->bus_no,
                        'reason' => $replacementReason,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Preserve old Reserve approvals as historical records when a
                // reserve bus is replaced, rather than attaching the approval
                // to an unrelated newly assigned vehicle.
                $reserveChanged = (int) $lockedAssignment->bus_id !== (int) $bus->id;
                if ($reserveChanged) {
                    DB::table('auto_schedule_reserve_approvals')
                        ->where('trip_assignment_id', $lockedAssignment->id)
                        ->update(['trip_assignment_id' => null, 'updated_at' => now()]);
                }

                $lockedAssignment->update([
                    'driver_attendance_id' => $driver->id ?: null,
                    'driver_id' => $driver->driver_id,
                    'driver_name' => $driver->driver_name,
                    'bus_id' => $bus->id,
                ]);

                if ($bus->fleet_purpose === 'Reserve'
                    && ($reserveChanged || ! $lockedAssignment->reserveApproval()->exists())) {
                    $this->recordReserveApproval($trip, $lockedAssignment, $bus);
                }

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
            $trip->status === 'Ready'
                ? 'Ready-trip replacement approved and recorded successfully.'
                : 'Assignment updated successfully.'
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
                if ($trip->status === 'Ready') {
                    throw ValidationException::withMessages([
                        'trip_schedule_id' => 'Ready-trip assignments cannot be removed directly. Use an approved replacement or cancel the trip before departure.',
                    ]);
                }

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
        ?int $attendanceId,
        TripSchedule $trip,
        ?string $driverId = null
    ): DriverAttendance {
        $attendance = $attendanceId
            ? DriverAttendance::query()
                ->whereKey($attendanceId)
                ->whereDate('attendance_date', $trip->trip_date)
                ->lockForUpdate()
                ->first()
            : null;

        if ($attendanceId && ! $attendance) {
            throw ValidationException::withMessages([
                'driver_attendance_id' => 'The selected attendance record does not match this trip date.',
            ]);
        }

        $resolvedDriverId = $driverId ?: $attendance?->driver_id;
        $activeDriver = $resolvedDriverId
            ? Driver::query()
                ->where('driver_id', $resolvedDriverId)
                ->where('employment_status', 'Active')
                ->lockForUpdate()
                ->first()
            : null;

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

        if ($attendance) {
            return $attendance;
        }

        // Planning does not establish actual attendance. Only a valid DDR
        // records Present; manual assignment may use the Active Driver master.
        $candidate = new DriverAttendance([
            'driver_id' => $activeDriver->driver_id,
            'driver_name' => $activeDriver->driver_name,
            'status' => 'Assumed Present',
            'attendance_date' => $trip->trip_date,
        ]);
        $candidate->id = null;

        return $candidate;
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

    private function assertBusAllocatedToTripClient(TripSchedule $trip, Bus $bus): void
    {
        if ($bus->fleet_purpose === 'Reserve') {
            return;
        }

        $client = trim((string) ($trip->client_name ?? ''));
        $allocations = $bus->operationClients()->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)));

        $compatible = $client === ''
            ? $allocations->isEmpty()
            : $allocations->contains(mb_strtolower($client));

        if (! $compatible) {
            throw ValidationException::withMessages([
                'bus_id' => 'The bus allocation does not match this trip client. Select a compatible bus.',
            ]);
        }
    }

    private function assertDriverAuthorizedForBus(string $driverId, int $busId): void
    {
        // Reserve fleet units are shared standby assets and intentionally
        // have no driver authorization pivots. Other driver eligibility checks
        // (active licence, overlaps, attendance where applicable) still apply.
        if (Bus::query()->whereKey($busId)->where('fleet_purpose', 'Reserve')->exists()) {
            return;
        }

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

    /**
     * Reserve buses belong to the shared fleet; only the Operations Head may
     * deploy them and only when no eligible Regular driver/bus pair remains.
     */
    private function assertReserveAssignmentAllowed(TripSchedule $trip, ?int $ignoreAssignmentId = null): void
    {
        if (! (auth()->user()?->canApproveReadyTripChanges() ?? false)) {
            throw ValidationException::withMessages([
                'bus_id' => 'Only the Operations Head can deploy a Reserve bus.',
            ]);
        }

        $existing = $trip->assignments()
            ->when($ignoreAssignmentId, fn ($q) => $q->where('id', '!=', $ignoreAssignmentId))
            ->get();
        $conflicts = $this->conflictingAssignmentsForTrip($trip, $ignoreAssignmentId);
        $busyDriverIds = $existing->pluck('driver_id')
            ->merge($conflicts->pluck('driver_id'))->filter()->unique()->all();
        $busyBusIds = $existing->pluck('bus_id')
            ->merge($conflicts->pluck('bus_id'))->filter()->unique()->all();

        $client = mb_strtolower(trim((string) ($trip->client_name ?? '')));
        $regularBuses = Bus::query()
            ->with('operationClients:id,name')
            ->where('status', 'Active')
            ->where('fleet_purpose', 'Regular')
            ->whereNotIn('bus_no', JobOrder::query()->blocksDispatch()->select('bus_no'))
            ->when($busyBusIds !== [], fn ($q) => $q->whereNotIn('id', $busyBusIds))
            ->get()
            ->filter(function (Bus $bus) use ($client): bool {
                $names = $bus->operationClients->pluck('name')
                    ->map(fn ($name) => mb_strtolower(trim((string) $name)));

                return $client === '' ? $names->isEmpty() : $names->contains($client);
            });

        foreach ($regularBuses as $regularBus) {
            if (Driver::query()
                ->where('employment_status', 'Active')
                ->where(fn ($q) => $q->whereNull('license_expiration')
                    ->orWhereDate('license_expiration', '>=', $trip->trip_date))
                ->when($busyDriverIds !== [], fn ($q) => $q->whereNotIn('driver_id', $busyDriverIds))
                ->whereHas('authorizedBuses', fn ($q) => $q->whereKey($regularBus->id))
                ->exists()) {
                throw ValidationException::withMessages([
                    'bus_id' => 'An eligible Regular driver and bus pair is available. Use it before deploying a Reserve bus.',
                ]);
            }
        }
    }

    private function recordReserveApproval(TripSchedule $trip, TripAssignment $assignment, Bus $bus): void
    {
        DB::table('auto_schedule_reserve_approvals')->insert([
            'trip_assignment_id' => $assignment->id,
            'trip_schedule_id' => $trip->id,
            'bus_id' => $bus->id,
            'trip_code' => $trip->trip_code,
            'bus_no' => $bus->bus_no,
            'recorded_by' => auth()->id(),
            'approved_by' => 'Operations Head (manual assignment)',
            'approval_method' => 'Direct',
            'approved_at' => now(),
            'remarks' => 'Direct Reserve deployment approved by the Operations Head after checking Regular pairs.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function lockEligibleBus(int $busId): Bus
    {
        $bus = Bus::query()
            ->whereKey($busId)
            ->where('status', 'Active')
            ->whereIn('fleet_purpose', ['Regular', 'Reserve'])
            ->whereNotIn('bus_no', JobOrder::query()->blocksDispatch()->select('bus_no'))
            ->lockForUpdate()
            ->first();

        if (! $bus) {
            throw ValidationException::withMessages([
                'bus_id' => 'The selected bus is inactive or unavailable.',
            ]);
        }

        if (JobOrder::query()->blocksDispatch()
            ->where('bus_no', $bus->bus_no)
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
                'nullable',
                'required_without:driver_id',
                'integer',
                Rule::exists('driver_attendances', 'id'),
            ],
            'driver_id' => ['nullable', 'required_without:driver_attendance_id', 'string', Rule::exists('drivers', 'driver_id')],
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
