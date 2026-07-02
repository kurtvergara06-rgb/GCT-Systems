<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmsSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_no',
        'last_pms_km',
        'next_pms_km',
        'pms_interval_km',
        'maintenance_type',
        'recommended_date',
    ];

    protected $casts = [
        'last_pms_km' => 'decimal:2',
        'next_pms_km' => 'decimal:2',
        'pms_interval_km' => 'decimal:2',
        'recommended_date' => 'date',
    ];

    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class, 'pms_schedule_id');
    }
}