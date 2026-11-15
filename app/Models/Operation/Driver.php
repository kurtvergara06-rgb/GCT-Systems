<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Maintenance\Bus;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'driver_name',
        'shift',
        'contact_number',
        'license_number',
        'license_expiration',
        'employment_status',
    ];

    protected $casts = [
        'license_expiration' => 'date',
    ];

    public function authorizedBuses(): BelongsToMany
    {
        return $this->belongsToMany(Bus::class, 'driver_authorized_buses')->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(DriverAttendance::class, 'driver_id', 'driver_id');
    }
}
