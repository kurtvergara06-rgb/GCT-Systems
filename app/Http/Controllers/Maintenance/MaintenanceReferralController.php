<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceReferral;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceReferralController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function index(Request $request): View
    {
        $query = MaintenanceReferral::query()
            ->with([
                'incident.bus',
                'incident.tripSchedule',
                'referrer',
                'reviewer',
                'jobOrder',
            ]);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->whereHas('incident', function ($incidentQuery) use ($search): void {
                $incidentQuery
                    ->where('incident_no', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('bus', function ($busQuery) use ($search): void {
                        $busQuery->where('bus_no', 'like', "%{$search}%");
                    });
            });
        }

        $referrals = $query
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('Maintenance.referrals', [
            'referrals' => $referrals,
            'pendingCount' => MaintenanceReferral::where('status', 'Pending')->count(),
            'approvedCount' => MaintenanceReferral::where('status', 'Approved')->count(),
            'createdCount' => MaintenanceReferral::where('status', 'Job Order Created')->count(),
            'rejectedCount' => MaintenanceReferral::where('status', 'Rejected')->count(),
        ]);
    }

    public function approve(MaintenanceReferral $maintenanceReferral): RedirectResponse
    {
        $this->authorizeMaintenanceHead();

        if ($maintenanceReferral->status !== 'Pending') {
            return back()->with('error', 'Only pending maintenance referrals can be approved.');
        }

        $maintenanceReferral->update([
            'status' => 'Approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'MaintenanceReferral',
            'approved',
            $maintenanceReferral->id,
            "Maintenance referral for incident {$maintenanceReferral->incident?->incident_no} was approved."
        );

        return back()->with('success', 'Maintenance referral approved. Maintenance Staff may now create the Job Order.');
    }

    public function reject(Request $request, MaintenanceReferral $maintenanceReferral): RedirectResponse
    {
        $this->authorizeMaintenanceHead();

        if ($maintenanceReferral->status !== 'Pending') {
            return back()->with('error', 'Only pending maintenance referrals can be rejected.');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $maintenanceReferral->update([
            'status' => 'Rejected',
            'notes' => $validated['notes'] ?? $maintenanceReferral->notes,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'MaintenanceReferral',
            'rejected',
            $maintenanceReferral->id,
            "Maintenance referral for incident {$maintenanceReferral->incident?->incident_no} was rejected."
        );

        return back()->with('success', 'Maintenance referral rejected.');
    }

    private function authorizeMaintenanceHead(): void
    {
        $user = auth()->user();
        $department = strtolower(trim((string) ($user?->department ?? '')));
        $role = strtolower(trim((string) ($user?->role ?? '')));

        $allowed = ($department === 'maintenance' && in_array($role, ['head', 'admin', 'maintenance head', 'maintenance admin'], true))
            || ($department === 'admin' && in_array($role, ['head', 'admin', 'system admin'], true));

        abort_unless($allowed, 403, 'Only Maintenance Head can review maintenance referrals.');
    }
}
