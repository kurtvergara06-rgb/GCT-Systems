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
        'record_no',
        'bus_no',
        'grouping',
        'trip_type',
        'beginning_at',
        'initial_location',
        'ending_at',
        'final_location',
        'duration_minutes',
        'total_minutes',
        'in_motion_minutes',
        'idling_minutes',
        'mileage_km',
        'engine_hours',
        'location',
        'coordinates',
        'description',
        'source_format',
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