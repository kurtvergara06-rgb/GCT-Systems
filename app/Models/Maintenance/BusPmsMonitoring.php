<?php

namespace App\Models\Maintenance;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusPmsMonitoring extends Model
{
    protected $table = 'bus_pms_monitoring';

    protected $fillable = [
        'bus_id',
        'last_completed_pms_date',
        'last_completed_pms_odometer_km',
        'interval_km',
        'interval_months',
        'is_active',
        'configured_by',
        'verified_current_odometer_km',
        'current_odometer_verified_at',
    ];

    protected $casts = [
        'last_completed_pms_date' => 'immutable_date',
        'last_completed_pms_odometer_km' => 'decimal:2',
        'verified_current_odometer_km' => 'decimal:2',
        'current_odometer_verified_at' => 'datetime',
        'interval_km' => 'integer',
        'interval_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function nextDueDate(): CarbonImmutable
    {
        return $this->last_completed_pms_date->addMonthsNoOverflow($this->interval_months);
    }

    public function nextDueOdometerKm(): float
    {
        return (float) $this->last_completed_pms_odometer_km + $this->interval_km;
    }

    /**
     * Null means an odometer reading has not been verified; never use raw
     * GPS report distance as a lifetime odometer.
     */
    public function status(?float $verifiedCurrentOdometerKm = null): string
    {
        $verifiedCurrentOdometerKm ??= $this->verified_current_odometer_km !== null ? (float) $this->verified_current_odometer_km : null;
        if (! $this->is_active) {
            return 'Inactive';
        }

        if (now()->startOfDay()->greaterThanOrEqualTo($this->nextDueDate())) {
            return 'PMS Due';
        }

        if ($verifiedCurrentOdometerKm !== null && $verifiedCurrentOdometerKm >= $this->nextDueOdometerKm()) {
            return 'PMS Due';
        }

        if ($verifiedCurrentOdometerKm === null) {
            return 'Mileage Unverified';
        }

        return 'Upcoming';
    }
}
