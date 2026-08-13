<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'item_code',
        'item_name',
        'reference_no',
        'movement_type',
        'quantity_change',
        'previous_stock',
        'new_stock',
        'unit',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
