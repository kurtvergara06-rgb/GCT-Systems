<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopbarNotification extends Model
{
    protected $fillable = [
        'module',
        'entity',
        'action',
        'record_id',
        'message',
        'created_by',
    ];
}
