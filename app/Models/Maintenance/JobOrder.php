<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrder extends Model
{
    protected $fillable = [
        'job_order_no',
        'bus_no',
        'pms_schedule_id',
        'problem_issue',
        'maintenance_type',
        'assigned_mechanic',
        'part_needed',
        'start_date',
        'completion_date',
        'status',
        'part_status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'completion_date' => 'datetime',
    ];

    public function pmsSchedule(): BelongsTo
    {
        return $this->belongsTo(PmsSchedule::class, 'pms_schedule_id');
    }
}