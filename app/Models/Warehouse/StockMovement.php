<?php

namespace App\Models\Warehouse;

use App\Models\Admin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'source',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuanceItem(): HasOne
    {
        return $this->hasOne(InventoryIssuanceItem::class, 'stock_movement_id');
    }

    public function isGenuine(): bool
    {
        return $this->source === 'app';
    }
}