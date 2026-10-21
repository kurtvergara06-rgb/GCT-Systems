<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\IncidentReplacement;
use App\Models\Operation\IncidentResponse;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Services\Operation\OperationNumberSequenceService;
use App\Support\TableSort;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IncidentController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function __construct(
        private readonly OperationNumberSequenceService $numberSequenceService
    ) {}

    public function index(Request $request): View
    {
        $query = Incident::query()
            ->with([
                'tripSchedule.shuttleRoute',
                'bus',
                'reporter',
                'maintenanceReferral',
                'replacement.originalBus',
                'replacement.replacementBus',
                'responses.responder',
            ]);

        // The active roster contains every non-terminal incident, including dispatch.
        // Historical records remain searchable without changing their workflow status.
        $tab = $request->query('tab') === 'history' ? 'history' : 'active';
        if ($tab === 'history') {
            $query->whereIn('status', ['Resolved', 'Cancelled']);
        } else {
            $query->whereNotIn('status', ['Resolved', 'Cancelled']);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('incident_no', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('driver_id', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('incident_type', 'like', "%{$search}%")
                    ->orWhereHas('bus', function ($busQuery) use ($search) {
                        $busQuery->where('bus_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('tripSchedule', function ($tripQuery) use ($search) {
                        $tripQuery->where('trip_code', 'like', "%{$search}%");
                    });
            });
        }

        if (
            $request->filled('status')
            && $request->input('status') !== 'all'
        ) {
            $query->where('status', $request->input('status'));
        }

        if (
            $request->filled('type')
            && $request->input('type') !== 'all'
        ) {
            $query->where('incident_type', $request->input('type'));
        }

        TableSort::apply($query, $request, ['incident_reported_at' => 'incident_reported_at', 'incident_no' => 'incident_no', 'incident_type' => 'incident_type', 'status' => 'status'], 'incident_reported_at', 'desc');
        $incidents = $query
            ->paginate(10, ['*'], 'incident_page')
            ->withQueryString();

        $totalIncidents = Incident::query()->count();
        $activeIncidents = Incident::query()
            ->whereIn('status', ['Reported', 'Monitoring', 'Responding'])
            ->count();
        $breakdownIncidents = Incident::query()
            ->where('incident_type', 'Bus Breakdown')
            ->whereIn('status', ['Reported', 'Monitoring', 'Responding'])
            ->count();
        $resolvedToday = Incident::query()
            ->whereDate('resolved_at', now()->toDateString())
            ->count();

        return view(
            'Operation.Incidents.index',
            array_merge(
                compact(
                    'incidents',
                    'totalIncidents',
                    'activeIncidents',
                    'breakdownIncidents',
                    'resolvedToday',
                    'tab'
                ),
                $this->incidentFormData($request)
            )
        );
    }

    public function create(Request $request): View
    {
        return view(
            'Operation.Incidents.create',
            $this->incidentFormData($request)
        );
    }

    private function incidentFormData(Request $request): array
    {
        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $activeTripAssignment = null;
        $tripSchedule = null;
        $tripAssignmentId = $request->input('trip_assignment_id');

        $activeTripScope = function ($tripQuery) use ($today): void {
            $tripQuery
                ->whereDate('trip_date', $today)
                ->where(function ($statusQuery): void {
                    $statusQuery
                        ->whereIn('status', ['Ready', 'Dispatched'])
                        ->orWhere(function ($assignedScheduled): void {
                            $assignedScheduled
                                ->where('status', 'Scheduled')
                                ->where('assignment_status', 'Assigned');
                        });
                });
        };

        if ($tripAssignmentId) {
            $activeTripAssignment = TripAssignment::query()
                ->with(['tripSchedule.shuttleRoute', 'bus'])
                ->where('id', $tripAssignmentId)
                ->whereHas('tripSchedule', $activeTripScope)
                ->whereHas('bus', fn ($query) => $query->where('status', 'Active'))
                ->first();

            if ($activeTripAssignment) {
                $tripSchedule = $activeTripAssignment->tripSchedule;
            }
        }

        $myAssignments = TripAssignment::query()
            ->with(['tripSchedule.shuttleRoute', 'bus'])
            ->whereHas('tripSchedule', $activeTripScope)
            ->whereHas('bus', fn ($query) => $query->where('status', 'Active'))
            ->orderByDesc('id')
            ->get();

        // Keep every Ready-trip slot, not just the first assignment per trip.
        $availableTrips = $myAssignments;

        // The normal Report Incident form is scoped to buses assigned to Ready trips.
        $assignedBusIds = $myAssignments->pluck('bus_id')->unique();
        $incidentBuses = Bus::query()->whereIn('id', $assignedBusIds)
            ->where('status', 'Active')
            ->orderBy('bus_no')->get(['id', 'bus_no', 'plate_no', 'status']);

        $activeDrivers = Driver::query()->where('employment_status', 'Active')
            ->orderBy('driver_name')->get(['driver_id', 'driver_name']);

        return compact(
            'incidentBuses',
            'activeDrivers',
            'activeTripAssignment',
            'tripSchedule',
            'myAssignments',
            'availableTrips'
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trip_schedule_id' => [
                'nullable',
                'integer',
                Rule::exists('trip_schedules', 'id'),
            ],
            'bus_id' => [
                'nullable',
                'integer',
                Rule::exists('buses', 'id'),
            ],
            'driver_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'driver_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'incident_type' => [
                'required',
                'string',
                Rule::in([
                    'Bus Breakdown',
                    'Accident/Road Incident',
                ]),
            ],
            'location' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'trip_assignment_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $tripAssignment = null;

        if ($validated['incident_type'] === 'Bus Breakdown') {
            if (! empty($validated['trip_schedule_id']) && ! empty($validated['bus_id'])) {
                $checkBreakdownKey = 'trip:'.$validated['trip_schedule_id'].':bus:'.$validated['bus_id'];
            } elseif (! empty($validated['trip_schedule_id'])) {
                $checkBreakdownKey = 'trip:'.$validated['trip_schedule_id'];
            } elseif (! empty($validated['bus_id'])) {
                $checkBreakdownKey = 'bus:'.$validated['bus_id'];
            } else {
                $checkBreakdownKey = null;
            }

            if ($checkBreakdownKey && Incident::query()
                ->where('active_breakdown_key', $checkBreakdownKey)
                ->exists()) {
                throw ValidationException::withMessages([
                    'incident_type' => 'An active breakdown incident already exists for this trip or bus.',
                ]);
            }
        }

        // Restrict newly submitted incident reports to active Ready/Dispatched trip assignments.
        $reportDate = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $reportTripScope = function ($tripQuery) use ($reportDate): void {
            $tripQuery
                ->whereDate('trip_date', $reportDate)
                ->where(function ($statusQuery): void {
                    $statusQuery
                        ->whereIn('status', ['Ready', 'Dispatched'])
                        ->orWhere(function ($assignedScheduled): void {
                            $assignedScheduled
                                ->where('status', 'Scheduled')
                                ->where('assignment_status', 'Assigned');
                        });
                });
        };

        $eligibleAssignment = TripAssignment::query()
            ->where('bus_id', $validated['bus_id'] ?? 0)
            ->whereHas('bus', fn ($query) => $query->where('status', 'Active'))
            ->whereHas('tripSchedule', $reportTripScope);
        if (! $eligibleAssignment->exists()) {
            throw ValidationException::withMessages([
                'bus_id' => 'Choose a bus assigned to a Ready trip today.',
            ]);
        }

        // Incident bus search must select an actual Bus Master List record.
        // A schedule is optional: emergencies may occur without an assignment.
        if ($request->boolean('bus_lookup_required')) {
            $bus = Bus::query()->find($validated['bus_id'] ?? 0);
            $typed = trim((string) $request->input('bus_lookup_display', ''));
            if (! $bus || $typed === '' || (
                strcasecmp($typed, $bus->bus_no) !== 0
                && strcasecmp($typed, (string) $bus->plate_no) !== 0
            )) {
                throw ValidationException::withMessages([
                    'bus_id' => 'Select a valid Bus ID from the suggestions.',
                ]);
            }

            if (! empty($validated['trip_assignment_id'])) {
                $assignment = TripAssignment::query()
                    ->whereKey($validated['trip_assignment_id'])
                    ->where('bus_id', $bus->id)
                    ->where('trip_schedule_id', $validated['trip_schedule_id'] ?? 0)
                    ->whereHas('tripSchedule', $reportTripScope)
                    ->first();
                if (! $assignment) {
                    throw ValidationException::withMessages([
                        'trip_schedule_id' => 'The selected trip does not match this Bus ID.',
                    ]);
                }
            }
        }

        if (! empty($validated['trip_assignment_id'])) {
            $tripAssignment = TripAssignment::query()
                ->with(['tripSchedule', 'bus'])
                ->where('id', $validated['trip_assignment_id'])
                ->where('bus_id', $validated['bus_id'] ?? 0)
                ->whereHas('tripSchedule', $reportTripScope)
                ->first();

            if (! $tripAssignment) {
                throw ValidationException::withMessages([
                    'trip_assignment_id' => 'The selected trip assignment is no longer available.',
                ]);
            }

            $validated['trip_schedule_id'] = $tripAssignment->trip_schedule_id;
            $validated['bus_id'] = $tripAssignment->bus_id;
            $validated['driver_id'] = $tripAssignment->driver_id;
            $validated['driver_name'] = $tripAssignment->driver_name;
        } elseif (! empty($validated['trip_schedule_id'])) {
            $tripAssignment = null;
            if (! empty($validated['bus_id'])) {
                $tripAssignment = TripAssignment::query()
                    ->with(['tripSchedule', 'bus'])
                    ->where('trip_schedule_id', $validated['trip_schedule_id'])
                    ->where('bus_id', $validated['bus_id'])
                    ->whereHas('tripSchedule', $reportTripScope)
                    ->first();
            }

            // Never silently replace the reported bus with another slot's bus.
            // Multiple bus slots can belong to the same trip.
            if (! $tripAssignment) {
                throw ValidationException::withMessages([
                    'trip_schedule_id' => 'Select the exact bus assignment for this trip. The reported bus does not match any assigned slot.',
                ]);
            }

            $validated['bus_id'] = $tripAssignment->bus_id;
            $validated['driver_id'] = $tripAssignment->driver_id;
            $validated['driver_name'] = $tripAssignment->driver_name;
        }

        // Only a master-listed driver can be selected; names are sourced from the
        // master list rather than trusting an arbitrary browser-supplied name.
        if (! empty($validated['driver_id']) && ! $tripAssignment) {
            $driver = Driver::query()->where('driver_id', $validated['driver_id'])
                ->where('employment_status', 'Active')->first();
            if (! $driver) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Please select an active driver from the Driver Master List.',
                ]);
            }
            $validated['driver_name'] = $driver->driver_name;
        }

        $assignmentId = $tripAssignment?->id ?: ($validated['trip_assignment_id'] ?? null);
        unset($validated['trip_assignment_id']);

        $existingMaximum = Incident::withTrashed()
            ->selectRaw('MAX(CAST(RIGHT(incident_no, 5) AS UNSIGNED)) AS maximum')
            ->value('maximum');
        $nextNumber = $this->numberSequenceService->next('incident', (int) $existingMaximum);
        $incidentNo = 'INC-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($validated, $incidentNo, $assignmentId): void {
            if ($validated['incident_type'] === 'Bus Breakdown') {
                if (! empty($validated['trip_schedule_id']) && ! empty($validated['bus_id'])) {
                    $activeBreakdownKey = 'trip:'.$validated['trip_schedule_id'].':bus:'.$validated['bus_id'];
                } elseif (! empty($validated['trip_schedule_id'])) {
                    $activeBreakdownKey = 'trip:'.$validated['trip_schedule_id'];
                } elseif (! empty($validated['bus_id'])) {
                    $activeBreakdownKey = 'bus:'.$validated['bus_id'];
                } else {
                    $activeBreakdownKey = null;
                }

                if ($activeBreakdownKey && Incident::query()
                    ->where('active_breakdown_key', $activeBreakdownKey)
                    ->lockForUpdate()
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'incident_type' => 'An active breakdown incident already exists for this trip or bus.',
                    ]);
                }
            }

            $incident = Incident::create([
                'incident_no' => $incidentNo,
                'trip_schedule_id' => $validated['trip_schedule_id'] ?? null,
                'trip_assignment_id' => $assignmentId,
                'bus_id' => $validated['bus_id'] ?? null,
                'driver_id' => $validated['driver_id'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'incident_type' => $validated['incident_type'],
                'active_breakdown_key' => $activeBreakdownKey ?? null,
                'location' => $validated['location'],
                'description' => $validated['description'] ?? null,
                'incident_reported_at' => now(),
                'status' => 'Reported',
                'reported_by' => auth()->id(),
            ]);

            if (Schema::hasTable('incident_responses')) {
                IncidentResponse::create([
                    'incident_id' => $incident->id,
                    'status' => 'Reported',
                    'notes' => 'Incident reported by driver.',
                    'responded_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
        }, 5);

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Incident',
            'created',
            $incidentNo,
            "New incident {$incidentNo} reported: {$validated['incident_type']}"
        );

        if ($request->boolean('incident_modal')) {
            return redirect()
                ->route('incidents', $request->only(['search', 'status', 'type']))
                ->with(
                    'success',
                    "Incident {$incidentNo} has been reported successfully."
                );
        }

        if (! empty($validated['trip_schedule_id'])) {
            return redirect()
                ->route('incidents.show', ['incident' => $incidentNo])
                ->with(
                    'success',
                    "Incident {$incidentNo} has been reported successfully."
                );
        }

        return redirect()
            ->route('incidents.show', ['incident' => $incidentNo])
            ->with(
                'success',
                "Incident {$incidentNo} has been reported successfully."
            );
    }

    public function show(Incident $incident): View|RedirectResponse
    {
        // Full-page incident details are legacy. Open the existing workflow modal
        // on Incident Management; embedded iframe requests still render the workflow.
        if (! request()->boolean('embedded')) {
            return redirect()->route('incidents', ['view_incident' => $incident->incident_no]);
        }

        $incident->load([
            'tripSchedule.shuttleRoute',
            'tripAssignment.driverAttendance',
            'bus',
            'replacement.originalBus',
            'replacement.replacementBus',
            'replacement.dispatcher',
            'reporter',
            'resolver',
            'responses.responder',
        ]);

        $busyBusIds = [];
        $busyDriverIds = [];
        $eligibleReplacementDrivers = collect();

        if ($incident->trip_schedule_id && $incident->tripSchedule) {
            $tripStart = $incident->tripSchedule->departureDateTime();
            $tripEnd = $incident->tripSchedule->estimatedArrivalDateTime();
            $affectedAssignmentId = $incident->trip_assignment_id;

            $overlappingAssignments = TripAssignment::query()
                ->with('tripSchedule')
                ->whereHas('tripSchedule', function ($tripQuery) use ($tripStart, $tripEnd): void {
                    $tripQuery
                        ->whereNotIn('status', ['Cancelled', 'Completed', 'Missed'])
                        ->whereBetween('trip_date', [
                            $tripStart->copy()->subDay()->toDateString(),
                            $tripEnd->toDateString(),
                        ]);
                })
                ->get()
                ->filter(function (TripAssignment $assignment) use ($incident, $tripStart, $tripEnd): bool {
                    $otherTrip = $assignment->tripSchedule;
                    if (! $otherTrip) {
                        return false;
                    }
                    if ((int) $otherTrip->id === (int) $incident->trip_schedule_id) {
                        return true;
                    }

                    return $tripStart->lt($otherTrip->estimatedArrivalDateTime())
                        && $tripEnd->gt($otherTrip->departureDateTime());
                });

            $busyBusIds = $overlappingAssignments
                ->pluck('bus_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $busyDriverIds = $overlappingAssignments
                ->when($affectedAssignmentId, fn ($collection) => $collection->where('id', '!=', $affectedAssignmentId))
                ->pluck('driver_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $tripDate = $incident->tripSchedule->trip_date?->toDateString()
                ?: now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

            $eligibleReplacementDrivers = Driver::query()
                ->where('employment_status', 'Active')
                ->where(function ($query) use ($tripDate): void {
                    $query->whereNull('license_expiration')
                        ->orWhereDate('license_expiration', '>=', $tripDate);
                })
                ->when($busyDriverIds !== [], fn ($query) => $query->whereNotIn('driver_id', $busyDriverIds))
                ->orderBy('driver_name')
                ->get();
        }

        $activeBuses = Bus::query()
            ->where('status', 'Active')
            ->where('fleet_purpose', 'Reserve')
            ->whereNotIn('bus_no', JobOrder::query()->where('status', '!=', 'Completed')->select('bus_no'))
            ->when($busyBusIds !== [], fn ($query) => $query->whereNotIn('id', $busyBusIds))
            ->orderBy('bus_no')
            ->get();

        return view(
            'Operation.Incidents.show',
            compact('incident', 'activeBuses', 'eligibleReplacementDrivers')
        );
    }

    public function updateDetails(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($incident, $validated) {
            $locked = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            abort_unless($locked->status === 'Reported'
                && ! $locked->maintenanceReferral()->exists()
                && ! $locked->replacement()->exists()
                && $locked->responses()->count() <= 1, 403,
                'This incident has already entered an operational workflow and cannot be edited.');
            $locked->update($validated);
        });

        $this->broadcastSystemDataUpdated('Operation', 'Incident', 'updated',
            $incident->incident_no, "Incident {$incident->incident_no} details corrected");

        return redirect()->route('incidents.show', ['incident' => $incident->incident_no])
            ->with('success', 'Incident details updated successfully.');
    }

    public function destroy(Incident $incident): RedirectResponse
    {
        DB::transaction(function () use ($incident) {
            $locked = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            abort_unless($locked->incident_type !== 'Bus Breakdown'
                && $locked->status === 'Reported'
                && ! $locked->maintenanceReferral()->exists()
                && ! $locked->replacement()->exists()
                && $locked->responses()->count() <= 1, 403,
                'This incident has dependent records or workflow activity and cannot be deleted.');
            $locked->delete();
        });

        $this->broadcastSystemDataUpdated('Operation', 'Incident', 'deleted',
            $incident->incident_no, "Incident {$incident->incident_no} archived");

        return redirect()->route('incidents')->with('success',
            "Incident {$incident->incident_no} archived successfully.");
    }

    public function update(
        Request $request,
        Incident $incident
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    'Reported',
                    'Monitoring',
                    'Responding',
                    'Replacement Bus Dispatched',
                    'Resolved',
                    'Cancelled',
                ]),
            ],
            'location' => [
                'nullable',
                'string',
                'max:255',
            ],
            'trip_outcome' => ['nullable', Rule::in(['Continued', 'Interrupted', 'Cancelled', 'Unknown'])],
            'resolution_notes' => [
                Rule::requiredIf(fn () => in_array($request->input('status'), ['Resolved', 'Cancelled'], true)),
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(function () use ($incident, $validated): void {
            $locked = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            $this->assertStatusTransitionAllowed($locked->status, $validated['status']);

            if ($locked->incident_type === 'Bus Breakdown'
                && $locked->trip_schedule_id
                && $validated['status'] === 'Resolved'
                && empty($validated['trip_outcome'])) {
                throw ValidationException::withMessages([
                    'trip_outcome' => 'Record the verified trip continuation outcome before resolving this breakdown.',
                ]);
            }
            $outcomeNote = ! empty($validated['trip_outcome'])
                ? '[Trip outcome: '.$validated['trip_outcome'].'] '
                : '';

            $updateData = ['status' => $validated['status']];
            if (! empty($validated['location'])) {
                $updateData['location'] = $validated['location'];
            }
            if ($validated['status'] === 'Resolved') {
                $updateData['resolved_at'] = now();
                $updateData['resolved_by'] = auth()->id();
            }
            if (! empty($validated['resolution_notes'])) {
                $updateData['resolution_notes'] = $outcomeNote.$validated['resolution_notes'];
            }

            $locked->update($updateData);

            if (Schema::hasTable('incident_responses')) {
                IncidentResponse::create([
                    'incident_id' => $locked->id,
                    'status' => $validated['status'],
                    'notes' => $outcomeNote.($validated['resolution_notes'] ?? ''),
                    'responded_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
        });

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Incident',
            'updated',
            $incident->incident_no,
            "Incident {$incident->incident_no} status updated to {$validated['status']}"
        );

        $redirectParams = ['incident' => $incident->incident_no];
        if ($request->boolean('embedded')) {
            $redirectParams['embedded'] = 1;
        }

        return redirect()
            ->route('incidents.show', $redirectParams)
            ->with(
                'success',
                "Incident {$incident->incident_no} has been updated."
            );
    }

    public function dispatchReplacement(
        Request $request,
        Incident $incident
    ): RedirectResponse {
        $validated = $request->validate([
            'replacement_bus_id' => [
                'required',
                'integer',
                Rule::exists('buses', 'id'),
            ],
            // Driver continuity is automatic. Reject submitted overrides rather than
            // silently changing the driver on an incident-linked assignment.
            'replacement_driver_attendance_id' => ['prohibited'],
            'replacement_driver_id' => ['prohibited'],
            'oic_authorized' => ['required', 'accepted'],
        ]);

        $result = DB::transaction(function () use ($incident, $validated): array {
            $locked = Incident::query()->with('tripSchedule')->lockForUpdate()->findOrFail($incident->id);
            $replacementBus = Bus::query()->lockForUpdate()->find($validated['replacement_bus_id']);

            if ($locked->incident_type !== 'Bus Breakdown') {
                return ['error' => 'Replacement bus dispatch is only available for Bus Breakdown incidents.'];
            }
            if (in_array($locked->status, ['Resolved', 'Cancelled'], true)) {
                return ['error' => 'Cannot dispatch a replacement bus for a resolved or cancelled incident.'];
            }
            if (! $replacementBus) {
                return ['error' => 'The selected replacement bus is not available.'];
            }
            if ($locked->bus_id && $replacementBus->id === $locked->bus_id) {
                return ['error' => 'The replacement bus cannot be the same as the original breakdown bus.'];
            }
            if ($replacementBus->status !== 'Active' || $replacementBus->fleet_purpose !== 'Reserve') {
                return ['error' => 'The selected replacement bus is not an active Reserve bus.'];
            }
            if (JobOrder::query()
                ->where('bus_no', $replacementBus->bus_no)
                ->where('status', '!=', 'Completed')
                ->exists()) {
                return ['error' => 'The selected replacement bus has an unresolved Maintenance Job Order.'];
            }
            if ($locked->replacement()->lockForUpdate()->exists()) {
                return ['error' => 'A replacement bus has already been dispatched for this incident.'];
            }

            // The authorized Operations user confirms the OIC instruction at deployment.
            // The submitted acknowledgement is validated and recorded in the response log.
            $hasRecordedOicResponse = $locked->responses()
                ->where(function ($query) {
                    $query->where('status', '!=', 'Reported')
                        ->orWhere(function ($reported) {
                            $reported->where('status', 'Reported')
                                ->where('notes', '!=', 'Incident reported by driver.');
                        });
                })->exists();
            if (! $hasRecordedOicResponse) {
                return ['error' => 'Record the OIC response and instructions before confirming reserve deployment.'];
            }

            // Never switch a different slot in a multi-bus trip.
            $affectedAssignment = null;
            if ($locked->trip_schedule_id) {
                $assignmentQuery = TripAssignment::query()
                    ->where('trip_schedule_id', $locked->trip_schedule_id);
                if ($locked->trip_assignment_id) {
                    $assignmentQuery->whereKey($locked->trip_assignment_id);
                } elseif ($locked->bus_id) {
                    $assignmentQuery->where('bus_id', $locked->bus_id);
                }
                $affectedAssignment = $assignmentQuery->lockForUpdate()->first();
                if (! $affectedAssignment || (int) $affectedAssignment->bus_id !== (int) $locked->bus_id) {
                    return ['error' => 'The original bus assignment is missing or has changed. Review the incident before dispatching.'];
                }
                if (! $locked->trip_assignment_id) {
                    $locked->trip_assignment_id = $affectedAssignment->id;
                    $locked->saveQuietly();
                }
            }

            $replacementDriverAttendance = null;
            $replacementDriverAttendanceId = isset($validated['replacement_driver_attendance_id']) && $validated['replacement_driver_attendance_id'] !== ''
                ? (int) $validated['replacement_driver_attendance_id']
                : null;

            if ($locked->trip_schedule_id && $locked->tripSchedule) {
                $alreadyOnThisTrip = TripAssignment::query()
                    ->where('trip_schedule_id', $locked->trip_schedule_id)
                    ->where('bus_id', $replacementBus->id)
                    ->exists();

                if ($alreadyOnThisTrip) {
                    return ['error' => 'The selected replacement bus is already assigned to this trip.'];
                }

                $tripStart = $locked->tripSchedule->departureDateTime();
                $tripEnd = $locked->tripSchedule->estimatedArrivalDateTime();
                $conflict = TripAssignment::query()
                    ->with('tripSchedule')
                    ->whereHas('tripSchedule', function ($tripQuery) use ($locked, $tripStart, $tripEnd): void {
                        $tripQuery
                            ->where('id', '!=', $locked->trip_schedule_id)
                            ->whereNotIn('status', ['Cancelled', 'Completed'])
                            ->whereBetween('trip_date', [
                                $tripStart->copy()->subDay()->toDateString(),
                                $tripEnd->toDateString(),
                            ]);
                    })
                    ->where('bus_id', $replacementBus->id)
                    ->lockForUpdate()
                    ->get()
                    ->contains(function (TripAssignment $assignment) use ($tripStart, $tripEnd): bool {
                        $otherTrip = $assignment->tripSchedule;

                        return $otherTrip
                            && $tripStart->lt($otherTrip->estimatedArrivalDateTime())
                            && $tripEnd->gt($otherTrip->departureDateTime());
                    });

                if ($conflict) {
                    return ['error' => 'The selected bus has an overlapping trip assignment at this time.'];
                }

                if ($affectedAssignment) {
                    $tripDate = $locked->tripSchedule->trip_date?->toDateString()
                        ?: now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

                    $requestedDriverId = $validated['replacement_driver_id'] ?? null;
                    $isDriverChange = ($requestedDriverId !== null && $requestedDriverId !== $affectedAssignment->driver_id)
                        || ($requestedDriverId === null && $replacementDriverAttendanceId !== null
                            && $replacementDriverAttendanceId !== (int) ($affectedAssignment->driver_attendance_id ?? 0));

                    if ($isDriverChange) {
                        if ($requestedDriverId !== null) {
                            $driver = Driver::query()
                                ->where('driver_id', $requestedDriverId)
                                ->where('employment_status', 'Active')
                                ->lockForUpdate()->first();
                            if (! $driver || ($driver->license_expiration
                                && $driver->license_expiration->toDateString() < $tripDate)) {
                                return ['error' => 'The selected replacement driver is not active or has an expired license.'];
                            }
                            // Unverified attendance must not be fabricated during dispatch.
                            $replacementDriverAttendance = new DriverAttendance([
                                'driver_id' => $driver->driver_id,
                                'driver_name' => $driver->driver_name,
                            ]);
                        } else {
                            // Legacy callers may still submit an existing attendance ID.
                            $replacementDriverAttendance = DriverAttendance::query()
                                ->with('driver')->whereKey($replacementDriverAttendanceId)
                                ->lockForUpdate()->first();
                            if (! $replacementDriverAttendance
                                || $replacementDriverAttendance->attendance_date?->toDateString() !== $tripDate
                                || ! $replacementDriverAttendance->driver
                                || $replacementDriverAttendance->driver->employment_status !== 'Active'
                                || ($replacementDriverAttendance->driver->license_expiration
                                    && $replacementDriverAttendance->driver->license_expiration->toDateString() < $tripDate)) {
                                return ['error' => 'The selected replacement driver is not eligible for this trip date.'];
                            }
                        }

                        $targetDriverId = $replacementDriverAttendance->driver_id;
                        $targetDriverName = $replacementDriverAttendance->driver_name;
                    } else {
                        $continuingAttendance = $affectedAssignment->driver_attendance_id
                            ? DriverAttendance::query()->with('driver')->whereKey($affectedAssignment->driver_attendance_id)->lockForUpdate()->first()
                            : null;
                        $continuingDriver = $continuingAttendance?->driver
                            ?: ($affectedAssignment->driver_id
                                ? Driver::query()->where('driver_id', $affectedAssignment->driver_id)->first()
                                : null);

                        if (empty($affectedAssignment->driver_id) && empty($affectedAssignment->driver_name)) {
                            return ['error' => 'No compatible driver is assigned to continue this trip. Contact Operations to resolve the driver assignment before deploying the Reserve Bus.'];
                        }

                        if ($continuingDriver && (
                            $continuingDriver->employment_status !== 'Active'
                            || ($continuingDriver->license_expiration && $continuingDriver->license_expiration->lt($tripDate))
                        )) {
                            return ['error' => 'The original driver is no longer active or eligible. Contact Operations to resolve driver eligibility before deploying the Reserve Bus.'];
                        }

                        $targetDriverId = $affectedAssignment->driver_id;
                        $targetDriverName = $affectedAssignment->driver_name;
                    }

                    $driverConflict = TripAssignment::query()
                        ->with('tripSchedule')
                        ->where('id', '!=', $affectedAssignment->id)
                        ->where(function ($driverQuery) use ($targetDriverId, $targetDriverName): void {
                            if ($targetDriverId) {
                                $driverQuery->where('driver_id', $targetDriverId);
                            } else {
                                $driverQuery->where('driver_name', $targetDriverName);
                            }
                        })
                        ->whereHas('tripSchedule', function ($tripQuery) use ($tripStart, $tripEnd): void {
                            $tripQuery
                                ->whereNotIn('status', ['Cancelled', 'Completed', 'Missed'])
                                ->whereBetween('trip_date', [
                                    $tripStart->copy()->subDay()->toDateString(),
                                    $tripEnd->toDateString(),
                                ]);
                        })
                        ->lockForUpdate()
                        ->get()
                        ->contains(function (TripAssignment $otherAssignment) use ($locked, $tripStart, $tripEnd): bool {
                            $otherTrip = $otherAssignment->tripSchedule;
                            if (! $otherTrip) {
                                return false;
                            }
                            if ((int) $otherTrip->id === (int) $locked->trip_schedule_id) {
                                return true;
                            }

                            return $tripStart->lt($otherTrip->estimatedArrivalDateTime())
                                && $tripEnd->gt($otherTrip->departureDateTime());
                        });

                    if ($driverConflict) {
                        return [
                            'error' => $isDriverChange
                                ? 'The selected replacement driver has an overlapping trip assignment at this time.'
                                : 'The continuing driver has an overlapping trip assignment at this time. Contact Operations to resolve the driver assignment before deploying the Reserve Bus.',
                        ];
                    }
                }
            }

            IncidentReplacement::create([
                'incident_id' => $locked->id,
                'original_bus_id' => $locked->bus_id,
                'replacement_bus_id' => $replacementBus->id,
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
            ]);

            if ($replacementDriverAttendance && $affectedAssignment) {
                $affectedAssignment->refresh();
                $affectedAssignment->driver_attendance_id = $replacementDriverAttendance->exists ? $replacementDriverAttendance->id : null;
                $affectedAssignment->driver_id = $replacementDriverAttendance->driver_id;
                $affectedAssignment->driver_name = $replacementDriverAttendance->driver_name;
                $affectedAssignment->save();
            }

            $locked->update([
                'status' => 'Replacement Bus Dispatched',
            ]);

            if (Schema::hasTable('incident_responses')) {
                $driverReplacementNote = $replacementDriverAttendance
                    ? " Replacement driver {$replacementDriverAttendance->driver_name} ({$replacementDriverAttendance->driver_id}) assigned to continue the trip."
                    : '';

                IncidentResponse::create([
                    'incident_id' => $locked->id,
                    'status' => 'Replacement Bus Dispatched',
                    'notes' => "OIC authorization confirmed by Operations for reserve bus {$replacementBus->bus_no}. Replacement bus dispatched.{$driverReplacementNote}",
                    'responded_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }

            return ['bus' => $replacementBus];
        });

        $redirectParams = ['incident' => $incident->incident_no];
        if ($request->boolean('embedded')) {
            $redirectParams['embedded'] = 1;
        }

        if (isset($result['error'])) {
            return redirect()
                ->route('incidents.show', $redirectParams)
                ->with('error', $result['error']);
        }

        /** @var Bus $replacementBus */
        $replacementBus = $result['bus'];

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Incident',
            'replacement_dispatched',
            $incident->incident_no,
            "Replacement bus {$replacementBus->bus_no} dispatched for incident {$incident->incident_no}"
        );

        return redirect()
            ->route('incidents.show', $redirectParams)
            ->with(
                'success',
                "Replacement bus {$replacementBus->bus_no} has been dispatched."
            );
    }

    public function addResponse(
        Request $request,
        Incident $incident
    ): RedirectResponse {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($incident, $validated): void {
            $locked = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            if (in_array($locked->status, ['Resolved', 'Cancelled'], true)) {
                throw ValidationException::withMessages([
                    'notes' => 'Responses cannot be added to a closed incident.',
                ]);
            }

            if (Schema::hasTable('incident_responses')) {
                IncidentResponse::create([
                    'incident_id' => $locked->id,
                    'status' => $locked->status,
                    'notes' => $validated['notes'],
                    'responded_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
        });

        $redirectParams = ['incident' => $incident->incident_no];
        if ($request->boolean('embedded')) {
            $redirectParams['embedded'] = 1;
        }

        return redirect()
            ->route('incidents.show', $redirectParams)
            ->with(
                'success',
                'Response added successfully.'
            );
    }

    private function assertStatusTransitionAllowed(string $from, string $to): void
    {
        $allowed = [
            'Reported' => ['Reported', 'Monitoring', 'Responding', 'Resolved', 'Cancelled'],
            'Monitoring' => ['Monitoring', 'Responding', 'Resolved', 'Cancelled'],
            'Responding' => ['Responding', 'Resolved', 'Cancelled'],
            'Replacement Bus Dispatched' => ['Replacement Bus Dispatched', 'Responding', 'Resolved', 'Cancelled'],
            'Resolved' => ['Resolved'],
            'Cancelled' => ['Cancelled'],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Incident status cannot move from {$from} to {$to}.",
            ]);
        }
    }
}
