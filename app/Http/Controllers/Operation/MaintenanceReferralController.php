<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Operation\Incident;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MaintenanceReferralController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function store(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeOperationHead();

        if ($incident->incident_type !== 'Bus Breakdown') {
            return back()->with('error', 'Only Bus Breakdown incidents can be referred to Maintenance.');
        }

        if (! $incident->bus_id) {
            return back()->with('error', 'The incident must be linked to a bus before it can be referred to Maintenance.');
        }

        if ($incident->maintenanceReferral()->exists()) {
            return back()->with('error', 'This incident already has a Maintenance referral.');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $incident->loadMissing(['bus', 'tripSchedule']);

        if ($incident->bus && $incident->bus->status !== 'Under Maintenance') {
            $incident->bus->update(['status' => 'Under Maintenance']);
        }

        $notes = trim((string) ($validated['notes'] ?? ''));

        if ($notes === '' && $incident->is_unplanned_breakdown) {
            $tripCode = $incident->tripSchedule?->trip_code ?: 'the active trip';
            $notes = "Unplanned breakdown during {$tripCode}. Original bus was placed Under Maintenance for inspection and repair.";
        }

        $referral = MaintenanceReferral::create([
            'incident_id' => $incident->id,
            'status' => 'Pending',
            'notes' => $notes !== '' ? $notes : null,
            'referred_by' => auth()->id(),
        ]);

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
