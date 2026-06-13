<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Auto-create restock requests for existing low / critical stocks
        |--------------------------------------------------------------------------
        */
        $this->syncAutoRestockRequests();

        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                    ->orWhere('parts_name', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('storage_location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'All Categories') {
            $query->where('category', $request->category);
        }

        $inventoryItems = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $categories = InventoryItem::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $totalItemsInStock = InventoryItem::count();

        $lowStockAlerts = InventoryItem::query()
            ->whereColumn('on_hand', '<=', 'reorder_level')
            ->where('on_hand', '>', 0)
            ->count();

        $criticalItems = InventoryItem::query()
            ->where('on_hand', '<=', 0)
            ->count();

        $forecastedStockouts = InventoryItem::query()
            ->whereColumn('on_hand', '<=', 'reorder_level')
            ->count();

        $itemsAtRisk = $forecastedStockouts;

        return view('Warehouse.Inventory', compact(
            'inventoryItems',
            'categories',
            'totalItemsInStock',
            'lowStockAlerts',
            'criticalItems',
            'forecastedStockouts',
            'itemsAtRisk'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_code' => ['nullable', 'string', 'max:255'],
            'parts_name' => ['nullable', 'string', 'max:255'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'on_hand' => ['required', 'integer', 'min:0'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
            'unit_of_measurement' => ['nullable', 'string', 'max:255'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated) {
            $validated['quantity_available'] = $validated['quantity_available'] ?? $validated['on_hand'];

            $validated['status'] = $this->inventoryStatus(
                (int) $validated['on_hand'],
                (int) $validated['reorder_level']
            );

            $inventoryItem = InventoryItem::create($validated);

            $this->createAutoRestockRequestIfNeeded($inventoryItem);
        });

        return redirect()
            ->route('inventory')
            ->with('success', 'Inventory item added successfully.');
    }

    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $validated = $request->validate([
            'item_code' => ['nullable', 'string', 'max:255'],
            'parts_name' => ['nullable', 'string', 'max:255'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'on_hand' => ['required', 'integer', 'min:0'],
            'quantity_available' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
            'unit_of_measurement' => ['nullable', 'string', 'max:255'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $inventoryItem) {
            $validated['quantity_available'] = $validated['quantity_available'] ?? $validated['on_hand'];

            $validated['status'] = $this->inventoryStatus(
                (int) $validated['on_hand'],
                (int) $validated['reorder_level']
            );

            $inventoryItem->update($validated);

            $this->createAutoRestockRequestIfNeeded($inventoryItem->fresh());
        });

        return redirect()
            ->route('inventory')
            ->with('success', 'Inventory item updated successfully.');
    }

    public function destroy(InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->delete();

        return redirect()
            ->route('inventory')
            ->with('success', 'Inventory item deleted successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'inventory_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('inventory_file');
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return redirect()
                ->route('inventory')
                ->with('error', 'Unable to read the uploaded file.');
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            return redirect()
                ->route('inventory')
                ->with('error', 'The uploaded CSV file is empty.');
        }

        $header = array_map(function ($value) {
            return strtolower(trim($value));
        }, $header);

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($handle, $header, &$created, &$updated) {
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);

                if (! $data) {
                    continue;
                }

                $itemCode = trim($data['item_code'] ?? '');
                $partsName = trim($data['parts_name'] ?? $data['item_name'] ?? '');

                if ($itemCode === '' && $partsName === '') {
                    continue;
                }

                $onHand = (int) ($data['on_hand'] ?? $data['quantity_available'] ?? 0);
                $reorderLevel = (int) ($data['reorder_level'] ?? 0);

                $payload = [
                    'item_code' => $itemCode ?: null,
                    'parts_name' => $partsName ?: null,
                    'item_name' => $partsName ?: null,
                    'category' => trim($data['category'] ?? '') ?: null,
                    'on_hand' => $onHand,
                    'quantity_available' => $onHand,
                    'unit' => trim($data['unit'] ?? $data['unit_of_measurement'] ?? '') ?: null,
                    'unit_of_measurement' => trim($data['unit_of_measurement'] ?? $data['unit'] ?? '') ?: null,
                    'reorder_level' => $reorderLevel,
                    'status' => $this->inventoryStatus($onHand, $reorderLevel),
                    'supplier' => trim($data['supplier'] ?? '') ?: null,
                    'location' => trim($data['location'] ?? $data['storage_location'] ?? '') ?: null,
                    'storage_location' => trim($data['storage_location'] ?? $data['location'] ?? '') ?: null,
                ];

                if ($itemCode !== '') {
                    $inventoryItem = InventoryItem::updateOrCreate(
                        ['item_code' => $itemCode],
                        $payload
                    );
                } else {
                    $inventoryItem = InventoryItem::create($payload);
                }

                if ($inventoryItem->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                $this->createAutoRestockRequestIfNeeded($inventoryItem->fresh());
            }
        });

        fclose($handle);

        return redirect()
            ->route('inventory')
            ->with('success', "Inventory import completed. Created: {$created}, Updated: {$updated}.");
    }

    private function syncAutoRestockRequests(): void
    {
        $lowStockItems = InventoryItem::query()
            ->whereColumn('on_hand', '<=', 'reorder_level')
            ->where('reorder_level', '>', 0)
            ->get();

        foreach ($lowStockItems as $inventoryItem) {
            $this->createAutoRestockRequestIfNeeded($inventoryItem);
        }
    }

    private function createAutoRestockRequestIfNeeded(InventoryItem $inventoryItem): void
    {
        $itemName = $this->getInventoryItemName($inventoryItem);
        $unit = $this->getInventoryUnit($inventoryItem);

        $onHand = (int) ($inventoryItem->on_hand ?? $inventoryItem->quantity_available ?? 0);
        $reorderLevel = (int) ($inventoryItem->reorder_level ?? 0);

        if ($itemName === '') {
            return;
        }

        if ($reorderLevel <= 0) {
            return;
        }

        if ($onHand > $reorderLevel) {
            return;
        }

        $existingActiveRequest = MaintenanceRequest::query()
            ->where('source_type', 'Auto Restock')
            ->where(function ($q) use ($itemName) {
                $q->where('item', 'like', "{$itemName}%")
                    ->orWhere('remarks', 'like', "%{$itemName}%");
            })
            ->whereIn('status', [
                'For Purchase',
                'Ordered',
                'For Pick-up',
                'For Delivery',
                'Delivered',
                'Picked Up',
            ])
            ->exists();

        if ($existingActiveRequest) {
            return;
        }

        $neededQuantity = max($reorderLevel - $onHand, 1);

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT:
        |--------------------------------------------------------------------------
        | Your purchase_requests table has NO `unit` column
        | and NO `date_requested` column.
        |
        | So this insert only uses columns that exist in your table.
        | Unit is saved inside item text:
        | Engine Oil - Qty: 10 liter
        |--------------------------------------------------------------------------
        */
        MaintenanceRequest::create([
            'pr_no' => $this->generateRestockPrNo(),
            'job_order_no' => 'RESTOCK',
            'bus_no' => 'RESTOCK',
            'item' => "{$itemName} - Qty: {$neededQuantity} {$unit}",
            'quantity' => $neededQuantity,
            'status' => 'For Purchase',
            'source_type' => 'Auto Restock',
            'remarks' => "Auto restock request from Warehouse Inventory. {$itemName} is below reorder level. Current stock: {$onHand} {$unit}. Reorder level: {$reorderLevel} {$unit}.",
        ]);
    }

    private function generateRestockPrNo(): string
    {
        $year = now()->format('Y');

        $latest = MaintenanceRequest::query()
            ->where('pr_no', 'like', "RST-{$year}-%")
            ->orderByDesc('id')
            ->first();

        if (! $latest || ! $latest->pr_no) {
            return "RST-{$year}-0001";
        }

        $lastNumber = (int) substr($latest->pr_no, -4);
        $nextNumber = $lastNumber + 1;

        return "RST-{$year}-" . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function inventoryStatus(int $onHand, int $reorderLevel): string
    {
        if ($onHand <= 0) {
            return 'Critical';
        }

        if ($reorderLevel > 0 && $onHand <= $reorderLevel) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    private function getInventoryItemName(InventoryItem $inventoryItem): string
    {
        return trim(
            $inventoryItem->parts_name
            ?? $inventoryItem->item_name
            ?? ''
        );
    }

    private function getInventoryUnit(InventoryItem $inventoryItem): string
    {
        return trim(
            $inventoryItem->unit
            ?? $inventoryItem->unit_of_measurement
            ?? 'pcs'
        ) ?: 'pcs';
    }
}