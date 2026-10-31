<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Maintenance\JobOrderPartReservation;
use Illuminate\Http\Request;
use App\Services\Maintenance\JobOrderWorkflowService;
use App\Models\Warehouse\InventoryItem;
use App\Models\Warehouse\InventoryIssuance;
use App\Models\Warehouse\InventoryIssuanceItem;
use App\Services\Warehouse\InventoryLedgerService;
use App\Services\PartParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ItemRequestController extends Controller
{

    /**
     * Read-only, up-to-date snapshot. Never reserve or deduct stock from a preview.
     */
    public function verify(ItemRequest $itemRequest, JobOrderWorkflowService $workflow, PartParser $parser)
    {
        // An IR is the durable parent of all JO handovers. After its first
        // signed batch, track remaining JO requirements without rewriting
        // the originally submitted request or any signed issuance record.
        $irNumber = 'IR-'.($itemRequest->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $itemRequest->id, 4, '0', STR_PAD_LEFT);
        $hasHandover = InventoryIssuance::query()->where(fn ($q) => $q
            ->where('reference_no', $irNumber)->orWhere('reference_no', 'like', $irNumber.'-B%'))->exists();
        $parts = $hasHandover && $itemRequest->jobOrder
            ? $parser->parsePartText((string) $itemRequest->jobOrder->part_needed)
            : ($itemRequest->requested_parts ?? []);
        $snapshot = $workflow->inventoryAvailability($parts);
        $previous = InventoryIssuance::query()->where(fn ($query) => $query
            ->where('reference_no', $irNumber)->orWhere('reference_no', 'like', $irNumber.'-B%'))
            ->with('items')->get();
        $key = static fn ($name, $unit, $id = null): string => $id
            ? 'id:'.(int) $id
            : mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $name))).'|'.$parser->normalizeUnit((string) $unit);
        $issued = [];
        foreach ($previous as $batch) {
            foreach ($batch->items as $entry) {
                $k = $key($entry->item_name, $entry->unit, $entry->inventory_item_id);
                $issued[$k] = ($issued[$k] ?? 0) + (int) $entry->quantity;
            }
        }
        $own = JobOrderPartReservation::query()->where('job_order_id', $itemRequest->job_order_id)->pluck('quantity', 'inventory_item_id');
        $others = JobOrderPartReservation::query()->where('job_order_id', '!=', $itemRequest->job_order_id)
            ->select('inventory_item_id', DB::raw('SUM(quantity) as qty'))->groupBy('inventory_item_id')->pluck('qty', 'inventory_item_id');
        $hasAllocation = isset($itemRequest->jobOrder?->inventory_snapshot['shortages']);
        $physicalItems = InventoryItem::query()->get()->keyBy('id');
        foreach ($snapshot['parts'] as &$part) {
            $id = $part['inventory_item_id'] ?? null;
            $quantity = (int) ($part['quantity'] ?? 0);
            $part['requested_total'] = $quantity;
            $partKey = $key($part['name'], $part['unit'], $part['inventory_item_id'] ?? null);
            $already = min($quantity, (int) ($issued[$partKey] ?? 0));
            $issued[$partKey] = max(0, (int) ($issued[$partKey] ?? 0) - $already);
            $part['already_issued'] = $already;
            $part['quantity'] = max(0, $quantity - $already);
            $quantity = $part['quantity'];
            $item = $physicalItems->get($id);
            $physical = $item ? (int) ($item->quantity_available ?? $item->on_hand ?? 0) : 0;
            $part['on_hand'] = max(0, $physical);
            $free = max(0, $physical - (int) $others->get($id, 0));
            $allocated = (int) $own->get($id, 0);
            $part['available'] = $hasAllocation ? min($free, $allocated) : $free;
            $part['shortage'] = max(0, $quantity - $part['available']);
            $part['fulfillable'] = $part['available'] >= $quantity;
        }
        unset($part);
        $canIssue = collect($snapshot['parts'])->every(fn ($part) => $part['fulfillable']);
        $anyStock = collect($snapshot['parts'])->contains(fn ($part) => $part['available'] > 0);
        $snapshot['status'] = $canIssue ? 'sufficient' : ($anyStock ? 'partial' : 'none');
        $codes = InventoryItem::query()->get(['id', 'item_code'])->keyBy('id');

        return response()->json([
            'request_id' => $itemRequest->id,
            'status' => $snapshot['status'],
            'release_policy' => $itemRequest->jobOrder?->parts_issue_strategy ?? 'complete_before_issuance',
            'reserved_stock' => $hasAllocation,
            'parts' => collect($snapshot['parts'])->map(fn (array $part) => [
                'name' => $part['name'],
                'stock_no' => $codes->get($part['inventory_item_id'] ?? null)?->item_code,
                'quantity' => $part['quantity'],
                'requested_total' => $part['requested_total'],
                'already_issued' => $part['already_issued'],
                'unit' => $part['unit'],
                'available' => $part['available'],
                'on_hand' => $part['on_hand'],
                'shortage' => $part['shortage'],
                'fulfillable' => $part['fulfillable'],
            ])->values(),
            'checked_at' => now(config('app.business_timezone', 'Asia/Manila'))->toIso8601String(),
            'note' => 'Availability is informational. Verify again during actual issuance.',
        ]);
    }


    /**
     * Signed physical handover. Supports cumulative partial batches while
     * enforcing the Maintenance Head policy and JO-scoped stock allocations.
     */
    public function issue(Request $request, ItemRequest $itemRequest, InventoryLedgerService $ledger, PartParser $parser)
    {
        $validated = $request->validate([
            'received_by' => ['required', 'string', 'max:255'],
            'signed_slip' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'handover_confirmed' => ['required', 'accepted'],
            'issued_quantities' => ['required', 'array', 'min:1'],
            'issued_quantities.*' => ['required', 'integer', 'min:0'],
        ]);

        $slipPath = $request->file('signed_slip')->store('warehouse/signed-ir-slips', 'local');
        try {
            $irNumber = 'IR-'.($itemRequest->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $itemRequest->id, 4, '0', STR_PAD_LEFT);
            DB::transaction(function () use ($itemRequest, $ledger, $parser, $irNumber, $slipPath, $validated): void {
                $ir = ItemRequest::query()->whereKey($itemRequest->id)->lockForUpdate()->firstOrFail();
                if (! in_array($ir->status, ['Submitted', 'Partially Issued', 'Issued'], true)) {
                    throw ValidationException::withMessages(['issue' => 'This IR is not ready for issuance.']);
                }
                $jo = $ir->jobOrder;
                if (! $jo || $jo->review_status !== 'Approved') {
                    throw ValidationException::withMessages(['issue' => 'The linked Job Order must be approved.']);
                }
                $previous = InventoryIssuance::query()
                    ->where(fn ($query) => $query->where('reference_no', $irNumber)
                        ->orWhere('reference_no', 'like', $irNumber.'-B%'))
                    ->with('items')->orderBy('id')->lockForUpdate()->get();
                if ($ir->status === 'Issued' && ($previous->isEmpty() || $jo->part_status === 'Issued')) {
                    throw ValidationException::withMessages(['issue' => 'This request is already fully issued.']);
                }
                $parts = collect($previous->isNotEmpty()
                    ? $parser->parsePartText((string) $jo->part_needed)
                    : ($ir->requested_parts ?? []))->values();
                if ($parts->isEmpty() || count($validated['issued_quantities']) !== $parts->count()) {
                    throw ValidationException::withMessages(['issued_quantities' => 'All current JO item rows must be included. Refresh the issuance slip.']);
                }
                // Canonicalize legacy name-only IR lines against Warehouse item IDs.
                // Never silently choose between duplicate-name inventory records.
                $stockItems = InventoryItem::query()->get();
                $nameKey = static fn ($name, $unit): string =>
                    mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $name))).'|'.$parser->normalizeUnit((string) $unit);
                $matchingIds = $stockItems->groupBy(fn ($item) =>
                    $nameKey($item->parts_name ?? $item->item_name, $item->unit ?? $item->unit_of_measurement));
                $key = static function ($name, $unit, $id = null) use ($nameKey, $matchingIds): string {
                    if ($id) return 'id:'.(int) $id;
                    $matches = $matchingIds->get($nameKey($name, $unit), collect());
                    return $matches->count() === 1 ? 'id:'.$matches->first()->id : 'name:'.$nameKey($name, $unit);
                };
                $already = [];
                foreach ($previous as $batch) {
                    foreach ($batch->items as $entry) {
                        $partKey = $key($entry->item_name, $entry->unit, $entry->inventory_item_id);
                        $already[$partKey] = ($already[$partKey] ?? 0) + (int) $entry->quantity;
                    }
                }

                $expectedTotals = [];
                $nowTotals = [];
                $needs = [];
                foreach ($parts as $index => $part) {
                    $name = trim((string) ($part['name'] ?? ''));
                    $unit = trim((string) ($part['unit'] ?? ''));
                    $quantity = (int) ($part['quantity'] ?? 0);
                    $issueNow = (int) ($validated['issued_quantities'][$index] ?? -1);
                    if ($name === '' || $unit === '' || $quantity < 1 || $issueNow < 0) {
                        throw ValidationException::withMessages(['issued_quantities' => 'Invalid item, unit, or issued quantity.']);
                    }
                    $partKey = $key($name, $unit, $part['inventory_item_id'] ?? null);
                    $expectedTotals[$partKey] = ($expectedTotals[$partKey] ?? 0) + $quantity;
                    $nowTotals[$partKey] = ($nowTotals[$partKey] ?? 0) + $issueNow;
                    $needs[$partKey] = ['name' => $name, 'unit' => $unit, 'inventory_item_id' => $part['inventory_item_id'] ?? null];
                }
                if (array_sum($nowTotals) < 1) {
                    throw ValidationException::withMessages(['issued_quantities' => 'Issue at least one item.']);
                }
                foreach ($expectedTotals as $partKey => $quantity) {
                    if (($already[$partKey] ?? 0) + ($nowTotals[$partKey] ?? 0) > $quantity) {
                        throw ValidationException::withMessages(['issued_quantities' => 'Quantity exceeds the remaining IR requirement. Refresh before issuing.']);
                    }
                }

                $joRequired = collect($parser->parsePartText((string) $jo->part_needed))->sum('quantity');
                $irQuantity = $parts->sum(fn ($part) => (int) ($part['quantity'] ?? 0));
                if ($joRequired > $irQuantity && $jo->parts_issue_strategy !== 'issue_available_only') {
                    throw ValidationException::withMessages(['issue' => 'This Job Order requires all parts before Warehouse may issue any quantity.']);
                }
                if ($jo->parts_issue_strategy !== 'issue_available_only' && array_sum($nowTotals) < array_sum($expectedTotals) - array_sum($already)) {
                    throw ValidationException::withMessages(['issue' => 'The Head selected Wait Until Complete. Partial handover is not authorized.']);
                }

                $inventory = InventoryItem::query()->orderBy('id')->lockForUpdate()->get();
                $allocations = [];
                foreach ($nowTotals as $partKey => $qty) {
                    if ($qty === 0) continue;
                    $matches = ! empty($needs[$partKey]['inventory_item_id'])
                        ? $inventory->where('id', (int) $needs[$partKey]['inventory_item_id'])->values()
                        : $inventory->filter(fn (InventoryItem $item) =>
                            $key($item->parts_name ?? $item->item_name, $item->unit ?? $item->unit_of_measurement) === $partKey)->values();
                    if ($matches->count() !== 1) {
                        throw ValidationException::withMessages(['issue' => 'Inventory item '.$needs[$partKey]['name'].' is missing or ambiguous. Resolve it before issuing.']);
                    }
                    $item = $matches->first();
                    $allocations[] = ['item' => $item, 'quantity' => $qty];
                }

                $otherReserved = JobOrderPartReservation::query()->where('job_order_id', '!=', $jo->id)
                    ->select('inventory_item_id', DB::raw('SUM(quantity) as quantity'))
                    ->groupBy('inventory_item_id')->pluck('quantity', 'inventory_item_id');
                $ownReserved = JobOrderPartReservation::query()->where('job_order_id', $jo->id)
                    ->lockForUpdate()->get()->keyBy('inventory_item_id');
                $hasAllocation = isset($jo->inventory_snapshot['shortages']);
                foreach ($allocations as $allocation) {
                    $item = $allocation['item'];
                    $physical = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
                    $freeForJo = max(0, $physical - (int) $otherReserved->get($item->id, 0));
                    $own = (int) ($ownReserved->get($item->id)?->quantity ?? 0);
                    if ($allocation['quantity'] > $freeForJo || ($hasAllocation && $allocation['quantity'] > $own)) {
                        throw ValidationException::withMessages(['issue' => 'Insufficient allocated stock for '.$item->parts_name.'. Recheck stock before issuing.']);
                    }
                }

                $batchNo = $previous->count() + 1;
                $batchRef = $batchNo === 1 ? $irNumber : $irNumber.'-B'.$batchNo;
                $issuance = InventoryIssuance::create([
                    'issue_no' => $batchNo === 1 ? 'ISS-IR-'.$ir->id : 'ISS-IR-'.$ir->id.'-B'.$batchNo,
                    'issued_to' => $jo->assigned_mechanic ?: 'Maintenance',
                    'purpose' => 'Item Request issuance',
                    'reference_no' => $batchRef,
                    'issued_by' => auth()->id(),
                    'issued_at' => now(),
                    'signed_slip_path' => $slipPath,
                    'received_by' => trim($validated['received_by']),
                    'handover_confirmed_at' => now(),
                ]);
                foreach ($allocations as $allocation) {
                    $item = $allocation['item'];
                    $qty = $allocation['quantity'];
                    $movement = $ledger->stockOut($item, $qty, $batchRef, 'Issued for '.$jo->job_order_no, null, $jo->id);
                    InventoryIssuanceItem::create([
                        'inventory_issuance_id' => $issuance->id,
                        'inventory_item_id' => $item->id,
                        'item_code' => $item->item_code,
                        'item_name' => $item->parts_name ?? $item->item_name,
                        'quantity' => $qty,
                        'unit' => $item->unit ?? $item->unit_of_measurement,
                        'previous_stock' => $movement->previous_stock,
                        'new_stock' => $movement->new_stock,
                        'stock_movement_id' => $movement->id,
                    ]);
                    $reserved = $ownReserved->get($item->id);
                    if ($reserved) {
                        $reserved->quantity <= $qty ? $reserved->delete() : $reserved->decrement('quantity', $qty);
                    }
                }
                // The parent IR is completed only when the WHOLE linked JO is fulfilled.
                // An initial handover can cover its originally submitted lines yet
                // still have purchased parts outstanding.
                // An approved JO may have several legacy sequential IRs after replenishment.
                // Completing the latest IR does not alone prove every JO part was issued.
                $allIr = ItemRequest::query()->where('job_order_id', $jo->id)->get();
                $allReferences = $allIr->map(fn ($record) => 'IR-'.($record->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $record->id, 4, '0', STR_PAD_LEFT));
                $issuedAcrossJo = [];
                $allBatches = InventoryIssuance::query()->where(function ($query) use ($allReferences) {
                    foreach ($allReferences as $ref) {
                        $query->orWhere('reference_no', $ref)->orWhere('reference_no', 'like', $ref.'-B%');
                    }
                })->with('items')->get();
                foreach ($allBatches as $batch) {
                    foreach ($batch->items as $line) {
                        $partKey = $key($line->item_name, $line->unit, $line->inventory_item_id);
                        $issuedAcrossJo[$partKey] = ($issuedAcrossJo[$partKey] ?? 0) + (int) $line->quantity;
                    }
                }
                $requiredAcrossJo = collect($parser->parsePartText((string) $jo->part_needed))
                    ->groupBy(fn ($part) => $key($part['name'], $part['unit'], $part['inventory_item_id'] ?? null))
                    ->map(fn ($rows) => $rows->sum(fn ($part) => (int) $part['quantity']));
                $allJoPartsIssued = $requiredAcrossJo->every(fn ($required, $partKey) =>
                    (int) ($issuedAcrossJo[$partKey] ?? 0) >= (int) $required);
                $priorPartStatus = $jo->part_status;
                $newPartStatus = $allJoPartsIssued || $priorPartStatus === 'Issued' ? 'Issued' : 'Partial';
                $jo->update(['part_status' => $newPartStatus]);
                $ir->update(['status' => $allJoPartsIssued ? 'Issued' : 'Partially Issued']);

                // Persist the physical handover as a JO audit event inside the
                // same transaction as its issuance ledger, so rolled-back
                // handovers cannot leave a misleading audit trail.
                $issuedLines = collect($allocations)->map(fn (array $allocation) => [
                    'inventory_item_id' => $allocation['item']->id,
                    'item_code' => $allocation['item']->item_code,
                    'part_name' => $allocation['item']->parts_name ?? $allocation['item']->item_name,
                    'quantity' => (int) $allocation['quantity'],
                    'unit' => $allocation['item']->unit ?? $allocation['item']->unit_of_measurement,
                ])->values()->all();
                $jo->audits()->create([
                    'user_id' => auth()->id(),
                    'action' => $newPartStatus === 'Issued' && $priorPartStatus !== 'Issued'
                        ? 'parts_fully_issued' : 'parts_partially_issued',
                    'from_status' => $priorPartStatus,
                    'to_status' => $newPartStatus,
                    'notes' => 'Warehouse issued '.array_sum($nowTotals).' unit(s) for '.$batchRef
                        .' to '.trim($validated['received_by']).'.',
                    'metadata' => [
                        'item_request_id' => $ir->id,
                        'inventory_issuance_id' => $issuance->id,
                        'reference_no' => $batchRef,
                        'ir_status' => $ir->status,
                        'received_by' => trim($validated['received_by']),
                        'issued_parts' => $issuedLines,
                    ],
                ]);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($slipPath);
            throw $error;
        }

        return redirect()->route('warehouse.item-requests')->with('success', 'Signed issuance recorded. Any remaining IR quantities stay pending.');
    }

    public function signedSlip(ItemRequest $itemRequest)
    {
        $reference = 'IR-'.($itemRequest->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $itemRequest->id, 4, '0', STR_PAD_LEFT);
        $issuance = InventoryIssuance::query()->where(fn ($query) => $query
            ->where('reference_no', $reference)->orWhere('reference_no', 'like', $reference.'-B%'))
            ->latest('id')->firstOrFail();
        if (! $issuance->signed_slip_path || ! Storage::disk('local')->exists($issuance->signed_slip_path)) {
            abort(404, 'Signed issuance slip not found.');
        }

        return Storage::disk('local')->download($issuance->signed_slip_path);
    }

    public function index(Request $request, PartParser $parser)
    {
        $historyStatuses = ['Issued', 'Completed', 'Cancelled', 'Rejected'];
        $view = $request->query('view') === 'history' ? 'history' : 'active';
        $query = ItemRequest::with('jobOrder');

        // Issued IR handovers remain immutable. An issued IR linked to a JO
        // with outstanding parts is tracked under Active until JO fulfillment.
        $waitingForJo = static fn ($q) => $q->whereHas('jobOrder', fn ($jo) => $jo
            ->where(function ($status) {
                $status->whereNull('part_status')->orWhere('part_status', '!=', 'Issued');
            }));
        if ($view === 'history') {
            $query->where(function ($q) use ($historyStatuses) {
                $q->whereIn('status', array_diff($historyStatuses, ['Issued']))
                    ->orWhere(fn ($issued) => $issued->where('status', 'Issued')
                        ->whereDoesntHave('jobOrder', fn ($jo) => $jo
                            ->whereNull('part_status')->orWhere('part_status', '!=', 'Issued')));
            });
        } else {
            $query->where(function ($q) use ($historyStatuses, $waitingForJo) {
                $q->whereNotIn('status', $historyStatuses)
                    ->orWhere(fn ($issued) => $issued->where('status', 'Issued')->where($waitingForJo));
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $irId = preg_match('/^IR-(?:\\d{4}-)?0*(\\d+)$/i', $search, $matches) ? (int) $matches[1] : null;
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

        $workflow = app(JobOrderWorkflowService::class);
        $stockByRequest = $requests->getCollection()->mapWithKeys(function (ItemRequest $ir) use ($workflow, $parser) {
            // Use the same reservation-aware, remaining-quantity view as the
            // issuance modal, not raw global free stock (which excludes this JO).
            $snapshot = $this->verify($ir, $workflow, $parser)->getData(true);
            return [$ir->id => $snapshot];
        });
        // Fulfillment is JO-wide, not just the quantity of this IR.
        // Batch references provide the signed stock-out source of truth.
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
        $issuedByJo = [];
        $normalizePart = fn ($name, $unit) => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $name))).'|'.$parser->normalizeUnit((string) $unit);
        foreach ($batches as $batch) {
            $ref = preg_replace('/-B\\d+$/', '', (string) $batch->reference_no);
            $joId = $references->get($ref);
            if (!$joId) continue;
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
        // Display the same persisted PR shortage breakdown as Maintenance.
        // This is informational only; Warehouse issuance remains immutable.
        $linkedPrPartsByJo = PurchaseRequest::query()
            ->whereIn('job_order_no', $requests->getCollection()->pluck('jobOrder.job_order_no')->filter()->unique())
            ->whereNotIn('status', ['Cancelled', 'Rejected'])
            ->get(['job_order_no', 'item'])
            ->groupBy('job_order_no')
            ->map(fn ($prs) => $prs->sum(fn ($pr) => count($parser->parsePartText((string) $pr->item))));
        $totalCount = ItemRequest::count();
        $submittedCount = ItemRequest::where('status', 'Submitted')->count();
        $waitingIssuedCount = ItemRequest::query()->where('status', 'Issued')
            ->whereHas('jobOrder', fn ($jo) => $jo->whereNull('part_status')->orWhere('part_status', '!=', 'Issued'))->count();
        $activeCount = ItemRequest::whereNotIn('status', $historyStatuses)->count() + $waitingIssuedCount;
        $historyCount = ItemRequest::whereIn('status', $historyStatuses)->count() - $waitingIssuedCount;

        return view('Warehouse.item-requests', compact(
            'requests', 'view', 'totalCount', 'submittedCount', 'activeCount', 'historyCount', 'stockByRequest', 'fulfillmentByJob', 'linkedPrPartsByJo'
        ));
    }
}
