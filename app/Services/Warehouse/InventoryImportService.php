<?php

namespace App\Services\Warehouse;

use App\Models\Warehouse\InventoryItem;
use Illuminate\Support\Facades\DB;

final class InventoryImportService
{
    public function __construct(private InventoryLedgerService $ledger) {}

    /**
     * Publish an imported inventory snapshot without bypassing the stock ledger
     * or reducing physical stock below quantities reserved for approved JOs.
     */
    public function reconcile(
        array $payload,
        string $source,
        ?int $actorId = null
    ): InventoryItem {
        return DB::transaction(function () use ($payload, $source, $actorId): InventoryItem {
            $targetStock = (int) ($payload['on_hand'] ?? $payload['quantity_available'] ?? 0);
            unset($payload['on_hand'], $payload['quantity_available']);

            $item = null;
            if (! empty($payload['item_code'])) {
                $item = InventoryItem::query()
                    ->where('item_code', $payload['item_code'])
                    ->lockForUpdate()
                    ->first();
            }

            if ($item) {
                $currentStock = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
                $item->update($payload);
            } else {
                $currentStock = 0;
                $item = InventoryItem::create($payload + [
                    'on_hand' => 0,
                    'quantity_available' => 0,
                ]);
            }

            if ($targetStock !== $currentStock) {
                $this->ledger->adjustTo(
                    $item,
                    $targetStock,
                    'ADMIN-IMPORT-'.$item->id,
                    $source.' inventory reconciliation.',
                    $actorId
                );
            }

            return $item->fresh();
        });
    }
}
