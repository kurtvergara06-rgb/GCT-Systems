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
        'departure_time',
        'estimated_arrival_time',
        'estimated_arrival_date',
        'shift',
        'assignment_status',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'estimated_arrival_date' => 'date',
    ];

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
        if ($this->assignment_status !== 'Unassigned') {
            return true;
        }

        if ($this->relationLoaded('assignment')) {
            if ($this->assignment !== null) {
                return true;
            }
        } elseif ($this->assignment()->exists()) {
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

    public function assignment(): HasOne
    {
        return $this->hasOne(
            TripAssignment::class,
            'trip_schedule_id'
        );
    }

    public function dailyDriverReports(): HasMany
    {
        return $this->hasMany(
            DailyDriverReport::class,
            'trip_schedule_id'
        );
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(
            Incident::class,
            'trip_schedule_id'
        );
    }
}
