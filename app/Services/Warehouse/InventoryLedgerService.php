<?php

namespace App\Services\Warehouse;

use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single, audit-safe way to change inventory stock.
 *
 * Every mutation runs inside a database transaction, locks the inventory row,
 * writes a fully-referenced StockMovement with the signed quantity change,
 * and recomputes the item status. The ledger invariant enforced everywhere is:
 *
 *     new_stock = previous_stock + quantity_change
 *
 * Stock In  => positive quantity_change
 * Stock Out => negative quantity_change
 */
class InventoryLedgerService
{
    public const TYPE_STOCK_IN = 'Stock In';

    public const TYPE_STOCK_OUT = 'Stock Out';

    public const TYPE_ADJUSTMENT = 'Adjustment';

    public function stockIn(
        InventoryItem $item,
        int $quantity,
        string $referenceNo,
        ?string $remarks = null,
        ?int $actorId = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock In quantity must be a positive number.');
        }

        return $this->recordMovement(
            $item->id,
            $quantity,
            null,
            self::TYPE_STOCK_IN,
            $referenceNo,
            $remarks,
            $actorId
        );
    }

    public function stockOut(
        InventoryItem $item,
        int $quantity,
        string $referenceNo,
        ?string $remarks = null,
        ?int $actorId = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock Out quantity must be a positive number.');
        }

        return $this->recordMovement(
            $item->id,
            -$quantity,
            null,
            self::TYPE_STOCK_OUT,
            $referenceNo,
            $remarks,
            $actorId
        );
    }

    public function adjustTo(
        InventoryItem $item,
        int $newStock,
        string $referenceNo,
        ?string $remarks = null,
        ?int $actorId = null
    ): StockMovement {
        if ($newStock < 0) {
            throw new InvalidArgumentException('New stock quantity cannot be negative.');
        }

        return $this->recordMovement(
            $item->id,
            null,
            $newStock,
            self::TYPE_ADJUSTMENT,
            $referenceNo,
            $remarks,
            $actorId
        );
    }

    /**
     * @param  int|null  $signedChange  signed Stock In/Out delta (null for absolute adjustments)
     * @param  int|null  $absoluteTarget  absolute new stock level (adjustments only)
     */
    protected function recordMovement(
        int $inventoryItemId,
        ?int $signedChange,
        ?int $absoluteTarget,
        string $movementType,
        string $referenceNo,
        ?string $remarks,
        ?int $actorId
    ): StockMovement {
        if ($signedChange === null && $absoluteTarget === null) {
            throw new InvalidArgumentException('A stock movement must define a quantity change.');
        }

        return DB::transaction(function () use (
            $inventoryItemId,
            $signedChange,
            $absoluteTarget,
            $movementType,
            $referenceNo,
            $remarks,
            $actorId
        ) {
            $item = InventoryItem::query()
                ->whereKey($inventoryItemId)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new InvalidArgumentException('Inventory item no longer exists.');
            }

            $previous = (int) ($item->on_hand ?? $item->quantity_available ?? 0);

            if ($absoluteTarget !== null) {
                $signedChange = $absoluteTarget - $previous;
            }

            if ($signedChange === 0) {
                throw new InvalidArgumentException('A stock movement must change the quantity.');
            }

            $new = $previous + $signedChange;

            if ($new < 0) {
                throw new InvalidArgumentException(
                    'Insufficient stock. Current on hand is '
                    . $previous
                    . ', but '
                    . abs($new)
                    . ' would be required to complete this transaction.'
                );
            }

            $item->forceFill([
                'on_hand' => $new,
                'quantity_available' => $new,
                'status' => $this->statusFor($new, (int) $item->reorder_level),
            ])->save();

            return StockMovement::create([
                'inventory_item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->parts_name ?? $item->item_name ?? $item->item_code ?? 'Inventory Item',
                'reference_no' => $referenceNo,
                'movement_type' => $movementType,
                'quantity_change' => $signedChange,
                'previous_stock' => $previous,
                'new_stock' => $new,
                'unit' => $item->unit_of_measurement ?: $item->unit,
                'remarks' => $remarks,
                'created_by' => $actorId ?? auth()->id(),
                'source' => 'app',
            ]);
        });
    }

    protected function statusFor(int $onHand, int $reorderLevel): string
    {
        if ($onHand <= 0) {
            return 'Critical';
        }

        if ($reorderLevel > 0 && $onHand <= $reorderLevel) {
            return 'Low Stock';
        }

        return 'In Stock';
    }
}