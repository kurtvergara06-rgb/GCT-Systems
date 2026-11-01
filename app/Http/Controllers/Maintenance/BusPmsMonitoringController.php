<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\BusPmsMonitoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusPmsMonitoringController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bus_id' => ['required', 'integer', 'exists:buses,id'],
            'last_completed_pms_date' => ['required', 'date', 'before_or_equal:today'],
            'last_completed_pms_odometer_km' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'verified_current_odometer_km' => ['required', 'numeric', 'gte:last_completed_pms_odometer_km', 'max:9999999999'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $bus = Bus::query()->lockForUpdate()->findOrFail($validated['bus_id']);
            if (BusPmsMonitoring::where('bus_id', $bus->id)->exists()) {
                throw ValidationException::withMessages([
                    'bus_id' => 'PMS monitoring already exists for this bus. Duplicate configurations are not permitted.',
                ]);
            }

            BusPmsMonitoring::create([
                ...$validated,
                'current_odometer_verified_at' => isset($validated['verified_current_odometer_km']) ? now() : null,
                'interval_km' => 10000,
                'interval_months' => 3,
                'is_active' => true,
                'configured_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('PMS-Scheduling')
            ->with('success', 'Bus PMS monitoring baseline saved.');
    }
}
