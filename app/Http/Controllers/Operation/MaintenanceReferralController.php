<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Operation\Incident;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceReferralController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function store(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeOperationHead();

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $referral = DB::transaction(function () use ($incident, $validated): MaintenanceReferral {
            $locked = Incident::query()->with(['bus', 'tripSchedule'])
                ->lockForUpdate()->findOrFail($incident->id);

            if ($locked->incident_type !== 'Bus Breakdown') {
                throw ValidationException::withMessages([
                    'incident' => 'Only Bus Breakdown incidents can be referred to Maintenance.',
                ]);
            }
            if (! $locked->bus_id) {
                throw ValidationException::withMessages([
                    'incident' => 'The incident must be linked to a bus before it can be referred to Maintenance.',
                ]);
            }
            if ($locked->maintenanceReferral()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'incident' => 'This incident already has a Maintenance referral.',
                ]);
            }

            if ($locked->bus && $locked->bus->status !== 'Under Maintenance') {
                $locked->bus->update(['status' => 'Under Maintenance']);
            }

            $notes = trim((string) ($validated['notes'] ?? ''));
            if ($notes === '' && $locked->is_unplanned_breakdown) {
                $tripCode = $locked->tripSchedule?->trip_code ?: 'the active trip';
                $notes = "Unplanned breakdown during {$tripCode}. Original bus was placed Under Maintenance for inspection and repair.";
            }

            return MaintenanceReferral::create([
                'incident_id' => $locked->id,
                'status' => 'Pending',
                'notes' => $notes !== '' ? $notes : null,
                'referred_by' => auth()->id(),
            ]);
        });

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'MaintenanceReferral',
            'created',
            $referral->id,
            $incident->is_unplanned_breakdown
                ? "Unplanned breakdown {$incident->incident_no} was referred to Maintenance for review."
                : "Breakdown incident {$incident->incident_no} was referred to Maintenance for review."
        );

        return back()->with('success', 'Incident referred to Maintenance for review.');
    }

    private function authorizeOperationHead(): void
    {
        $user = auth()->user();
        $department = strtolower(trim((string) ($user?->department ?? '')));
        $role = strtolower(trim((string) ($user?->role ?? '')));

        $allowed = (in_array($department, ['operation', 'operations'], true)
                && in_array($role, ['head', 'admin', 'operation head', 'operations head', 'operation admin'], true))
            || ($department === 'admin' && in_array($role, ['head', 'admin', 'system admin'], true));

        abort_unless($allowed, 403, 'Only Operation Head can refer incidents to Maintenance.');
    }
}
