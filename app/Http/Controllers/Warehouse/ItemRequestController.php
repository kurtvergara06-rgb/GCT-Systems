<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\ItemRequest;

class ItemRequestController extends Controller
{
    public function index()
    {
        $requests = ItemRequest::with('jobOrder')->latest()->paginate(10);

        return view('Warehouse.item-requests', compact('requests'));
    }
}
