<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\Incident;
use App\Models\Operation\IncidentReplacement;
use App\Models\Operation\IncidentResponse;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Services\Operation\OperationNumberSequenceService;
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

        $incidents = $query
            ->orderByDesc('incident_reported_at')
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
        $today = now()->toDateString();
        $activeTripAssignment = null;
        $tripSchedule = null;
        $tripAssignmentId = $request->input('trip_assignment_id');

        if ($tripAssignmentId) {
            $activeTripAssignment = TripAssignment::query()
                ->with(['tripSchedule.shuttleRoute', 'bus'])
                ->where('id', $tripAssignmentId)
                ->first();

            if ($activeTripAssignment) {
                $tripSchedule = $activeTripAssignment->tripSchedule;
            }
        }

        $myAssignments = TripAssignment::query()
            ->with(['tripSchedule.shuttleRoute', 'bus'])
            ->whereHas('tripSchedule', function ($tripQuery) use ($today) {
                $tripQuery
                    ->whereDate('trip_date', $today)
                    ->whereIn('status', ['Scheduled', 'Dispatched', 'Ready']);
            })
            ->orderByDesc('id')
            ->get();

        $availableTrips = TripSchedule::query()
            ->with(['shuttleRoute', 'assignment.bus'])
            ->whereDate('trip_date', $today)
            ->whereIn('status', ['Scheduled', 'Dispatched', 'Ready'])
            ->orderBy('departure_time')
            ->get();

        $incidentBuses = Bus::query()->orderBy('bus_no')->get(['id', 'bus_no', 'plate_no', 'status']);

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
                    'Traffic',
                    'Bus Breakdown',
                    'Accident/Road Incident',
                    'Other',
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
            $tripAssignment = TripAssignment::query()
                ->with(['tripSchedule', 'bus'])
                ->where('trip_schedule_id', $validated['trip_schedule_id'])
                ->first();

            if (! $tripAssignment) {
                throw ValidationException::withMessages([
                    'trip_schedule_id' => 'The selected trip does not have a valid driver and bus assignment.',
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

        unset($validated['trip_assignment_id']);

        $incidentNo = '';

        DB::transaction(function () use ($validated, &$incidentNo): void {
            if ($validated['incident_type'] === 'Bus Breakdown') {
                $activeBreakdownKey = ! empty($validated['trip_schedule_id'])
                    ? 'trip:'.$validated['trip_schedule_id']
                    : null;

                if ($activeBreakdownKey && Incident::query()
                    ->where('active_breakdown_key', $activeBreakdownKey)
                    ->lockForUpdate()
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'incident_type' => 'An active breakdown incident already exists for this trip or bus.',
                    ]);
                }
            }

            $existingMaximum = Incident::withTrashed()->get(['incident_no'])
                ->max(fn (Incident $incident) => (int) substr($incident->incident_no, -5));
            $nextNumber = $this->numberSequenceService->next('incident', (int) $existingMaximum);

            $incidentNo = 'INC-'
                .str_pad(
                    (string) $nextNumber,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

            $incident = Incident::create([
                'incident_no' => $incidentNo,
                'trip_schedule_id' => $validated['trip_schedule_id'] ?? null,
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
        });

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

    public function show(Incident $incident): View
    {
        $incident->load([
            'tripSchedule.shuttleRoute',
            'bus',
            'replacement.originalBus',
            'replacement.replacementBus',
            'replacement.dispatcher',
            'reporter',
            'resolver',
            'responses.responder',
        ]);

        $activeBuses = Bus::query()
            ->where('status', 'Active')
            ->orderBy('bus_no')
            ->get();

        return view(
            'Operation.Incidents.show',
            compact('incident', 'activeBuses')
        );
    }

    public function edit(Incident $incident): View
    {
        abort_unless($incident->status === 'Reported'
            && ! $incident->maintenanceReferral()->exists()
            && ! $incident->replacement()->exists()
            && $incident->responses()->count() <= 1, 403,
            'This incident has already entered an operational workflow and cannot be edited.');

        return view('Operation.Incidents.edit', compact('incident'));
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

            $updateData = ['status' => $validated['status']];
            if (! empty($validated['location'])) {
                $updateData['location'] = $validated['location'];
            }
            if ($validated['status'] === 'Resolved') {
                $updateData['resolved_at'] = now();
                $updateData['resolved_by'] = auth()->id();
            }
            if (! empty($validated['resolution_notes'])) {
                $updateData['resolution_notes'] = $validated['resolution_notes'];
            }

            $locked->update($updateData);

            if (Schema::hasTable('incident_responses')) {
                IncidentResponse::create([
                    'incident_id' => $locked->id,
                    'status' => $validated['status'],
                    'notes' => $validated['resolution_notes'] ?? null,
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

        return redirect()
            ->route('incidents.show', ['incident' => $incident->incident_no])
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
            if (! $replacementBus || $replacementBus->status !== 'Active') {
                return ['error' => 'The selected replacement bus is not available.'];
            }
            if ($locked->bus_id && $replacementBus->id === $locked->bus_id) {
                return ['error' => 'The replacement bus cannot be the same as the original breakdown bus.'];
            }
            if ($locked->replacement()->lockForUpdate()->exists()) {
                return ['error' => 'A replacement bus has already been dispatched for this incident.'];
            }

            if ($locked->trip_schedule_id && $locked->tripSchedule) {
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
            }

            IncidentReplacement::create([
                'incident_id' => $locked->id,
                'original_bus_id' => $locked->bus_id,
                'replacement_bus_id' => $replacementBus->id,
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
            ]);

            $locked->update([
                'status' => 'Replacement Bus Dispatched',
            ]);

            if (Schema::hasTable('incident_responses')) {
                IncidentResponse::create([
                    'incident_id' => $locked->id,
                    'status' => 'Replacement Bus Dispatched',
                    'notes' => "Replacement bus {$replacementBus->bus_no} dispatched.",
                    'responded_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }

            return ['bus' => $replacementBus];
        });

        if (isset($result['error'])) {
            return redirect()
                ->route('incidents.show', ['incident' => $incident->incident_no])
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
            ->route('incidents.show', ['incident' => $incident->incident_no])
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
            'notes' => [
                'required',
                'string',
                'max:2000',
            ],
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

        return redirect()
            ->route('incidents.show', ['incident' => $incident->incident_no])
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
