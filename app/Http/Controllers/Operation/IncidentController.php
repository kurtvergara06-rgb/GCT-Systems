<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Incident;
use App\Models\Operation\IncidentReplacement;
use App\Models\Operation\IncidentResponse;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function index(Request $request): View
    {
        $query = Incident::query()
            ->with([
                'tripSchedule.shuttleRoute',
                'bus',
                'replacement.originalBus',
                'replacement.replacementBus',
                'responses.responder',
            ]);

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
            compact(
                'incidents',
                'totalIncidents',
                'activeIncidents',
                'breakdownIncidents',
                'resolvedToday'
            )
        );
    }

    public function create(Request $request): View
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

        return view(
            'Operation.Incidents.create',
            compact(
                'activeTripAssignment',
                'tripSchedule',
                'myAssignments',
                'availableTrips'
            )
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

        if (! empty($validated['trip_assignment_id'])) {
            $tripAssignment = TripAssignment::query()
                ->with(['tripSchedule', 'bus'])
                ->where('id', $validated['trip_assignment_id'])
                ->first();

            if ($tripAssignment) {
                $validated['trip_schedule_id'] = $tripAssignment->trip_schedule_id;
                $validated['bus_id'] = $tripAssignment->bus_id;
                $validated['driver_id'] = $tripAssignment->driver_id;
                $validated['driver_name'] = $tripAssignment->driver_name;
            }
        }

        unset($validated['trip_assignment_id']);

        $incidentNo = '';

        DB::transaction(function () use ($validated, &$incidentNo) {
            $latest = Incident::query()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $nextNumber = $latest
                ? $latest->id + 1
                : 1;

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
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $updateData = [
            'status' => $validated['status'],
        ];

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

        $incident->update($updateData);

        if (Schema::hasTable('incident_responses')) {
            IncidentResponse::create([
                'incident_id' => $incident->id,
                'status' => $validated['status'],
                'notes' => $validated['resolution_notes'] ?? null,
                'responded_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }

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

        if ($incident->incident_type !== 'Bus Breakdown') {
            return redirect()
                ->route('incidents.show', ['incident' => $incident->incident_no])
                ->with(
                    'error',
                    'Replacement bus dispatch is only available for Bus Breakdown incidents.'
                );
        }

        if ($incident->status === 'Resolved' || $incident->status === 'Cancelled') {
            return redirect()
                ->route('incidents.show', ['incident' => $incident->incident_no])
                ->with(
                    'error',
                    'Cannot dispatch a replacement bus for a resolved or cancelled incident.'
                );
        }

        $replacementBus = Bus::query()
            ->where('status', 'Active')
            ->find($validated['replacement_bus_id']);

        if (! $replacementBus) {
            return redirect()
                ->route('incidents.show', ['incident' => $incident->incident_no])
                ->with(
                    'error',
                    'The selected replacement bus is not available.'
                );
        }

        if ($incident->bus_id && $replacementBus->id === $incident->bus_id) {
            return redirect()
                ->route('incidents.show', ['incident' => $incident->incident_no])
                ->with(
                    'error',
                    'The replacement bus cannot be the same as the original breakdown bus.'
                );
        }

        if ($incident->replacement()->exists()) {
            return redirect()
                ->route('incidents.show', ['incident' => $incident->incident_no])
                ->with(
                    'error',
                    'A replacement bus has already been dispatched for this incident.'
                );
        }

        if ($incident->trip_schedule_id) {
            $conflict = TripAssignment::query()
                ->whereHas('tripSchedule', function ($tripQuery) use ($incident) {
                    $tripQuery
                        ->whereDate('trip_date', $incident->tripSchedule->trip_date)
                        ->where('id', '!=', $incident->trip_schedule_id)
                        ->where('departure_time', '<', $incident->tripSchedule->estimated_arrival_time)
                        ->where('estimated_arrival_time', '>', $incident->tripSchedule->departure_time);
                })
                ->where('bus_id', $replacementBus->id)
                ->exists();

            if ($conflict) {
                return redirect()
                    ->route('incidents.show', ['incident' => $incident->incident_no])
                    ->with(
                        'error',
                        'The selected bus has an overlapping trip assignment at this time.'
                    );
            }
        }

        DB::transaction(function () use ($incident, $replacementBus) {
            IncidentReplacement::create([
                'incident_id' => $incident->id,
                'original_bus_id' => $incident->bus_id,
                'replacement_bus_id' => $replacementBus->id,
                'dispatched_at' => now(),
                'dispatched_by' => auth()->id(),
            ]);

            $incident->update([
                'status' => 'Replacement Bus Dispatched',
            ]);

            if (Schema::hasTable('incident_responses')) {
                IncidentResponse::create([
                    'incident_id' => $incident->id,
                    'status' => 'Replacement Bus Dispatched',
                    'notes' => "Replacement bus {$replacementBus->bus_no} dispatched.",
                    'responded_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
        });

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

        if (Schema::hasTable('incident_responses')) {
            IncidentResponse::create([
                'incident_id' => $incident->id,
                'status' => $incident->status,
                'notes' => $validated['notes'],
                'responded_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('incidents.show', ['incident' => $incident->incident_no])
            ->with(
                'success',
                'Response added successfully.'
            );
    }
}
