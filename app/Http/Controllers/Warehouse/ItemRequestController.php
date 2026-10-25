<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\ItemRequest;
use Illuminate\Http\Request;

class ItemRequestController extends Controller
{
    public function index(Request $request)
    {
        $historyStatuses = ['Issued', 'Completed', 'Cancelled', 'Rejected'];
        $view = $request->query('view') === 'history' ? 'history' : 'active';
        $query = ItemRequest::with('jobOrder');

        if ($view === 'history') {
            $query->whereIn('status', $historyStatuses);
        } else {
            $query->whereNotIn('status', $historyStatuses);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $irId = preg_match('/^IR-?0*(\\d+)$/i', $search, $matches) ? (int) $matches[1] : null;
            $query->where(function ($nested) use ($search, $irId) {
                if ($irId !== null) {
                    $nested->orWhere('id', $irId);
                }
                $nested->whereHas('jobOrder', fn ($jo) => $jo
                    ->where('job_order_no', 'like', '%'.$search.'%')
                    ->orWhere('bus_no', 'like', '%'.$search.'%'))
                    ->orWhere('requested_parts', 'like', '%'.$search.'%');
            });
        }
        if ($request->filled('status') && $request->query('status') !== 'All Statuses') {
            $query->where('status', $request->query('status'));
        }

        $sort = (string) $request->query('sort', 'created_at');
        if (! in_array($sort, ['created_at', 'id', 'status'], true)) {
            $sort = 'created_at';
        }
        $direction = $request->query('sort_direction', $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $requests = $query->orderBy($sort, $direction)->orderBy('id', 'desc')->paginate(10)->withQueryString();

        $totalCount = ItemRequest::count();
        $submittedCount = ItemRequest::where('status', 'Submitted')->count();
        $activeCount = ItemRequest::whereNotIn('status', $historyStatuses)->count();
        $historyCount = ItemRequest::whereIn('status', $historyStatuses)->count();

        return view('Warehouse.item-requests', compact(
            'requests', 'view', 'totalCount', 'submittedCount', 'activeCount', 'historyCount'
        ));
    }
}
