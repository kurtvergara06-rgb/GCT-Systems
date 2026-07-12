<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPurchase extends Model
{
    protected $fillable = [
        'schedule_no','schedule_name','supplier_name','supplier_contact','item',
        'quantity','unit','frequency','custom_interval_days','start_date',
        'next_purchase_date','estimated_cost','status','notes','last_po_id',
        'last_purchased_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'start_date' => 'date',
        'next_purchase_date' => 'date',
        'last_purchased_at' => 'datetime',
    ];

    public function lastPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'last_po_id');
    }

    public function getDisplayStatusAttribute(): string
    {
        if (in_array($this->status, ['Paused', 'Completed'], true)) {
            return $this->status;
        }

        if ($this->next_purchase_date->isPast()) {
            return 'Overdue';
        }

        if ($this->next_purchase_date->lte(now()->addDays(7)->endOfDay())) {
            return 'Due Soon';
        }

        return 'Active';
    }
}