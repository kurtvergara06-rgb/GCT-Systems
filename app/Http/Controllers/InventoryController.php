<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->filled('search')) {
            $search = $request->search;

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

        $inventoryItems = $query->latest()->paginate(8)->withQueryString();

        $categories = InventoryItem::select('category')
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
                    'item_code' => $row[0] ?? null,
                ],
                [
                    'item_name' => $row[1] ?? '',
                    'category' => $row[2] ?? '',
                    'quantity_available' => (int) ($row[3] ?? 0),
                    'unit_of_measurement' => $row[4] ?? 'pcs',
                    'reorder_level' => (int) ($row[5] ?? 0),
                    'supplier' => $row[6] ?? null,
                    'storage_location' => $row[7] ?? null,
                ]
            );
        }

        fclose($file);

        return redirect()
            ->route('inventory')
            ->with('success', 'Inventory data imported successfully.');
    }
}