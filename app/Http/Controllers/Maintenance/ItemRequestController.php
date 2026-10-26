<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\JobOrder;
use App\Services\PartParser;
use App\Services\Maintenance\JobOrderWorkflowService;
use App\Services\Maintenance\ItemRequestPartsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemRequestController extends Controller
{
    public function index(Request $request, PartParser $parser, JobOrderWorkflowService $workflow, ItemRequestPartsService $irParts)
    {
        $view = in_array($request->query('view'), ['pending', 'history'], true) ? $request->query('view') : 'active';
        $historyStatuses = ['Issued', 'Completed', 'Cancelled', 'Rejected'];
        $requestsQuery = ItemRequest::with('jobOrder');
        if ($view === 'history') {
            $requestsQuery->whereIn('status', $historyStatuses);
        } else {
            $requestsQuery->whereNotIn('status', $historyStatuses);
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $irSearchId = preg_match('/^IR-?0*(\\d+)$/i', $search, $matches) ? (int) $matches[1] : null;
            $requestsQuery->where(function ($query) use ($search, $irSearchId) {
                if ($irSearchId !== null) {
                    $query->orWhere('id', $irSearchId);
                }
                $query->whereHas('jobOrder', function ($jo) use ($search) {
                    $jo->where('job_order_no', 'like', '%'.$search.'%')
                        ->orWhere('bus_no', 'like', '%'.$search.'%');
                })->orWhere('requested_parts', 'like', '%'.$search.'%');
            });
        }
        if ($request->filled('status') && $request->query('status') !== 'All Statuses') {
            $requestsQuery->where('status', $request->query('status'));
        }
        $sortField = (string) $request->query('sort', 'created_at');
        if (! in_array($sortField, ['created_at', 'status', 'id'], true)) {
            $sortField = 'created_at';
        }
        $sortDirection = (string) $request->query('sort_direction', $request->query('direction', 'desc'));
        $sortDirection = $sortDirection === 'asc' ? 'asc' : 'desc';
        $requests = $requestsQuery->orderBy($sortField, $sortDirection)->orderBy('id', 'desc')->paginate(10)->withQueryString();
        $totalCount = ItemRequest::count();
        $submittedCount = ItemRequest::where('status', 'Submitted')->count();
        $activeCount = ItemRequest::whereNotIn('status', $historyStatuses)->count();
        $historyCount = ItemRequest::whereIn('status', $historyStatuses)->count();
        $candidateJobs = JobOrder::query()
            ->where('review_status', 'Approved')
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->whereNotIn('id', ItemRequest::query()->select('job_order_id'))
            ->orderByDesc('created_at')
            ->get(['id', 'job_order_no', 'bus_no', 'assigned_mechanic', 'part_needed']);
        $eligibleJobs = $candidateJobs->filter(function (JobOrder $job) use ($irParts): bool {
            try {
                return $irParts->availableParts((string) $job->part_needed) !== [];
            } catch (ValidationException) {
                return false;
            }
        })->values();
        $jobOrderPreviews = $eligibleJobs->mapWithKeys(function (JobOrder $job) use ($irParts) {
            return [$job->id => [
                'jo' => $job->job_order_no,
                'bus' => $job->bus_no,
                'mechanic' => $job->assigned_mechanic ?: 'Not assigned',
                'parts' => $irParts->availableParts((string) $job->part_needed),
            ]];
        });

        return view('Maintenance.item-requests', compact('requests', 'eligibleJobs', 'view', 'totalCount', 'submittedCount', 'activeCount', 'historyCount', 'jobOrderPreviews'));
    }

    public function store(Request $request, ItemRequestPartsService $irParts)
    {
        $data = $request->validate([
            'job_order_id' => ['required', 'integer', 'exists:job_orders,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $irParts): void {
            $jo = JobOrder::query()->lockForUpdate()->findOrFail($data['job_order_id']);
            if ($jo->review_status !== 'Approved') {
                throw ValidationException::withMessages(['job_order_id' => 'The Job Order must be approved before creating an Item Request.']);
            }
            if (ItemRequest::query()->where('job_order_id', $jo->id)->exists()) {
                throw ValidationException::withMessages(['job_order_id' => 'This Job Order already has an Item Request.']);
            }
            $parts = $irParts->availableParts((string) $jo->part_needed);
            if ($parts === []) {
                throw ValidationException::withMessages(['job_order_id' => 'No available quantities remain for this Job Order. Missing quantities belong in a Purchase Request.']);
            }
            ItemRequest::create([
                'job_order_id' => $jo->id,
                'requested_by' => auth()->id(),
                'status' => 'Submitted',
                'requested_parts' => $parts,
                'remarks' => $data['remarks'] ?? null,
            ]);
            // An IR is the operational request for JO parts. Creating it
            // transitions the JO away from "Not Requested" without implying
            // that Warehouse has issued or reserved anything.
            if (in_array($jo->part_status, [null, 'Not Requested'], true)) {
                $jo->update(['part_status' => 'Submitted']);
            }
        });

        return redirect()->route('item-requests')->with('success', 'Item Request submitted for Warehouse stock verification.');
    }
}
