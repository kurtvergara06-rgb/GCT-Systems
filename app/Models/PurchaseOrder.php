<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_no',
        'po_date',
        'supplier_name',
        'supplier_address_tel',
        'terms',
        'terms_of_payment',
        'purpose',
        'items',
        'gross_amount',
        'delivery_fee',
        'discount',
        'vat',
        'net_amount',
        'status',
    ];

    protected $casts = [
        'po_date' => 'date',
        'items' => 'array',
        'gross_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];
}