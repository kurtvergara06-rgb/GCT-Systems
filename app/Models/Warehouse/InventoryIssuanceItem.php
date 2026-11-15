<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryIssuanceItem extends Model
{
    protected $fillable = [
        'inventory_issuance_id',
        'inventory_item_id',
        'item_code',
        'item_name',
        'quantity',
        'unit',
        'previous_stock',
        'new_stock',
        'stock_movement_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
    ];

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(InventoryIssuance::class, 'inventory_issuance_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}