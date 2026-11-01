<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceReferral;
use Illuminate\Http\RedirectResponse;

class ReferralJobOrderController extends Controller
{
    /**
     * Legacy referral action: direct JO creation is intentionally disabled.
     * The staff must complete the standard JO form before a record is saved.
     */
    public function store(MaintenanceReferral $maintenanceReferral): RedirectResponse
    {
        abort_unless(auth()->user()?->hasSystemPermission('maintenance', 'edit'), 403,
            'Your role does not have permission to create Job Orders from referrals.');

        if ($maintenanceReferral->status !== 'Approved' || $maintenanceReferral->jobOrder()->exists()) {
            return back()->with('error', 'Only approved referrals without a Job Order can start a Job Order.');
        }

        return redirect()->route('job-orders', [
            'maintenance_referral_id' => $maintenanceReferral->id,
        ])->with('success', 'Complete the Job Order form and submit it for review.');
    }
}
