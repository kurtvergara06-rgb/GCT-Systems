<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\StockMovement;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function show(Request $request, InventoryItem $inventoryItem)
    {
        $query = $inventoryItem
            ->movements();

        if ($request->filled('type') && $request->type !== 'All Types') {
            $query->where('movement_type', $request->type);
        }

        $movements = $query->paginate(20)->withQueryString();

        $stockIn = StockMovement::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('movement_type', 'Stock In')
            ->count();

        $stockOut = StockMovement::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('movement_type', 'Stock Out')
            ->count();

        $adjustments = StockMovement::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('movement_type', 'Adjustment')
            ->count();

        $simulatedMovements = StockMovement::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('source', 'simulated')
            ->count();

        $data = compact(
            'inventoryItem',
            'movements',
            'stockIn',
            'stockOut',
            'adjustments',
            'simulatedMovements'
        );

        if ($request->ajax() || $request->boolean('modal')) {
            return view(
                'Warehouse.partials.inventory-movement-history',
                $data + ['isModal' => true]
            );
        }

        return view('Warehouse.inventory-movements', $data);
    }
}
