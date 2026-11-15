<?php

namespace App\Models\Maintenance;

use App\Models\Operation\TripAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Operation\Driver;
use App\Models\Operation\OperationClient;

class Bus extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_no',
        'client_allocation_type',
        'fleet_purpose',
        'plate_no',
        'bus_model',
        'year_model',
        'capacity',
        'route_grouping',
        'status',
        'pre_maintenance_status',
        'latest_gps_km',
        'latest_gps_at',
        'last_pms_km',
        'pms_interval_km',
        'next_pms_km',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'latest_gps_km' => 'decimal:2',
        'latest_gps_at' => 'datetime',
        'last_pms_km' => 'decimal:2',
        'pms_interval_km' => 'decimal:2',
        'next_pms_km' => 'decimal:2',
    ];

    public function authorizedDrivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'driver_authorized_buses')->withTimestamps();
    }

    public function operationClients(): BelongsToMany
    {
        return $this->belongsToMany(OperationClient::class, 'bus_operation_clients')->withTimestamps();
    }

    public function tripAssignments(): HasMany
    {
        return $this->hasMany(
            TripAssignment::class,
            'bus_id'
        );
    }

    /**
     * Hand control of this bus to Maintenance without losing the operational
     * status that should be restored after the final maintenance workflow is
     * released.
     */
    public function placeUnderMaintenance(): bool
    {
        if ($this->status === 'Under Maintenance') {
            return false;
        }

        $previousStatus = in_array($this->status, ['Active', 'Inactive'], true)
            ? $this->status
            : null;

        $updated = $this->update([
            'pre_maintenance_status' => $this->pre_maintenance_status ?: $previousStatus,
            'status' => 'Under Maintenance',
        ]);
        if ($updated) {
            $this->syncFutureTripReadiness();
        }
        return $updated;
    }

    /**
     * Return control to Operations after Maintenance confirms that no active
     * workflow remains. Legacy records fall back to Active, matching the
     * historical release behavior.
     */
    public function releaseFromMaintenance(): bool
    {
        if ($this->status !== 'Under Maintenance') {
            return false;
        }

        $restoredStatus = in_array($this->pre_maintenance_status, ['Active', 'Inactive'], true)
            ? $this->pre_maintenance_status
            : 'Active';

        $updated = $this->update([
            'status' => $restoredStatus,
            'pre_maintenance_status' => null,
        ]);
        if ($updated) {
            $this->syncFutureTripReadiness();
        }
        return $updated;
    }
    /**
     * Recalculate future trip readiness when Maintenance changes bus availability.
     * Departed and historical schedules stay untouched for their audit trail.
     */
    private function syncFutureTripReadiness(): void
    {
        $this->tripAssignments()
            ->with('tripSchedule')
            ->get()
            ->pluck('tripSchedule')
            ->filter(fn ($trip) => $trip
                && in_array($trip->status, ['Ready', 'Scheduled'], true)
                && ! $trip->hasDeparted())
            ->unique('id')
            ->each(fn ($trip) => $trip->syncAssignmentStatus());
    }
}
