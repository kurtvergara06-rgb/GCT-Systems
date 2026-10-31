<?php

namespace App\Models\Maintenance;

use Illuminate\Database\Eloquent\Model;

class JobOrderPartReservation extends Model
{
    protected $fillable = ['job_order_id', 'inventory_item_id', 'quantity'];
    protected $casts = ['quantity' => 'integer'];
}
