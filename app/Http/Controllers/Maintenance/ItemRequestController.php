<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Warehouse\InventoryIssuance;
use App\Services\PartParser;
use App\Services\Maintenance\JobOrderWorkflowService;
use App\Services\Maintenance\ItemRequestPartsService;
use App\Services\Maintenance\PurchaseRequestPartsService;
use App\Services\Maintenance\PartsReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemRequestController extends Controller
{
    public function index(Request $request, PartParser $parser, JobOrderWorkflowService $workflow, ItemRequestPartsService $irParts, PurchaseRequestPartsService $prParts)
    {
        $view = in_array($request->query('view'), ['pending', 'history'], true) ? $request->query('view') : 'active';
        $historyStatuses = ['Issued', 'Completed', 'Cancelled', 'Rejected'];
        $requestsQuery = ItemRequest::with(['jobOrder.approver', 'requester']);
        // Match Warehouse: a signed/Issued IR remains read-only but visible
        // in Active while its linked Job Order still has outstanding parts.
        $scopeHistory = static function ($query) use ($historyStatuses) {
            $query->where(function ($q) use ($historyStatuses) {
                $q->whereIn('status', array_diff($historyStatuses, ['Issued']))
                    ->orWhere(fn ($issued) => $issued->where('status', 'Issued')
                        ->whereDoesntHave('jobOrder', fn ($jo) => $jo
                            ->whereNull('part_status')->orWhere('part_status', '!=', 'Issued')));
            });
        };
        $scopeActive = static function ($query) use ($historyStatuses) {
            $query->where(function ($q) use ($historyStatuses) {
                $q->whereNotIn('status', $historyStatuses)
                    ->orWhere(fn ($issued) => $issued->where('status', 'Issued')
                        ->whereHas('jobOrder', fn ($jo) => $jo
                            ->whereNull('part_status')->orWhere('part_status', '!=', 'Issued')));
            });
        };
        ($view === 'history' ? $scopeHistory : $scopeActive)($requestsQuery);
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
        // Use the actual persisted Purchase Requests, not live inventory, for
        // the PR breakdown on submitted IRs (stable even after stock changes).
        $linkedPrPartsByJo = PurchaseRequest::query()
            ->whereIn('job_order_no', $requests->getCollection()->pluck('jobOrder.job_order_no')->filter()->unique()->values())
            ->whereNotIn('status', ['Cancelled', 'Rejected'])
            ->get(['job_order_no', 'pr_no', 'item', 'status'])
            ->groupBy('job_order_no')
            ->map(fn ($prs) => $prs->flatMap(fn ($pr) => collect($parser->parsePartText((string) $pr->item))
                ->map(fn ($part) => array_merge($part, [
                    'pr_no' => $pr->pr_no,
                    'status' => $pr->status,
                ])))->values()->all());
        // Derive JO-wide fulfillment from signed Warehouse issuance batches,
        // not from the IR status or the requested quantity.
        $pageJobs = $requests->getCollection()->pluck('job_order_id')->filter()->unique()->values();
        $relatedIrs = ItemRequest::query()->whereIn('job_order_id', $pageJobs)->get(['id', 'job_order_id', 'created_at']);
        $references = $relatedIrs->mapWithKeys(fn ($ir) => [
            'IR-'.($ir->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $ir->id, 4, '0', STR_PAD_LEFT) => $ir->job_order_id,
        ]);
        $batches = $references->isEmpty() ? collect() : InventoryIssuance::query()
            ->where(function ($query) use ($references) {
                foreach ($references->keys() as $ref) {
                    $query->orWhere('reference_no', $ref)->orWhere('reference_no', 'like', $ref.'-B%');
                }
            })->with('items')->get();
        $normalizePart = fn ($name, $unit) => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $name))).'|'.$parser->normalizeUnit((string) $unit);
        $issuedByJo = [];
        foreach ($batches as $batch) {
            $ref = preg_replace('/-B\\d+$/', '', (string) $batch->reference_no);
            $joId = $references->get($ref);
            if (! $joId) continue;
            foreach ($batch->items as $item) {
                $key = $normalizePart($item->item_name, $item->unit);
                $issuedByJo[$joId][$key] = ($issuedByJo[$joId][$key] ?? 0) + (int) $item->quantity;
            }
        }
        $fulfillmentByJob = $requests->getCollection()->filter(fn ($ir) => $ir->jobOrder)
            ->unique('job_order_id')->mapWithKeys(function ($ir) use ($parser, $normalizePart, $issuedByJo) {
                $required = collect($parser->parsePartText((string) $ir->jobOrder->part_needed))
                    ->groupBy(fn ($part) => $normalizePart($part['name'], $part['unit']))
                    ->map(fn ($rows) => $rows->sum(fn ($part) => (int) $part['quantity']));
                $issued = $required->sum(fn ($quantity, $key) =>
                    min((int) $quantity, (int) ($issuedByJo[$ir->job_order_id][$key] ?? 0)));
                return [$ir->job_order_id => [
                    'required' => $required->sum(),
                    'issued' => $issued,
                    'remaining' => max(0, $required->sum() - $issued),
                ]];
            });
        $totalCount = ItemRequest::count();
        $submittedCount = ItemRequest::where('status', 'Submitted')->count();
        $activeCount = tap(ItemRequest::query(), $scopeActive)->count();
        $historyCount = tap(ItemRequest::query(), $scopeHistory)->count();
        $candidateJobs = JobOrder::query()
            ->where('review_status', 'Approved')
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->whereNotIn('id', ItemRequest::query()->whereNotIn('status', ['Cancelled', 'Rejected'])->select('job_order_id'))
            ->where(fn ($query) => $query->whereNull('part_status')
                ->orWhereNotIn('part_status', ['Issued', 'No Parts Required']))
            ->with(['approver', 'audits' => fn ($q) => $q->whereIn('action', ['created', 'resubmitted'])->orderByDesc('id')])
            ->orderByDesc('created_at')
            ->get(['id', 'job_order_no', 'bus_no', 'assigned_mechanic', 'part_needed', 'parts_issue_strategy', 'inventory_snapshot', 'part_status', 'created_at', 'approved_by']);
        $issuedQuantitiesByJo = ItemRequest::query()
            ->whereIn('job_order_id', $candidateJobs->pluck('id'))
            ->where('status', 'Issued')->get()
            ->groupBy('job_order_id')
            ->map(fn ($requests) => $requests->sum(fn ($ir) =>
                collect($ir->requested_parts ?? [])->sum(fn ($part) => (int) ($part['quantity'] ?? 0))));
        $eligibleJobs = $candidateJobs->filter(function (JobOrder $job) use ($irParts): bool {
            try {
                return $irParts->availableParts((string) $job->part_needed, $job) !== [];
            } catch (ValidationException) {
                return false;
            }
        })->values();
        $jobOrderPreviews = $eligibleJobs->mapWithKeys(function (JobOrder $job) use ($irParts, $prParts, $issuedQuantitiesByJo) {
            return [$job->id => [
                'jo' => $job->job_order_no,
                'previously_issued' => (int) $issuedQuantitiesByJo->get($job->id, 0),
                'bus' => $job->bus_no,
                'mechanic' => $job->assigned_mechanic ?: 'Not assigned',
                'approver' => $job->approver?->name ?? 'Not recorded',
                'signature_url' => route('job-orders.approval-signature', $job, false),
                'parts' => $irParts->availableParts((string) $job->part_needed, $job),
                'missing_parts' => $prParts->missingParts((string) $job->part_needed, $job),
            ]];
        });

        // Search and sort the virtual pending suggestions independently of
        // the persisted Active/History records.
        $pendingJobs = $eligibleJobs;
        if ($request->filled('search')) {
            $needle = mb_strtolower(trim((string) $request->query('search')));
            $pendingJobs = $pendingJobs->filter(function (JobOrder $job) use ($needle, $jobOrderPreviews): bool {
                $parts = $jobOrderPreviews->get($job->id)['parts'] ?? [];
                $haystack = mb_strtolower($job->job_order_no.' '.$job->bus_no.' '.
                    collect($parts)->pluck('name')->implode(' '));
                return str_contains($haystack, $needle);
            })->values();
        }
        if ($request->filled('status') && $request->query('status') !== 'All Statuses') {
            $pendingJobs = $pendingJobs->filter(function (JobOrder $job) use ($request, $parser, $jobOrderPreviews): bool {
                $all = $parser->parsePartText((string) $job->part_needed);
                $parts = $jobOrderPreviews->get($job->id)['parts'] ?? [];
                $remaining = max(0, collect($all)->sum('quantity') - (int) ($jobOrderPreviews->get($job->id)['previously_issued'] ?? 0));
                $blocked = $remaining > collect($parts)->sum('quantity')
                    && $job->parts_issue_strategy !== 'issue_available_only';
                return $request->query('status') === ($blocked ? 'Waiting for Complete Stock' : 'Pending Review');
            })->values();
        }
        if ($sortField === 'id') {
            $pendingJobs = $pendingJobs->sortBy('id', SORT_REGULAR, $sortDirection === 'desc')->values();
        } elseif ($sortField === 'created_at') {
            $pendingJobs = $pendingJobs->sortBy('created_at', SORT_REGULAR, $sortDirection === 'desc')->values();
        }

        // Show actionable suggestions first on ordinary sidebar navigation.
        // Explicit Active/History/Pending links always keep their requested tab.
        if (! $request->has('view') && $eligibleJobs->isNotEmpty()) {
            $view = 'pending';
        }

        return view('Maintenance.item-requests', compact('requests', 'eligibleJobs', 'view', 'totalCount', 'submittedCount', 'activeCount', 'historyCount', 'jobOrderPreviews', 'pendingJobs', 'linkedPrPartsByJo', 'fulfillmentByJob'));
    }

    public function store(Request $request, ItemRequestPartsService $irParts, PartParser $parser, PartsReservationService $reservations, PurchaseRequestPartsService $prParts)
    {
        $data = $request->validate([
            'job_order_id' => ['required', 'integer', 'exists:job_orders,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $irParts, $parser, $reservations, $prParts): void {
            $jo = JobOrder::query()->lockForUpdate()->findOrFail($data['job_order_id']);
            if ($jo->review_status !== 'Approved' || $jo->status === 'Completed') {
                throw ValidationException::withMessages(['job_order_id' => 'The Job Order must be approved and open before creating an Item Request or Purchase Request.']);
            }
            if (ItemRequest::query()->where('job_order_id', $jo->id)
                ->whereNotIn('status', ['Cancelled', 'Rejected'])->exists()) {
                throw ValidationException::withMessages(['job_order_id' => 'This Job Order already has an Item Request. Warehouse handles subsequent physical handovers on that same IR.']);
            }
            $reservations->topUp($jo);
            $parts = $irParts->availableParts((string) $jo->part_needed, $jo);
            // Splitting a JO into IR + PR must be authorized by the Head.
            // Default JO strategy waits until all parts can be supplied.
            $fullRequired = collect($parser->parsePartText((string) $jo->part_needed))->sum('quantity');
            $availableForIr = collect($parts)->sum('quantity');
            $previouslyIssued = ItemRequest::query()
                ->where('job_order_id', $jo->id)->where('status', 'Issued')->get()
                ->sum(fn ($prior) => collect($prior->requested_parts ?? [])
                    ->sum(fn ($part) => (int) ($part['quantity'] ?? 0)));
            $remainingJoQuantity = max(0, $fullRequired - $previouslyIssued);
            // A shortage must be split between available IR quantities and
            // missing PR quantities. The Head's release policy governs
            // physical Warehouse issuance, not creation of the IR.
            // Zero-stock JOs still need a procurement path, but must never
            // create an empty IR that Warehouse cannot physically fulfill.
            if ($parts !== []) {
                ItemRequest::create([
                    'job_order_id' => $jo->id,
                    'requested_by' => auth()->id(),
                    'status' => 'Submitted',
                    'requested_parts' => $parts,
                    'remarks' => $data['remarks'] ?? null,
                ]);
                if (in_array($jo->part_status, [null, 'Not Requested'], true)) {
                    $jo->update(['part_status' => 'Submitted']);
                }
            }

            // IR submission must not create or submit a Purchase Request.
            // Staff submits shortages independently on Purchase Requisitions.
            // Reject IR-only submissions when no allocatable quantities exist.
            if ($parts === []) {
                throw ValidationException::withMessages([
                    'job_order_id' => 'No available parts remain for an Item Request. Submit a Purchase Requisition separately for the shortages.',
                ]);
            }

        });

        return redirect()->route('item-requests', ['view' => 'active'])->with('success', 'Item Request submitted for available parts. Submit any Purchase Requisition separately.');
    }
}
