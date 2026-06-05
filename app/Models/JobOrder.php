<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOrder extends Model
{
    protected $fillable = [
        'job_order_no',
        'bus_no',
        'problem_issue',
        'maintenance_type',
        'assigned_mechanic',
        'part_needed',
        'start_date',
        'completion_date',
        'status',
    ];
}