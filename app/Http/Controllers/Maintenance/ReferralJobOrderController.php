<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\MaintenanceReferral;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReferralJobOrderController extends Controller
{
    use SystemDataUpdateBroadcaster;
    public function store(MaintenanceReferral $maintenanceReferral): RedirectResponse
    {
        $this->authorizeMaintenanceStaff();

        $maintenanceReferral->load(['incident.bus', 'jobOrder']);

        if ($maintenanceReferral->status !== 'Approved') {
            return back()->with('error', 'Only approved maintenance referrals can create a Job Order.');
        }

        if ($maintenanceReferral->jobOrder) {
            return redirect()
                ->route('job-orders', ['search' => $maintenanceReferral->jobOrder->job_order_no])
                ->with('error', 'This referral already has a Job Order.');
        }

        $incident = $maintenanceReferral->incident;
        $busNo = $incident?->bus?->bus_no;

        if (! $incident || ! $busNo) {
            return back()->with('error', 'The referred incident is not linked to a valid bus.');
        }

        $activeJobOrder = JobOrder::query()
            ->where('bus_no', $busNo)
            ->where('status', '!=', 'Completed')
            ->first();

        if ($activeJobOrder) {
            return redirect()
                ->route('job-orders', ['search' => $activeJobOrder->job_order_no])
                ->with('error', 'This bus already has an active Job Order.');
        }

        $jobOrder = DB::transaction(function () use ($maintenanceReferral, $incident, $busNo): JobOrder {
            $lockedReferral = MaintenanceReferral::query()
                ->whereKey($maintenanceReferral->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReferral->status !== 'Approved' || $lockedReferral->jobOrder()->exists()) {
                abort(409, 'This referral has already been processed.');
            }

            $year = now()->format('Y');
            $lastJobOrder = JobOrder::query()
                ->where('job_order_no', 'like', "JO-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            preg_match('/JO-' . $year . '-(\d+)/', (string) ($lastJobOrder?->job_order_no ?? ''), $matches);
            $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
            $jobOrderNo = 'JO-' . $year . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

            while (JobOrder::where('job_order_no', $jobOrderNo)->exists()) {
                $nextNumber++;
                $jobOrderNo = 'JO-' . $year . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            }

            $problem = trim((string) $incident->description);
            if ($problem === '') {
                $problem = 'Bus breakdown reported at ' . ($incident->location ?: 'an unspecified location') . '.';
            }

            $jobOrder = JobOrder::create([
                'job_order_no' => $jobOrderNo,
                'bus_no' => $busNo,
                'maintenance_referral_id' => $lockedReferral->id,
                'incident_id' => $incident->id,
                'problem_issue' => $problem,
                'maintenance_type' => 'Repair',
                'assigned_mechanic' => null,
                'part_needed' => null,
                'start_date' => now(),
                'completion_date' => null,
                'status' => 'On Hold',
                'part_status' => 'No Parts Needed',
            ]);

            $lockedReferral->update(['status' => 'Job Order Created']);

            return $jobOrder;
        });

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'created',
            $jobOrder->id,
            "Job Order {$jobOrder->job_order_no} created from referral."
        );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'MaintenanceReferral',
            'updated',
            $maintenanceReferral->id,
            "Referral status updated to Job Order Created."
        );

        return redirect()
            ->route('job-orders', ['search' => $jobOrder->job_order_no])
            ->with('success', "Job Order {$jobOrder->job_order_no} created from incident {$incident->incident_no}. Assign a mechanic to continue the repair workflow.");
    }

    private function authorizeMaintenanceStaff(): void
    {
        $user = auth()->user();
        $department = strtolower(trim((string) ($user?->department ?? '')));
        $role = strtolower(trim((string) ($user?->role ?? '')));

        $allowed = ($department === 'maintenance' && in_array($role, ['staff', 'head', 'admin', 'maintenance staff', 'maintenance head', 'maintenance admin'], true))
            || ($department === 'admin' && in_array($role, ['head', 'admin', 'system admin'], true));

        abort_unless($allowed, 403, 'Only Maintenance personnel can create Job Orders from referrals.');
    }
}
