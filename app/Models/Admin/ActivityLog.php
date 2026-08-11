<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'department',
        'activity',
        'module',
        'reference',
        'event_type',
        'details',
        'ip_address',
        'user_agent',
    ];
}
