<?php

namespace App\Models\Maintenance;

use App\Services\Maintenance\DdrPmsMileageService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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

    /** @return Collection<int, int> */
    public static function dueBusIds(?array $busIds = null): Collection
    {
        $monitors = self::query()->where('is_active', true)
            ->when($busIds !== null, fn ($query) => $query->whereIn('bus_id', $busIds))
            ->with('bus:id,bus_no')->get();

        $due = $monitors->filter(fn (self $monitor) => $monitor->status() === 'PMS Due');
        $remaining = $monitors->diff($due)->values();
        $estimates = app(DdrPmsMileageService::class)->estimates($remaining);

        return $due->concat($remaining->filter(fn (self $monitor) =>
            $monitor->status(data_get($estimates->get($monitor->bus_id), 'estimated_odometer_km')) === 'PMS Due'
        ))->pluck('bus_id')->values();
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

        if (now(config('app.business_timezone', 'Asia/Manila'))->toDateString() >= $this->nextDueDate()->toDateString()) {
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
