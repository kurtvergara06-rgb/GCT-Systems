<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOrder extends Model
{
    protected $fillable = [
        'job_order_no',
        'bus_no',
        'service',
        'type',
        'assigned_mechanic',
        'status',
        'start_time',
        'end_time',
        'date_reported',
    ];
}