<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'pr_no',
        'job_order_no',
        'bus_no',
        'item',
        'quantity',
        'status',
        'remarks',
    ];
}