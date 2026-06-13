<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                    ->orWhere('item_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%")
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

        $categories = InventoryItem::select('category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $totalItemsInStock = InventoryItem::sum('quantity_available');

        $lowStockAlerts = InventoryItem::whereColumn('quantity_available', '<=', 'reorder_level')
            ->where('quantity_available', '>', 0)
            ->count();

        $criticalItems = InventoryItem::where('quantity_available', '<=', 0)->count();

        $forecastedStockouts = InventoryItem::whereColumn('quantity_available', '<=', 'reorder_level')->count();

        return view('Warehouse.inventory', compact(
            'inventoryItems',
            'categories',
            'totalItemsInStock',
            'lowStockAlerts',
            'criticalItems',
            'forecastedStockouts'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_code' => 'required|string|max:255|unique:inventory_items,item_code',
            'item_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'quantity_available' => 'required|integer|min:0',
            'unit_of_measurement' => 'required|string|max:255',
            'reorder_level' => 'required|integer|min:0',
            'supplier' => 'nullable|string|max:255',
            'storage_location' => 'nullable|string|max:255',
        ]);

        $validated['unit_of_measurement'] = $this->normalizeUnit($validated['unit_of_measurement']);

        InventoryItem::create($validated);

        return redirect()
            ->route('inventory')
            ->with('success', 'Inventory item added successfully.');
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $validated = $request->validate([
            'item_code' => 'required|string|max:255|unique:inventory_items,item_code,' . $inventoryItem->id,
            'item_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'quantity_available' => 'required|integer|min:0',
            'unit_of_measurement' => 'required|string|max:255',
            'reorder_level' => 'required|integer|min:0',
            'supplier' => 'nullable|string|max:255',
            'storage_location' => 'nullable|string|max:255',
        ]);

        $validated['unit_of_measurement'] = $this->normalizeUnit($validated['unit_of_measurement']);

        $inventoryItem->update($validated);

        return redirect()
            ->route('inventory')
            ->with('success', 'Inventory item updated successfully.');
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return redirect()
            ->route('inventory')
            ->with('success', 'Inventory item deleted successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = fopen($request->file('import_file')->getRealPath(), 'r');

        $header = fgetcsv($file);

        while (($row = fgetcsv($file)) !== false) {
            InventoryItem::updateOrCreate(
                [
                    'item_code' => trim($row[0] ?? ''),
                ],
                [
                    'item_name' => trim($row[1] ?? ''),
                    'category' => trim($row[2] ?? ''),
                    'quantity_available' => (int) ($row[3] ?? 0),
                    'unit_of_measurement' => $this->normalizeUnit($row[4] ?? 'pcs'),
                    'reorder_level' => (int) ($row[5] ?? 0),
                    'supplier' => trim($row[6] ?? '') ?: null,
                    'storage_location' => trim($row[7] ?? '') ?: null,
                ]
            );
        }

        fclose($file);

        return redirect()
            ->route('inventory')
            ->with('success', 'Inventory data imported successfully.');
    }

    private function normalizeUnit(?string $unit): string
    {
        $unit = strtolower(trim($unit ?? 'pcs'));
        $unit = preg_replace('/\s+/', ' ', $unit);

        return match ($unit) {
            'liter', 'liters', 'litre', 'litres', 'ltr', 'ltrs', 'l' => 'liter',
            'piece', 'pieces', 'pc', 'pcs' => 'pcs',
            'set', 'sets' => 'set',
            'bottle', 'bottles' => 'bottle',
            'box', 'boxes' => 'box',
            'pack', 'packs' => 'pack',
            'pair', 'pairs' => 'pair',
            'roll', 'rolls' => 'roll',
            'tube', 'tubes' => 'tube',
            'gallon', 'gallons', 'gal' => 'gallon',
            'meter', 'meters', 'm' => 'meter',
            'kg', 'kilogram', 'kilograms' => 'kg',
            default => $unit ?: 'pcs',
        };
    }
}