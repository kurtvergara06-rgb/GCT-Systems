<?php

namespace App\Models\Operation;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TripSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'trip_code',
        'trip_date',
        'shuttle_route_id',
        'client_name',
        'direction',
        'required_buses',
        'route_code_snapshot',
        'route_name_snapshot',
        'route_origin_snapshot',
        'route_destination_snapshot',
        'planned_distance_km',
        'planned_duration_minutes',
        'departure_time',
        'estimated_arrival_time',
        'estimated_arrival_date',
        'actual_departure_time',
        'actual_arrival_time',
        'shift',
        'assignment_status',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'required_buses' => 'integer',
        'trip_date' => 'date',
        'estimated_arrival_date' => 'date',
        'planned_distance_km' => 'decimal:2',
        'planned_duration_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (TripSchedule $trip): void {
            if (! $trip->shuttle_route_id || $trip->route_name_snapshot) {
                return;
            }

            $route = ShuttleRoute::query()->find($trip->shuttle_route_id);
            if (! $route) {
                return;
            }

            $trip->route_code_snapshot = $route->route_code;
            $trip->route_name_snapshot = $route->route_name;
            $trip->route_origin_snapshot = $route->origin;
            $trip->route_destination_snapshot = $route->destination;
            $trip->planned_distance_km = $route->distance_km;
            $trip->planned_duration_minutes = $route->estimated_time_minutes;
        });

        static::updating(function (TripSchedule $trip): void {
            if (! $trip->isDirty('shuttle_route_id')) {
                return;
            }

            $route = ShuttleRoute::query()->find($trip->shuttle_route_id);
            if (! $route) {
                return;
            }

            $trip->route_code_snapshot = $route->route_code;
            $trip->route_name_snapshot = $route->route_name;
            $trip->route_origin_snapshot = $route->origin;
            $trip->route_destination_snapshot = $route->destination;
            $trip->planned_distance_km = $route->distance_km;
            $trip->planned_duration_minutes = $route->estimated_time_minutes;
        });
    }

    public function scopeNotDeparted(Builder $query): Builder
    {
        $now = now(config('app.business_timezone', 'Asia/Manila'))
            ->startOfMinute();

        return $query->where(
            function (Builder $builder) use ($now): void {
                $builder
                    ->whereDate('trip_date', '>', $now->toDateString())
                    ->orWhere(
                        function (Builder $sameDay) use ($now): void {
                            $sameDay
                                ->whereDate('trip_date', $now->toDateString())
                                ->whereTime('departure_time', '>=', $now->format('H:i:s'));
                        }
                    );
            }
        );
    }

    public function hasDeparted(): bool
    {
        if (! $this->trip_date || ! $this->departure_time) {
            return true;
        }

        return $this->departureDateTime()->lt(
            now(config('app.business_timezone', 'Asia/Manila'))
                ->startOfMinute()
        );
    }

    public function hasOperationalHistory(): bool
    {
        if (in_array($this->status, ['Dispatched', 'Completed'], true)) {
            return true;
        }

        if ($this->assignment_status === 'Assigned') {
            return true;
        }

        if ($this->is_fully_assigned && $this->assigned_buses_count > 0) {
            return true;
        }

        $dailyDriverReportsCount = $this->getAttribute(
            'daily_driver_reports_count'
        );

        if ($dailyDriverReportsCount !== null) {
            if ((int) $dailyDriverReportsCount > 0) {
                return true;
            }
        } elseif ($this->dailyDriverReports()->exists()) {
            return true;
        }

        $incidentsCount = $this->getAttribute(
            'incidents_count'
        );

        if ($incidentsCount !== null) {
            if ((int) $incidentsCount > 0) {
                return true;
            }
        } elseif ($this->incidents()->exists()) {
            return true;
        }

        return false;
    }

    public function canBeManagedFromSchedule(): bool
    {
        return in_array(
            $this->status,
            ['Scheduled', 'Cancelled'],
            true
        ) && ! $this->hasOperationalHistory();
    }

    public function getAssignedBusesCountAttribute(): int
    {
        if ($this->relationLoaded('assignments')) {
            return $this->assignments->count() + $this->externalRentals()->where('status', 'Assigned')->count();
        }

        $count = $this->getAttribute('assignments_count');
        if ($count !== null) {
            return (int) $count + $this->externalRentals()->where('status', 'Assigned')->count();
        }

        if ($this->relationLoaded('assignment')) {
            return ($this->assignment ? 1 : 0) + $this->externalRentals()->where('status', 'Assigned')->count();
        }

        return $this->assignments()->count() + $this->externalRentals()->where('status', 'Assigned')->count();
    }

    public function getIsFullyAssignedAttribute(): bool
    {
        return $this->assigned_buses_count >= max(1, (int) ($this->required_buses ?? 1));
    }

    public function getIsPartiallyAssignedAttribute(): bool
    {
        $assigned = $this->assigned_buses_count;
        $required = max(1, (int) ($this->required_buses ?? 1));

        return $assigned > 0 && $assigned < $required;
    }

    public function canBeDispatched(): bool
    {
        return $this->status === 'Ready'
            && ! $this->assignments()->whereHas('bus', fn ($query) => $query->where('status', '!=', 'Active'))->exists()
            && $this->is_fully_assigned
            && ! in_array($this->status, ['Cancelled', 'Dispatched', 'Completed'], true);
    }

    public function externalRentals(): HasMany
    {
        return $this->hasMany(ExternalBusRental::class, 'trip_schedule_id');
    }

    public function syncAssignmentStatus(bool $save = true): void
    {
        $assignedCount = $this->assignments()->count() + $this->externalRentals()->where('status', 'Assigned')->count();
        $requiredCount = max(1, (int) ($this->required_buses ?? 1));
        $hasUnavailableBus = $this->assignments()
            ->whereHas('bus', fn ($query) => $query->where('status', '!=', 'Active'))
            ->exists();

        if ($assignedCount === 0) {
            $this->assignment_status = 'Unassigned';
            if ($this->status === 'Ready') {
                $this->status = 'Scheduled';
            }
        } elseif ($assignedCount < $requiredCount) {
            $this->assignment_status = 'Partially Assigned';
            if ($this->status === 'Ready') {
                $this->status = 'Scheduled';
            }
        } else {
            $this->assignment_status = 'Assigned';
            if ($hasUnavailableBus && $this->status === 'Ready' && ! $this->hasDeparted()) {
                $this->status = 'Scheduled';
            } elseif (! $hasUnavailableBus && $this->status === 'Scheduled') {
                $this->status = 'Ready';
            }
        }

        if ($save) {
            $this->save();
        }
    }

    public static function shiftForDeparture(Carbon $departure): string
    {
        $minutes = (((int) $departure->format('H')) * 60)
            + (int) $departure->format('i');

        if ($minutes >= 240 && $minutes < 720) {
            return 'Morning';
        }

        if ($minutes >= 720 && $minutes < 1080) {
            return 'Afternoon';
        }

        return 'Night';
    }

    public function departureDateTime(): Carbon
    {
        return Carbon::parse(
            $this->trip_date->format('Y-m-d').' '.$this->departure_time,
            config('app.business_timezone', 'Asia/Manila')
        );
    }

    public function estimatedArrivalDateTime(): Carbon
    {
        $arrivalDate = $this->estimated_arrival_date
            ? $this->estimated_arrival_date->copy()
            : $this->trip_date->copy();

        $arrival = Carbon::parse(
            $arrivalDate->format('Y-m-d').' '.$this->estimated_arrival_time,
            config('app.business_timezone', 'Asia/Manila')
        );

        if (
            $this->estimated_arrival_date === null
            && $arrival->lessThanOrEqualTo($this->departureDateTime())
        ) {
            $arrival->addDay();
        }

        return $arrival;
    }

    public function shuttleRoute(): BelongsTo
    {
        return $this->belongsTo(
            ShuttleRoute::class,
            'shuttle_route_id'
        );
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(
            TripAssignment::class,
            'trip_schedule_id'
        );
    }

    public function slotDecisions(): HasMany
    {
        return $this->hasMany(
            AutoScheduleSlotDecision::class,
            'trip_schedule_id'
        );
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(
            TripAssignment::class,
            'trip_schedule_id'
        )->oldestOfMany();
    }

    public function dailyDriverReports(): HasMany
    {
        return $this->hasMany(
            DailyDriverReport::class,
            'trip_schedule_id'
        );
    }

    public function dailyDriverReportTripEntries(): HasMany
    {
        return $this->hasMany(DailyDriverReportTripEntry::class, 'trip_schedule_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(
            Incident::class,
            'trip_schedule_id'
        );
    }
}
