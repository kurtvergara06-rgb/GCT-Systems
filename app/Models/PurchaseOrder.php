<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number',
        'purchase_request_id',
        'pr_no',
        'supplier',
        'first_item',
        'bus_no',
        'employee',
        'qty',
        'net_amount',
        'status',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'net_amount' => 'decimal:2',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }
}