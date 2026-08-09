<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Warehouse\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        return view('Warehouse.stock-movements', $this->data($request));
    }

    public function data(Request $request): array
    {
        $movementQuery = StockMovement::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $movementQuery->where(function ($query) use ($search) {
                $query->where('item_name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('movement_type', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type') && $request->type !== 'All Types') {
            $movementQuery->where('movement_type', $request->type);
        }

        if ($request->date_filter === 'Today') {
            $movementQuery->whereDate('created_at', today());
        } elseif ($request->date_filter === 'This Week') {
            $movementQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($request->date_filter === 'This Month') {
            $movementQuery->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month);
        }

        $stockMovements = $movementQuery->latest()->paginate(20)->withQueryString();
        $totalMovements = StockMovement::count();
        $stockIn = StockMovement::where('movement_type', 'Stock In')->count();
        $stockOut = StockMovement::where('movement_type', 'Stock Out')->count();
        $adjustments = StockMovement::where('movement_type', 'Adjustment')->count();

        return compact('stockMovements', 'totalMovements', 'stockIn', 'stockOut', 'adjustments');
    }
}
