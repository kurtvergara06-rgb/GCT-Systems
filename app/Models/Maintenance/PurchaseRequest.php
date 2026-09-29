<?php

namespace App\Models\Maintenance;

use App\Models\Purchase\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'pr_no',
        'job_order_no',
        'bus_no',
        'item',
        'quantity',
        'status',
        'warehouse_status',
        'warehouse_approved_by',
        'warehouse_approved_at',
        'warehouse_prepared_by',
        'warehouse_prepared_at',
        'warehouse_issue_quantities',
        'source_type',
        'remarks',
        'approved_at',
        'rejected_at',
        'issued_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'issued_at' => 'datetime',
        'warehouse_approved_at' => 'datetime',
        'warehouse_prepared_at' => 'datetime',
        'warehouse_issue_quantities' => 'array',
    ];

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }
}
