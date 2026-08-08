<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Model;
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

    protected static function booted(): void
    {
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

        static::updated(function (InventoryItem $item) {
            if (! Schema::hasTable('stock_movements')) {
                return;
            }

            $quantityChanged = $item->wasChanged('quantity_available');
            $onHandChanged = Schema::hasColumn('inventory_items', 'on_hand') && $item->wasChanged('on_hand');

            if (! $quantityChanged && ! $onHandChanged) {
                return;
            }

            $newStock = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
            $previousStock = $quantityChanged
                ? (int) $item->getOriginal('quantity_available')
                : (int) $item->getOriginal('on_hand');

            $change = $newStock - $previousStock;

            if ($change === 0) {
                return;
            }

            $route = request()?->route();
            $routeName = $route?->getName();
            $referenceNo = $item->item_code;
            $movementType = $change > 0 ? 'Stock In' : 'Stock Out';
            $remarks = $change > 0
                ? 'Inventory quantity increased.'
                : 'Inventory quantity decreased.';

            if ($routeName === 'part-requests.issue') {
                $purchaseRequest = $route?->parameter('purchaseRequest');
                $referenceNo = is_object($purchaseRequest)
                    ? ($purchaseRequest->pr_no ?? $item->item_code)
                    : $item->item_code;
                $movementType = 'Stock Out';
                $remarks = 'Issued through Warehouse Part Request.';
            } elseif (in_array($routeName, ['purchase-orders.update-status', 'purchase-orders.update', 'purchase-orders.store'], true)) {
                $purchaseOrder = $route?->parameter('purchaseOrder');
                $referenceNo = is_object($purchaseOrder)
                    ? ($purchaseOrder->po_no ?? $item->item_code)
                    : $item->item_code;
                $movementType = 'Stock In';
                $remarks = 'Received from Purchase Order.';
            } elseif ($routeName === 'inventory.update') {
                $movementType = 'Adjustment';
                $remarks = 'Manual inventory adjustment.';
            }

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->parts_name ?? $item->item_name ?? $item->item_code ?? 'Inventory Item',
                'reference_no' => $referenceNo,
                'movement_type' => $movementType,
                'quantity_change' => $change,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'unit' => $item->unit ?? $item->unit_of_measurement,
                'remarks' => $remarks,
                'created_by' => auth()->id(),
            ]);
        });
    }

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
