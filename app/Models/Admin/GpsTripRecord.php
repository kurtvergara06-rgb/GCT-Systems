<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GpsTripRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_upload_id',
        'bus_no',
        'grouping',
        'beginning_at',
        'initial_location',
        'ending_at',
        'final_location',
        'engine_hours',
        'total_minutes',
        'in_motion_minutes',
        'idling_minutes',
        'mileage_km',
        'severity',
        'raw_data',
    ];

    protected $casts = [
        'beginning_at' => 'datetime',
        'ending_at' => 'datetime',
        'engine_hours' => 'decimal:2',
        'mileage_km' => 'decimal:2',
        'raw_data' => 'array',
    ];

    public function batchUpload(): BelongsTo
    {
        return $this->belongsTo(BatchUpload::class, 'batch_upload_id');
    }
}