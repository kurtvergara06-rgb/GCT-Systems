<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\ItemRequest;
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
    public function verify(ItemRequest $itemRequest, JobOrderWorkflowService $workflow)
    {
        $parts = $itemRequest->requested_parts ?? [];
        $snapshot = $workflow->inventoryAvailability($parts);
        $own = JobOrderPartReservation::query()->where('job_order_id', $itemRequest->job_order_id)->pluck('quantity', 'inventory_item_id');
        $others = JobOrderPartReservation::query()->where('job_order_id', '!=', $itemRequest->job_order_id)
            ->select('inventory_item_id', DB::raw('SUM(quantity) as qty'))->groupBy('inventory_item_id')->pluck('qty', 'inventory_item_id');
        $hasAllocation = isset($itemRequest->jobOrder?->inventory_snapshot['shortages']);
        foreach ($snapshot['parts'] as &$part) {
            $id = $part['inventory_item_id'] ?? null;
            $quantity = (int) ($part['quantity'] ?? 0);
            $free = max(0, (int) ($part['available'] ?? 0) - (int) $others->get($id, 0));
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
                'unit' => $part['unit'],
                'available' => $part['available'],
                'shortage' => $part['shortage'],
                'fulfillable' => $part['fulfillable'],
            ])->values(),
            'checked_at' => now(config('app.business_timezone', 'Asia/Manila'))->toIso8601String(),
            'note' => 'Availability is informational. Verify again during actual issuance.',
        ]);
    }


    /**
     * Issue a complete IR only. Partial releases require a separate Head decision.
     * Item rows are locked and matched by name AND compatible unit.
     */
    public function issue(Request $request, ItemRequest $itemRequest, InventoryLedgerService $ledger, PartParser $parser)
    {
        $validated = $request->validate([
            'received_by' => ['required', 'string', 'max:255'],
            'signed_slip' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'handover_confirmed' => ['required', 'accepted'],
            'issued_quantities' => ['required', 'array', 'min:1'],
            'issued_quantities.*' => ['required', 'integer', 'min:1'],
        ]);

        $expected = collect($itemRequest->requested_parts ?? [])->values();
        if ($expected->isEmpty() || count($validated['issued_quantities']) !== $expected->count()) {
            throw ValidationException::withMessages(['issued_quantities' => 'All requested parts must be included.']);
        }
        foreach ($expected as $index => $part) {
            if ((int) ($validated['issued_quantities'][$index] ?? 0) !== (int) ($part['quantity'] ?? 0)) {
                throw ValidationException::withMessages(['issued_quantities' => 'Partial issuance requires Maintenance Head authorization and is not enabled yet.']);
            }
        }

        // Retain evidence in private storage, not the publicly accessible disk.
        $slipPath = $request->file('signed_slip')->store('warehouse/signed-ir-slips', 'local');
        try {
        $irNumber = 'IR-'.($itemRequest->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $itemRequest->id, 4, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($itemRequest, $ledger, $parser, $irNumber, $slipPath, $validated): void {
            $ir = ItemRequest::query()->whereKey($itemRequest->id)->lockForUpdate()->firstOrFail();
            if ($ir->status !== 'Submitted') {
                throw ValidationException::withMessages(['issue' => 'This Item Request has already been processed or is not ready for issuance.']);
            }
            if ($ir->jobOrder?->review_status !== 'Approved') {
                throw ValidationException::withMessages(['issue' => 'The linked Job Order must be approved.']);
            }
            if (InventoryIssuance::where('reference_no', $irNumber)->exists()) {
                throw ValidationException::withMessages(['issue' => 'An issuance is already recorded for this Item Request.']);
            }
            $parts = collect($ir->requested_parts ?? []);
            $jo = $ir->jobOrder;
            $joRequired = collect($parser->parsePartText((string) ($jo?->part_needed ?? '')))->sum('quantity');
            $irQuantity = $parts->sum(fn ($part) => (int) ($part['quantity'] ?? 0));
            $hasOutstandingJoParts = $joRequired > $irQuantity;
            if ($hasOutstandingJoParts && $jo?->parts_issue_strategy !== 'issue_available_only') {
                throw ValidationException::withMessages(['issue' => 'The Maintenance Head has not authorized partial release for this Job Order.']);
            }
            if ($parts->isEmpty()) {
                throw ValidationException::withMessages(['issue' => 'There are no items to issue.']);
            }

            $normalize = static fn ($value) => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $value)));
            $stockRows = InventoryItem::query()->orderBy('id')->lockForUpdate()->get();
            $allocations = [];
            foreach ($parts as $part) {
                $name = trim((string) ($part['name'] ?? ''));
                $unit = trim((string) ($part['unit'] ?? ''));
                $quantity = (int) ($part['quantity'] ?? 0);
                if ($name === '' || $unit === '' || $quantity < 1) {
                    throw ValidationException::withMessages(['issue' => 'Each requested part must have a name, unit, and positive quantity.']);
                }
                $matches = $stockRows->filter(fn ($item) =>
                    $normalize($item->parts_name) === $normalize($name)
                    && $parser->normalizeUnit((string) $item->unit) === $parser->normalizeUnit($unit)
                )->values();
                if ($matches->count() !== 1) {
                    throw ValidationException::withMessages(['issue' => "Part '{$name}' is missing or matches multiple inventory records. Warehouse must resolve the item code before issuing."]);
                }
                $item = $matches->first();
                $allocations[$item->id] ??= ['item' => $item, 'quantity' => 0];
                $allocations[$item->id]['quantity'] += $quantity;
            }
            $otherReserved = JobOrderPartReservation::query()->where('job_order_id', '!=', $jo->id)
                ->select('inventory_item_id', DB::raw('SUM(quantity) as qty'))->groupBy('inventory_item_id')->pluck('qty', 'inventory_item_id');
            $ownReserved = JobOrderPartReservation::query()->where('job_order_id', $jo->id)->lockForUpdate()->get()->keyBy('inventory_item_id');
            $hasAllocation = isset($jo->inventory_snapshot['shortages']);
            foreach ($allocations as $allocation) {
                $item = $allocation['item'];
                $needed = $allocation['quantity'];
                $physical = (int) ($item->quantity_available ?? $item->on_hand ?? 0);
                $freeForJo = max(0, $physical - (int) $otherReserved->get($item->id, 0));
                $reserved = (int) ($ownReserved->get($item->id)?->quantity ?? 0);
                if ($needed > $freeForJo || ($hasAllocation && $needed > $reserved)) {
                    throw ValidationException::withMessages(['issue' => "Insufficient allocated stock for '{$item->parts_name}'. Recheck the IR and coordinate any shortage."]);
                }
            }

            $issuance = InventoryIssuance::create([
                'issue_no' => 'ISS-IR-'.$ir->id,
                'issued_to' => $ir->jobOrder?->assigned_mechanic ?: 'Maintenance',
                'purpose' => 'Item Request issuance',
                'reference_no' => $irNumber,
                'issued_by' => auth()->id(),
                'issued_at' => now(),
                'signed_slip_path' => $slipPath,
                'received_by' => trim($validated['received_by']),
                'handover_confirmed_at' => now(),
            ]);
            foreach ($allocations as $allocation) {
                $item = $allocation['item'];
                $qty = $allocation['quantity'];
                $movement = $ledger->stockOut($item, $qty, $irNumber, 'Issued for '.$ir->jobOrder?->job_order_no);
                $reservation = $ownReserved->get($item->id);
                if ($reservation) {
                    $reservation->quantity === $qty ? $reservation->delete() : $reservation->decrement('quantity', $qty);
                }
                InventoryIssuanceItem::create([
                    'inventory_issuance_id' => $issuance->id,
                    'inventory_item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->parts_name,
                    'quantity' => $qty,
                    'unit' => $item->unit,
                    'previous_stock' => $movement->previous_stock,
                    'new_stock' => $movement->new_stock,
                    'stock_movement_id' => $movement->id,
                ]);
            }
            $ir->update(['status' => 'Issued']);
            $jo?->update(['part_status' => $hasOutstandingJoParts ? 'Partial' : 'Issued']);
        });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($slipPath);
            throw $error;
        }

        return redirect()->route('warehouse.item-requests')->with('success', "{$irNumber} issued. Signed slip retained and stock movements recorded.");
    }

    public function signedSlip(ItemRequest $itemRequest)
    {
        $reference = 'IR-'.($itemRequest->created_at?->format('Y') ?? '0000').'-'.str_pad((string) $itemRequest->id, 4, '0', STR_PAD_LEFT);
        $issuance = InventoryIssuance::query()->where('reference_no', $reference)->firstOrFail();
        if (! $issuance->signed_slip_path || ! Storage::disk('local')->exists($issuance->signed_slip_path)) {
            abort(404, 'Signed issuance slip not found.');
        }

        return Storage::disk('local')->download($issuance->signed_slip_path);
    }

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
        $stockByRequest = $requests->getCollection()->mapWithKeys(function (ItemRequest $ir) use ($workflow) {
            $snapshot = $workflow->inventoryAvailability($ir->requested_parts ?? []);
            return [$ir->id => $snapshot];
        });
        $totalCount = ItemRequest::count();
        $submittedCount = ItemRequest::where('status', 'Submitted')->count();
        $activeCount = ItemRequest::whereNotIn('status', $historyStatuses)->count();
        $historyCount = ItemRequest::whereIn('status', $historyStatuses)->count();

        return view('Warehouse.item-requests', compact(
            'requests', 'view', 'totalCount', 'submittedCount', 'activeCount', 'historyCount', 'stockByRequest'
        ));
    }
}
