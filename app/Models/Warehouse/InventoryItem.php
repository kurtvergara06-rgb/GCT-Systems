<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

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

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Keep the legacy on_hand / quantity_available columns in sync.
        |--------------------------------------------------------------------------
        | Movement logging is intentionally NOT performed here. Every stock
        | change must flow through InventoryLedgerService so that transactions,
        | references, previous/new stock and the signed quantity_change are
        | written atomically and keep new_stock = previous_stock + quantity_change.
        */
        static::saving(function (InventoryItem $item) {
            if (! Schema::hasColumn('inventory_items', 'on_hand')) {
                return;
            }

            if ($item->isDirty('quantity_available') && ! $item->isDirty('on_hand')) {
                $item->on_hand = (int) $item->quantity_available;
            } elseif ($item->isDirty('on_hand') && ! $item->isDirty('quantity_available')) {
                $item->quantity_available = (int) $item->on_hand;
            }
        });
    }

    public function getStockStatusAttribute(): string
    {
        $stock = (int) ($this->on_hand ?? $this->quantity_available ?? 0);
        $reorderLevel = (int) ($this->reorder_level ?? 0);

        if ($stock <= 0) {
            return 'Critical';
        }

        if ($reorderLevel > 0 && $stock <= $reorderLevel) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    public function getPartsNameAttribute(): ?string
    {
        return $this->attributes['parts_name'] ?? $this->attributes['item_name'] ?? null;
    }

    public function getOnHandAttribute($value): int
    {
        return (int) ($value ?? $this->attributes['quantity_available'] ?? 0);
    }

    public function getUnitAttribute($value): ?string
    {
        return $value ?? $this->attributes['unit_of_measurement'] ?? null;
    }
}
