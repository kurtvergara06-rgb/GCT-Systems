<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceReferral;
use App\Support\TableSort;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceReferralController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function index(Request $request): View
    {
        $recordView = $request->input('record_view') === 'history'
            ? 'history'
            : 'active';

        $activeStatuses = ['Pending', 'Approved'];
        $historyStatuses = ['Job Order Created', 'Rejected'];
        $viewStatuses = $recordView === 'history'
            ? $historyStatuses
            : $activeStatuses;

        $query = MaintenanceReferral::query()
            ->with([
                'incident.bus',
                'incident.tripSchedule',
                'referrer',
                'reviewer',
                'jobOrder',
            ])
            ->whereIn('status', $viewStatuses);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            if (is_string($status) && in_array($status, $viewStatuses, true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($referralQuery) use ($search): void {
                $referralQuery
                    ->whereHas('incident', function ($incidentQuery) use ($search): void {
                        $incidentQuery
                            ->where('incident_no', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('bus', function ($busQuery) use ($search): void {
                                $busQuery->where('bus_no', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('jobOrder', function ($jobOrderQuery) use ($search): void {
                        $jobOrderQuery
                            ->where('job_order_no', 'like', "%{$search}%")
                            ->orWhere('bus_no', 'like', "%{$search}%");
                    });
            });
        }

        TableSort::apply($query, $request, ['created_at' => 'created_at', 'status' => 'status'], 'created_at', 'desc');
        $referrals = $query
            ->paginate(25)
            ->withQueryString();

        return view('Maintenance.referrals', [
            'referrals' => $referrals,
            'recordView' => $recordView,
            'viewStatuses' => $viewStatuses,
            'activeCount' => MaintenanceReferral::whereIn('status', $activeStatuses)->count(),
            'historyCount' => MaintenanceReferral::whereIn('status', $historyStatuses)->count(),
            'pendingCount' => MaintenanceReferral::where('status', 'Pending')->count(),
            'approvedCount' => MaintenanceReferral::where('status', 'Approved')->count(),
            'createdCount' => MaintenanceReferral::where('status', 'Job Order Created')->count(),
            'rejectedCount' => MaintenanceReferral::where('status', 'Rejected')->count(),
        ]);
    }

    public function approve(MaintenanceReferral $maintenanceReferral): RedirectResponse
    {
        $this->authorizeReferralReview();

        $updated = MaintenanceReferral::query()
            ->whereKey($maintenanceReferral->getKey())
            ->where('status', 'Pending')
            ->update([
                'status' => 'Approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

        if ($updated !== 1) {
            return back()->with('error', 'Only pending maintenance referrals can be approved.');
        }

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
        $this->authorizeReferralReview();

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $fields = [
            'status' => 'Rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ];
        if (array_key_exists('notes', $validated) && $validated['notes'] !== null) {
            $fields['notes'] = $validated['notes'];
        }

        $updated = MaintenanceReferral::query()
            ->whereKey($maintenanceReferral->getKey())
            ->where('status', 'Pending')
            ->update($fields);

        if ($updated !== 1) {
            return back()->with('error', 'Only pending maintenance referrals can be rejected.');
        }

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'MaintenanceReferral',
            'rejected',
            $maintenanceReferral->id,
            "Maintenance referral for incident {$maintenanceReferral->incident?->incident_no} was rejected."
        );

        return back()->with('success', 'Maintenance referral rejected.');
    }

    private function authorizeReferralReview(): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->hasSystemPermission('maintenance', 'approve') ?? false,
            403,
            'Your role does not have permission to review maintenance referrals.'
        );
    }
}
