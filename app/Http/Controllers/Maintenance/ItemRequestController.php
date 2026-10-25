<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\JobOrder;
use App\Services\PartParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemRequestController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->query('view') === 'history' ? 'history' : 'active';
        $historyStatuses = ['Issued', 'Completed', 'Cancelled', 'Rejected'];
        $requestsQuery = ItemRequest::with('jobOrder');
        if ($view === 'history') {
            $requestsQuery->whereIn('status', $historyStatuses);
        } else {
            $requestsQuery->whereNotIn('status', $historyStatuses);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $requestsQuery->where(function ($query) use ($search) {
                $query->whereHas('jobOrder', function ($jo) use ($search) {
                    $jo->where('job_order_no', 'like', '%'.$search.'%')
                        ->orWhere('bus_no', 'like', '%'.$search.'%');
                })->orWhere('requested_parts', 'like', '%'.$search.'%');
            });
        }
        if ($request->filled('status') && $request->query('status') !== 'All Statuses') {
            $requestsQuery->where('status', $request->query('status'));
        }
        $sort = $request->query('sort') === 'oldest' ? 'asc' : 'desc';
        $requests = $requestsQuery->orderBy('created_at', $sort)->paginate(10)->withQueryString();
        $totalCount = ItemRequest::count();
        $submittedCount = ItemRequest::where('status', 'Submitted')->count();
        $activeCount = ItemRequest::whereNotIn('status', $historyStatuses)->count();
        $historyCount = ItemRequest::whereIn('status', $historyStatuses)->count();
        $eligibleJobs = JobOrder::query()
            ->where('review_status', 'Approved')
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->whereNotIn('id', ItemRequest::query()->select('job_order_id'))
            ->orderByDesc('created_at')
            ->get(['id', 'job_order_no', 'bus_no', 'part_needed']);

        return view('Maintenance.item-requests', compact('requests', 'eligibleJobs', 'view', 'totalCount', 'submittedCount', 'activeCount', 'historyCount'));
    }

    public function store(Request $request, PartParser $parser)
    {
        $data = $request->validate([
            'job_order_id' => ['required', 'integer', 'exists:job_orders,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $parser): void {
            $jo = JobOrder::query()->lockForUpdate()->findOrFail($data['job_order_id']);
            if ($jo->review_status !== 'Approved') {
                throw ValidationException::withMessages(['job_order_id' => 'The Job Order must be approved before creating an Item Request.']);
            }
            if (ItemRequest::query()->where('job_order_id', $jo->id)->exists()) {
                throw ValidationException::withMessages(['job_order_id' => 'This Job Order already has an Item Request.']);
            }
            $parts = $parser->parsePartText((string) $jo->part_needed);
            if ($parts === []) {
                throw ValidationException::withMessages(['job_order_id' => 'The Job Order has no requested parts.']);
            }
            ItemRequest::create([
                'job_order_id' => $jo->id,
                'requested_by' => auth()->id(),
                'status' => 'Submitted',
                'requested_parts' => $parts,
                'remarks' => $data['remarks'] ?? null,
            ]);
        });

        return redirect()->route('item-requests')->with('success', 'Item Request submitted for Warehouse stock verification.');
    }
}
