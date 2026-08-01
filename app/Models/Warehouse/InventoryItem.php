<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $table = 'inventory_items';

    protected $fillable = [
        'item_code',
        'parts_name',
        'item_name',
        'category',
        'on_hand',
        'quantity_available',
        'unit',
        'unit_of_measurement',
        'reorder_level',
        'status',
        'supplier',
        'location',
        'storage_location',
    ];

    protected $casts = [
        'on_hand' => 'integer',
        'quantity_available' => 'integer',
        'reorder_level' => 'integer',
    ];

    public function getStockStatusAttribute(): string
    {
        $stock = (int) (
            $this->on_hand
            ?? $this->quantity_available
            ?? 0
        );

        $reorderLevel = (int) (
            $this->reorder_level
            ?? 0
        );

        if ($stock <= 0) {
            return 'Critical';
        }

        if (
            $reorderLevel > 0
            && $stock <= $reorderLevel
        ) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    /*
    |--------------------------------------------------------------------------
    | Compatibility aliases
    |--------------------------------------------------------------------------
    */

    public function getPartsNameAttribute(): ?string
    {
        return $this->attributes['parts_name']
            ?? $this->attributes['item_name']
            ?? null;
    }

    public function getOnHandAttribute($value): int
    {
        return (int) (
            $value
            ?? $this->attributes['quantity_available']
            ?? 0
        );
    }

    public function getUnitAttribute($value): ?string
    {
        return $value
            ?? $this->attributes['unit_of_measurement']
            ?? null;
    }
}