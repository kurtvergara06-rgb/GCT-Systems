<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Services\Maintenance\PurchaseRequestPartsService;
use App\Services\PartParser;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private array $statuses = [
        'Submitted',
        'Approved',
        'Rejected',
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'For Delivery',
        'Delivered',
        'Picked Up',
        'Issued',
    ];

    private PartParser $partParser;

    private PurchaseRequestPartsService $purchaseRequestParts;

    public function __construct(
        PartParser $partParser,
        PurchaseRequestPartsService $purchaseRequestParts
    ) {
        $this->partParser =
            $partParser;
        $this->purchaseRequestParts = $purchaseRequestParts;
    }

    /* =========================================================
       APPROVAL PERMISSION
    ========================================================= */

    private function canApprovePurchaseRequest(): bool
    {
        $user = Auth::user();

        return $user?->hasSystemPermission('maintenance', 'approve') ?? false;
    }

    /* =========================================================
       MAINTENANCE PR QUERY
    ========================================================= */

    private function maintenancePurchaseRequestQuery()
    {
        return PurchaseRequest::query()
            ->where(
                'pr_no',
                'not like',
                '%-P'
            )
            ->where(function ($query) {
                $query
                    ->whereNull(
                        'job_order_no'
                    )
                    ->orWhere(
                        'job_order_no',
                        '!=',
                        'RESTOCK'
                    );
            })
            ->where(function ($query) {
                $query
                    ->whereNull(
                        'bus_no'
                    )
                    ->orWhere(
                        'bus_no',
                        '!=',
                        'RESTOCK'
                    );
            })
            ->where(function ($query) {
                $query
                    ->whereNull(
                        'source_type'
                    )
                    ->orWhere(
                        'source_type',
                        'Maintenance Request'
                    );
            });
    }

    /* =========================================================
       INDEX
    ========================================================= */

    public function index(Request $request)
    {
        $recordView = in_array($request->query('record_view'), ['pending', 'active', 'history'], true)
            ? $request->query('record_view') : 'pending';

        $query =
            $this
                ->maintenancePurchaseRequestQuery();

        if (
            $request->filled(
                'search'
            )
        ) {
            $search =
                trim(
                    $request->search
                );

            $query->where(
                function ($q) use ($search) {
                    $q
                        ->where(
                            'pr_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'job_order_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'bus_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'item',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'quantity',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'status',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            $request->filled(
                'status'
            )
            && $request->status
                !== 'All Statuses'
        ) {
            $query->where(
                'status',
                $request->status
            );
        }

        // Pending is a JO shortage proposal, not a persisted PurchaseRequest.
        // Keep fulfilled procurement in History and in-flight records in Active.
        if ($recordView === 'history') {
            $query->whereIn('status', ['Issued', 'Delivered', 'Picked Up', 'Cancelled']);
        } elseif ($recordView === 'active') {
            $query->whereNotIn('status', ['Issued', 'Delivered', 'Picked Up', 'Cancelled']);
        } else {
            $query->whereRaw('1 = 0');
        }

        $purchaseRequests =
            $query
                ->latest()
                ->paginate(8)
                ->withQueryString();

        // Source JO approval is the authorization for the requisition.
        $prJobApprovals = JobOrder::query()
            ->with('approver')
            ->whereIn('job_order_no', $purchaseRequests->getCollection()->pluck('job_order_no')->filter())
            ->get()
            ->keyBy('job_order_no');

        // The IR status is read from the persisted Item Request, never inferred
        // from the JO-minus-PR quantity shown for reference.
        $irStatusesByJo = \App\Models\Maintenance\ItemRequest::query()
            ->whereIn('job_order_id', $prJobApprovals->pluck('id'))
            ->orderByDesc('id')
            ->get(['job_order_id', 'status'])
            ->unique('job_order_id')
            ->pluck('status', 'job_order_id');

        $submitted =
            $this
                ->maintenancePurchaseRequestQuery()
                ->where(
                    'status',
                    'Submitted'
                )
                ->count();

        $approved =
            $this
                ->maintenancePurchaseRequestQuery()
                ->where(
                    'status',
                    'Approved'
                )
                ->count();

        $rejected =
            $this
                ->maintenancePurchaseRequestQuery()
                ->where(
                    'status',
                    'Rejected'
                )
                ->count();

        $forPurchase =
            $this
                ->maintenancePurchaseRequestQuery()
                ->where(
                    'status',
                    'For Purchase'
                )
                ->count();

        $issued =
            $this
                ->maintenancePurchaseRequestQuery()
                ->where(
                    'status',
                    'Issued'
                )
                ->count();

        $nextPrNo =
            $this->generatePrNo();

        /*
        |--------------------------------------------------------------------------
        | Only Job Orders that:
        | - have a mechanic
        | - have requested parts
        | - are not completed
        |
        | are candidates for PR creation.
        */

        $jobOrders =
            JobOrder::query()
                ->whereNotNull(
                    'assigned_mechanic'
                )
                ->where(
                    'assigned_mechanic',
                    '!=',
                    ''
                )
                ->whereNotNull(
                    'part_needed'
                )
                ->where(
                    'part_needed',
                    '!=',
                    ''
                )
                ->where(
                    'status',
                    '!=',
                    'Completed'
                )
                ->orderByDesc(
                    'created_at'
                )
                ->get()
                ->filter(function (JobOrder $jobOrder): bool {
                    if ($jobOrder->review_status !== 'Approved') {
                        return false;
                    }

                    if ($this->maintenancePurchaseRequestQuery()
                        ->where('job_order_no', $jobOrder->job_order_no)
                        ->exists()) {
                        return false;
                    }

                    try {
                        // Reuse the exact shortage calculation used by submission.
                        return $this->purchaseRequestParts->missingParts((string) $jobOrder->part_needed, $jobOrder) !== [];
                    } catch (ValidationException $exception) {
                        // Invalid JO part units still need correction, not a purchase request.
                        return false;
                    }
                })
                ->values();

        // Build the Pending tab from approved JOs with outstanding shortages.
        // This is read-only data; it does not reserve or issue inventory.
        $pendingPrJobs = $jobOrders->map(function (JobOrder $jobOrder) {
            return [
                'job' => $jobOrder,
                'parts' => $this->purchaseRequestParts->missingParts(
                    (string) $jobOrder->part_needed,
                    $jobOrder
                ),
            ];
        })->values();

        $workToPerformByJobOrder = JobOrder::query()
            ->whereIn('job_order_no', $purchaseRequests->getCollection()->pluck('job_order_no')->filter())
            ->pluck('work_to_perform', 'job_order_no');

        $selectedJobOrder =
            null;

        if (
            $request->filled(
                'job_order_id'
            )
        ) {
            $selectedJobOrder =
                JobOrder::find(
                    $request
                        ->job_order_id
                );
        }

        $statuses =
            $this->statuses;

        $isMaintenanceAdmin =
            $this
                ->canApprovePurchaseRequest();

        return view(
            'Maintenance.purchase-requests',
            compact(
                'purchaseRequests',
                'recordView',
                'pendingPrJobs',
                'workToPerformByJobOrder',
                'prJobApprovals',
                'irStatusesByJo',
                'submitted',
                'approved',
                'rejected',
                'forPurchase',
                'issued',
                'nextPrNo',
                'jobOrders',
                'selectedJobOrder',
                'statuses',
                'isMaintenanceAdmin'
            )
        );
    }

    /* =========================================================
       STORE
    ========================================================= */

    public function jobOrderData(JobOrder $jobOrder)
    {
        abort_unless(
            $jobOrder->review_status === 'Approved'
                && $jobOrder->status !== 'Completed'
                && filled($jobOrder->assigned_mechanic)
                && filled($jobOrder->part_needed),
            404
        );

        $existingRequest = $this->maintenancePurchaseRequestQuery()
            ->where('job_order_no', $jobOrder->job_order_no)
            ->exists();
        if ($existingRequest) {
            return response()->json([
                'message' => 'This Job Order already has a Purchase Request.',
            ], 409);
        }

        $approvalAudit = $jobOrder->audits()
            ->where('action', 'approved')
            ->latest('id')
            ->first();
        $signaturePath = data_get($approvalAudit?->metadata, 'handwritten_signature_path');

        $missingParts = $this->purchaseRequestParts->missingParts((string) $jobOrder->part_needed, $jobOrder);
        $shortagesByKey = collect($missingParts)->groupBy(fn ($p) => mb_strtolower(trim((string) ($p['name'] ?? ''))).'|'.mb_strtolower(trim((string) ($p['unit'] ?? ''))))
            ->map(fn ($items) => $items->sum(fn ($p) => (int) ($p['quantity'] ?? 0)));
        $availableParts = collect($this->partParser->parsePartText((string) $jobOrder->part_needed))
            ->map(function ($part) use ($shortagesByKey) {
                $key = mb_strtolower(trim((string) ($part['name'] ?? ''))).'|'.mb_strtolower(trim((string) ($part['unit'] ?? '')));
                return array_merge($part, ['quantity' => max(0, (int) ($part['quantity'] ?? 0) - (int) $shortagesByKey->get($key, 0))]);
            })->filter(fn ($part) => $part['quantity'] > 0)->values()->all();

        return response()->json([
            'job_order_no' => $jobOrder->job_order_no,
            'bus_no' => $jobOrder->bus_no,
            'assigned_mechanics' => $jobOrder->assigned_mechanic ?: 'Not assigned',
            'work_to_perform' => $jobOrder->work_to_perform,
            'approver_name' => $jobOrder->approver?->name,
            'signature_url' => is_string($signaturePath) && $signaturePath !== ''
                ? route('job-orders.approval-signature', $jobOrder, false)
                : null,
            'parts' => $missingParts,
            'available_parts' => $availableParts,
            'ir_status' => \App\Models\Maintenance\ItemRequest::query()
                ->where('job_order_id', $jobOrder->id)
                ->orderByDesc('id')
                ->value('status'),
        ]);
    }

    public function store(Request $request)
    {
        $validated =
            $request->validate([
                'job_order_no' =>
                    'required|string|max:255',

                'bus_no' =>
                    'required|string|max:255',

                'parts' =>
                    'nullable|array',

                'parts.*.name' =>
                    'nullable|string|max:255',

                'parts.*.quantity' =>
                    'nullable|integer|min:1',

                'parts.*.unit' =>
                    'nullable|string|max:50',

                'item' =>
                    'nullable|string|max:1000',

                'quantity' =>
                    'nullable|integer|min:1',

                'remarks' =>
                    'nullable|string|max:1000',

                'purpose' =>
                    'nullable|string|max:1000',

                'target_date' =>
                    'nullable|date|after_or_equal:today',
            ]);

        if (
            strtoupper(
                trim(
                    $validated[
                        'job_order_no'
                    ]
                )
            ) === 'RESTOCK'
            ||
            strtoupper(
                trim(
                    $validated[
                        'bus_no'
                    ]
                )
            ) === 'RESTOCK'
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests are not allowed in Maintenance Purchase Requests.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'Inventory restock requests are not allowed in Maintenance Purchase Requests.'
                );
        }

        /* =====================================================
           FIND JO
        ====================================================== */

        $jobOrder =
            JobOrder::where(
                'job_order_no',
                $validated[
                    'job_order_no'
                ]
            )->first();

        if (! $jobOrder) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Selected job order was not found.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'Selected job order was not found.'
                );
        }

        /* =====================================================
           JO MUST HAVE MECHANIC
        ====================================================== */

        if (
            empty(
                $jobOrder
                    ->assigned_mechanic
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'A mechanic must be assigned before creating a Purchase Request.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'A mechanic must be assigned before creating a Purchase Request.'
                );
        }

        if ($jobOrder->review_status !== 'Approved') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'A Purchase Requisition can only be created from an approved Job Order.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'A Purchase Requisition can only be created from an approved Job Order.'
                );
        }

        /* =====================================================
           JO MUST HAVE PARTS
        ====================================================== */

        if (
            empty(
                $jobOrder
                    ->part_needed
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This Job Order has no requested parts.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'This Job Order has no requested parts.'
                );
        }

        if (
            $jobOrder->status
            === 'Completed'
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'A Purchase Request cannot be created for a completed Job Order.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'A Purchase Request cannot be created for a completed Job Order.'
                );
        }

        /* =====================================================
           BLOCK DUPLICATE PR

           Includes:
           Submitted
           Approved
           Rejected
           For Purchase
           Issued
           etc.

           Rejected must be RESUBMITTED.
        ====================================================== */

        $existingRequest =
            $this
                ->maintenancePurchaseRequestQuery()
                ->where(
                    'job_order_no',
                    $jobOrder
                        ->job_order_no
                )
                ->latest()
                ->first();

        if ($existingRequest) {
            $existingMsg = $existingRequest->status === 'Rejected'
                ? 'This Job Order already has a rejected Purchase Request. Revise and resubmit the same PR.'
                : 'This Job Order already has a Purchase Request.';
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $existingMsg], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    $existingMsg
                );
        }

        /* =====================================================
           CREATE
        ====================================================== */

        [$purchaseRequest, $jobOrder] = DB::transaction(function () use (
            $jobOrder,
            $validated
        ): array {
            $lockedJobOrder = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);

            if ($lockedJobOrder->review_status !== 'Approved' || $lockedJobOrder->status === 'Completed') {
                throw ValidationException::withMessages([
                    'job_order_no' => 'A Purchase Requisition can only be created from an approved, open Job Order.',
                ]);
            }

            $existingRequest = $this->maintenancePurchaseRequestQuery()
                ->where('job_order_no', $lockedJobOrder->job_order_no)
                ->latest()
                ->first();

            if ($existingRequest) {
                throw ValidationException::withMessages([
                    'job_order_no' => 'This Job Order already has a Purchase Request.',
                ]);
            }

            $parts = $this->purchaseRequestParts->missingParts((string) $lockedJobOrder->part_needed, $lockedJobOrder);
            if ($parts === []) {
                throw ValidationException::withMessages([
                    'job_order_no' => 'All requested parts are available. A Purchase Requisition is not needed.',
                ]);
            }

            $formattedParts = $this->partParser->formatParts($parts);
            $totalQuantity = $this->partParser->calculateTotalQuantity($parts);

            $createdPurchaseRequest = PurchaseRequest::create([
                'pr_no' => $this->generatePrNo(),
                'job_order_no' => $lockedJobOrder->job_order_no,
                'bus_no' => $lockedJobOrder->bus_no,
                'item' => $formattedParts,
                'quantity' => $totalQuantity,
                // JO approval is authorization; PR is merely submitted for procurement.
                'status' => 'Submitted',
                'approved_at' => null,
                'source_type' => 'Maintenance Request',
                'remarks' => $validated['remarks'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'target_date' => $validated['target_date'] ?? null,
                'requested_by' => Auth::id(),
            ]);

            // Procurement approval is not proof of physical issuance.
            // Preserve IR/partial/issued progress rather than overwriting it.
            if (in_array($lockedJobOrder->part_status, [null, 'Not Requested', 'Submitted'], true)) {
                $lockedJobOrder->update([
                    'part_status' => 'For Purchase',
                ]);
            }

            return [$createdPurchaseRequest, $lockedJobOrder->fresh()];
        });

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'created',
            $purchaseRequest->id,
            'A maintenance purchase request was created.'
        );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'status_updated',
            $jobOrder->id,
            'Job order part status was updated to For Purchase.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase request created successfully.',
                'purchase_request' => $purchaseRequest,
            ]);
        }

        return redirect()
            ->to(route('purchase-requests', ['record_view' => 'active'], false))
            ->with(
                'success',
                'Purchase request created successfully.'
            );
    }

    /* =========================================================
       UPDATE SUBMITTED PR
    ========================================================= */

    public function update(
        Request $request,
        PurchaseRequest $purchaseRequest
    ) {
        if (
            $this->isRestockRequest(
                $purchaseRequest
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests cannot be edited from Maintenance.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Inventory restock requests cannot be edited from Maintenance.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Normal edit is only for Submitted.
        | Rejected uses resubmit().
        */

        if (
            $purchaseRequest->status
            !== 'Submitted'
        ) {
            $msg = $purchaseRequest->status === 'Rejected'
                ? 'This Purchase Request was rejected. Use Revise and Resubmit.'
                : 'Only submitted Purchase Requests can be edited.';
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    $msg
                );
        }

        $validated =
            $request->validate([
                'job_order_no' =>
                    'required|string|max:255',

                'bus_no' =>
                    'required|string|max:255',

                'parts' =>
                    'nullable|array',

                'parts.*.name' =>
                    'nullable|string|max:255',

                'parts.*.quantity' =>
                    'nullable|integer|min:1',

                'parts.*.unit' =>
                    'nullable|string|max:50',

                'item' =>
                    'nullable|string|max:1000',

                'quantity' =>
                    'nullable|integer|min:1',

                'remarks' =>
                    'nullable|string|max:1000',
            ]);

        if (
            strtoupper(
                trim(
                    $validated[
                        'job_order_no'
                    ]
                )
            ) === 'RESTOCK'
            ||
            strtoupper(
                trim(
                    $validated[
                        'bus_no'
                    ]
                )
            ) === 'RESTOCK'
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests are not allowed in Maintenance Purchase Requests.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'Inventory restock requests are not allowed in Maintenance Purchase Requests.'
                );
        }

        $jobOrder =
            JobOrder::where(
                'job_order_no',
                $validated[
                    'job_order_no'
                ]
            )->first();

        if (! $jobOrder) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Selected job order was not found.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'Selected job order was not found.'
                );
        }

        if (
            empty(
                $jobOrder
                    ->assigned_mechanic
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'The related Job Order must have an assigned mechanic.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'The related Job Order must have an assigned mechanic.'
                );
        }

        $parts =
            $this->partParser
                ->normalizePartsInput(
                    $request->parts ?? []
                );

        if (
            count($parts) === 0
            && $request->filled(
                'item'
            )
        ) {
            $parts[] = [
                'name' =>
                    trim(
                        $request->item
                    ),

                'quantity' =>
                    (int)
                        ($request->quantity ?? 1)
                    > 0
                        ? (int)
                            ($request->quantity ?? 1)
                        : 1,

                'unit' =>
                    trim(
                        $request->unit ?? ''
                    ),
            ];
        }

        if (
            count($parts) === 0
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Please add at least one requested part.'], 422);
            }
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'Please add at least one requested part.'
                );
        }

        $formattedParts =
            $this->partParser
                ->formatParts(
                    $parts
                );

        $totalQuantity =
            $this->partParser
                ->calculateTotalQuantity(
                    $parts
                );

        $oldJobOrderNo =
            $purchaseRequest
                ->job_order_no;

        DB::transaction(function () use (
            $formattedParts,
            $jobOrder,
            $oldJobOrderNo,
            $purchaseRequest,
            $totalQuantity,
            $validated
        ): void {
        $purchaseRequest->update([
            'job_order_no' =>
                $validated[
                    'job_order_no'
                ],

            'bus_no' =>
                $validated[
                    'bus_no'
                ],

            'item' =>
                $formattedParts,

            'quantity' =>
                $totalQuantity,

            'source_type' =>
                'Maintenance Request',

            'remarks' =>
                $validated[
                    'remarks'
                ] ?? null,
        ]);

        /* =====================================================
           OLD JO CLEANUP IF JO LINK CHANGED
        ====================================================== */

        if (
            $oldJobOrderNo
            !== $purchaseRequest
                ->job_order_no
        ) {
            $oldJobOrder =
                JobOrder::where(
                    'job_order_no',
                    $oldJobOrderNo
                )->first();

            if ($oldJobOrder) {
                $hasOtherRequest =
                    $this
                        ->maintenancePurchaseRequestQuery()
                        ->where(
                            'job_order_no',
                            $oldJobOrderNo
                        )
                        ->exists();

                if (
                    ! $hasOtherRequest
                    && ! \App\Models\Maintenance\ItemRequest::query()->where('job_order_id', $oldJobOrder->id)->exists()
                    && ! in_array($oldJobOrder->part_status, ['Issued', 'Partial'], true)
                ) {
                    $oldJobOrder->update([
                        'part_status' =>
                            ! empty(
                                $oldJobOrder
                                    ->part_needed
                            )
                                ? 'Not Requested'
                                : 'No Parts Needed',
                    ]);
                }
            }
        }

        // The approved JO is the immutable source of required parts.
        // Editing an existing PR must not rewrite the JO demand or erase IR progress.
        });

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'updated',
            $purchaseRequest->id,
            'A maintenance purchase request was updated.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase request updated successfully.',
                'purchase_request' => $purchaseRequest,
            ]);
        }

        return redirect()
            ->back()
            ->with(
                'success',
                'Purchase request updated successfully.'
            );
    }

    /* =========================================================
       RESUBMIT REJECTED PR
    ========================================================= */

    public function resubmit(
        Request $request,
        PurchaseRequest $purchaseRequest
    ) {
        if (
            $this->isRestockRequest(
                $purchaseRequest
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests cannot be resubmitted from Maintenance.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Inventory restock requests cannot be resubmitted from Maintenance.'
                );
        }

        if (
            $purchaseRequest->status
            !== 'Rejected'
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only rejected Purchase Requests can be revised and resubmitted.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Only rejected Purchase Requests can be revised and resubmitted.'
                );
        }

        $validated =
            $request->validate([
                'parts' =>
                    'required|array|min:1',

                'parts.*.name' =>
                    'required|string|max:255',

                'parts.*.quantity' =>
                    'required|integer|min:1',

                'parts.*.unit' =>
                    'nullable|string|max:50',

                'remarks' =>
                    'nullable|string|max:1000',
            ]);

        $jobOrder =
            JobOrder::where(
                'job_order_no',
                $purchaseRequest
                    ->job_order_no
            )->first();

        if (! $jobOrder) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'The related Job Order was not found.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'The related Job Order was not found.'
                );
        }

        if (
            empty(
                $jobOrder
                    ->assigned_mechanic
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'The related Job Order must have an assigned mechanic before the PR can be resubmitted.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'The related Job Order must have an assigned mechanic before the PR can be resubmitted.'
                );
        }

        $parts =
            $this->partParser
                ->normalizePartsInput(
                    $validated[
                        'parts'
                    ]
                );

        if (
            count($parts) === 0
        ) {
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'Please add at least one requested part.'
                );
        }

        $formattedParts =
            $this->partParser
                ->formatParts(
                    $parts
                );

        $totalQuantity =
            $this->partParser
                ->calculateTotalQuantity(
                    $parts
                );

        // The signed JO is the sole approval authority. Never let a rejected
        // legacy PR silently change the parts covered by that authorization.
        if ($jobOrder->review_status !== 'Approved' || $jobOrder->status === 'Completed') {
            throw ValidationException::withMessages([
                'job_order_no' => 'The linked Job Order must be approved and open before this PR can be resubmitted.',
            ]);
        }

        $authorizedParts = $this->partParser->normalizePartsInput(
            $this->partParser->parsePartText((string) $jobOrder->part_needed)
        );
        if ($parts !== $authorizedParts) {
            throw ValidationException::withMessages([
                'parts' => 'These parts differ from the approved Job Order. A new authorized Job Order is required before requesting changed parts.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SAME PR NUMBER
        |--------------------------------------------------------------------------
        | Only update the existing record.
        */

        DB::transaction(function () use (
            $formattedParts,
            $jobOrder,
            $purchaseRequest,
            $totalQuantity,
            $validated
        ): void {
        $purchaseRequest->update([
            'item' =>
                $formattedParts,

            'quantity' =>
                $totalQuantity,

            'remarks' =>
                $validated[
                    'remarks'
                ]
                ?? 'Revised and resubmitted.',

            'status' =>
                'Submitted',

            'approved_at' =>
                null,

            'rejected_at' =>
                null,
        ]);

        // PR resubmission does not modify approved JO parts or fulfillment.
        });

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A rejected Purchase Request was restored under the existing Job Order approval.'
        );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'status_updated',
            $jobOrder->id,
            'The related Job Order part status was restored to Approved using the signed Job Order.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase Request revised and restored under the existing Job Order approval.',
                'purchase_request' => $purchaseRequest,
            ]);
        }

        return redirect()
            ->to(route('purchase-requests', [], false))
            ->with(
                'success',
                'Purchase Request revised and resubmitted successfully.'
            );
    }

    /* =========================================================
       APPROVE
    ========================================================= */

    public function approve(
        Request $request,
        PurchaseRequest $purchaseRequest
    ) {
        abort(409, 'Purchase Requisition approval is inherited from the signed Job Order; separate PR review is disabled.');

        if (
            $this->isRestockRequest(
                $purchaseRequest
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests cannot be approved from Maintenance.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Inventory restock requests cannot be approved from Maintenance.'
                );
        }

        if (
            ! $this
                ->canApprovePurchaseRequest()
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Your role does not have permission to approve purchase requests.'], 403);
            }
            abort(
                403,
                'Your role does not have permission to approve purchase requests.'
            );
        }

        if (
            $purchaseRequest->status
            !== 'Submitted'
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only submitted purchase requests can be approved.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Only submitted purchase requests can be approved.'
                );
        }

        $purchaseRequest = DB::transaction(function () use ($purchaseRequest): PurchaseRequest {
        $lockedPurchaseRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

        if ($lockedPurchaseRequest->status !== 'Submitted') {
            throw ValidationException::withMessages([
                'status' => 'This Purchase Request changed and can no longer be approved.',
            ]);
        }

        $lockedPurchaseRequest->update([
            'status' =>
                'Approved',

            'approved_at' =>
                now(),

            'rejected_at' =>
                null,
        ]);

        $this
            ->updateRelatedJobOrderPartStatus(
                $lockedPurchaseRequest,
                'Approved'
            );

        return $lockedPurchaseRequest->fresh();
        });

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was approved.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase request approved successfully.',
                'purchase_request' => $purchaseRequest,
            ]);
        }

        return redirect()
            ->back()
            ->with(
                'success',
                'Purchase request approved successfully.'
            );
    }

    /* =========================================================
       REJECT
    ========================================================= */

    public function reject(
        Request $request,
        PurchaseRequest $purchaseRequest
    ) {
        abort(409, 'Purchase Requisition approval is inherited from the signed Job Order; separate PR review is disabled.');

        if (
            $this->isRestockRequest(
                $purchaseRequest
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests cannot be rejected from Maintenance.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Inventory restock requests cannot be rejected from Maintenance.'
                );
        }

        if (
            ! $this
                ->canApprovePurchaseRequest()
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Your role does not have permission to reject purchase requests.'], 403);
            }
            abort(
                403,
                'Your role does not have permission to reject purchase requests.'
            );
        }

        if (
            $purchaseRequest->status
            !== 'Submitted'
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only submitted purchase requests can be rejected.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Only submitted purchase requests can be rejected.'
                );
        }

        $purchaseRequest = DB::transaction(function () use ($purchaseRequest, $request): PurchaseRequest {
        $lockedPurchaseRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

        if ($lockedPurchaseRequest->status !== 'Submitted') {
            throw ValidationException::withMessages([
                'status' => 'This Purchase Request changed and can no longer be rejected.',
            ]);
        }

        $lockedPurchaseRequest->update([
            'status' =>
                'Rejected',

            'rejected_at' =>
                now(),

            'approved_at' =>
                null,

            'remarks' =>
                $request->input('remarks', 'Rejected by Maintenance'),
        ]);

        $this
            ->updateRelatedJobOrderPartStatus(
                $lockedPurchaseRequest,
                'Rejected'
            );

        return $lockedPurchaseRequest->fresh();
        });

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was rejected.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase request rejected successfully.',
                'purchase_request' => $purchaseRequest,
            ]);
        }

        return redirect()
            ->back()
            ->with(
                'success',
                'Purchase request rejected successfully.'
            );
    }

    /* =========================================================
       FOR PURCHASE
    ========================================================= */

    public function markForPurchase(
        PurchaseRequest $purchaseRequest
    ) {
        if (
            $this->isRestockRequest(
                $purchaseRequest
            )
        ) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Inventory restock requests cannot be sent to purchase from Maintenance.'
                );
        }

        if (
            $purchaseRequest->status
            !== 'Approved'
        ) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Only approved purchase requests can be sent to purchase.'
                );
        }

        $purchaseRequest = DB::transaction(function () use ($purchaseRequest): PurchaseRequest {
        $lockedPurchaseRequest = PurchaseRequest::query()->lockForUpdate()->findOrFail($purchaseRequest->id);

        if ($lockedPurchaseRequest->status !== 'Approved') {
            throw ValidationException::withMessages([
                'status' => 'This Purchase Request changed and can no longer be sent to Purchase.',
            ]);
        }

        $lockedPurchaseRequest->update([
            'status' =>
                'For Purchase',
        ]);

        $this
            ->updateRelatedJobOrderPartStatus(
                $lockedPurchaseRequest,
                'For Purchase'
            );

        return $lockedPurchaseRequest->fresh();
        });

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was marked For Purchase.'
        );

        return redirect()
            ->back()
            ->with(
                'success',
                'Purchase request sent to purchase successfully.'
            );
    }

    /* =========================================================
       DELETE PR
    ========================================================= */

    public function destroy(
        Request $request,
        PurchaseRequest $purchaseRequest
    ) {
        if (
            $this->isRestockRequest(
                $purchaseRequest
            )
        ) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Inventory restock requests cannot be deleted from Maintenance.'], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Inventory restock requests cannot be deleted from Maintenance.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Rejected must be revised/resubmitted.
        | Approved and downstream records are transaction history.
        */

        if (
            $purchaseRequest->status
            !== 'Submitted'
        ) {
            $msg = $purchaseRequest->status === 'Rejected'
                ? 'Rejected Purchase Requests cannot be deleted. Revise and resubmit the same PR.'
                : 'This Purchase Request can no longer be deleted because it has already entered the approval or processing workflow.';
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()
                ->back()
                ->with(
                    'error',
                    $msg
                );
        }

        $purchaseRequestId =
            $purchaseRequest->id;

        $jobOrderNo =
            $purchaseRequest
                ->job_order_no;

        $purchaseRequest->delete();

        $jobOrder =
            JobOrder::where(
                'job_order_no',
                $jobOrderNo
            )->first();

        if (
            $jobOrder
            && ! empty(
                $jobOrder
                    ->part_needed
            )
        ) {
            $hasOtherRequest =
                $this
                    ->maintenancePurchaseRequestQuery()
                    ->where(
                        'job_order_no',
                        $jobOrderNo
                    )
                    ->exists();

            if (
                ! $hasOtherRequest
                && ! \App\Models\Maintenance\ItemRequest::query()->where('job_order_id', $jobOrder->id)->exists()
                && ! in_array($jobOrder->part_status, ['Issued', 'Partial'], true)
            ) {
                $jobOrder->update([
                    'part_status' =>
                        'Not Requested',
                ]);
            }
        }

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'deleted',
            $purchaseRequestId,
            'A maintenance purchase request was deleted.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase request deleted successfully.',
            ]);
        }

        return redirect()
            ->back()
            ->with(
                'success',
                'Purchase request deleted successfully.'
            );
    }

    /* =========================================================
       RESTOCK CHECK
    ========================================================= */

    private function isRestockRequest(
        PurchaseRequest $purchaseRequest
    ): bool {
        return
            strtoupper(
                trim(
                    $purchaseRequest
                        ->job_order_no ?? ''
                )
            ) === 'RESTOCK'

            ||

            strtoupper(
                trim(
                    $purchaseRequest
                        ->bus_no ?? ''
                )
            ) === 'RESTOCK'

            ||

            strtolower(
                trim(
                    $purchaseRequest
                        ->source_type ?? ''
                )
            ) === 'inventory restock';
    }

    /* =========================================================
       UPDATE RELATED JO PART STATUS
    ========================================================= */

    private function updateRelatedJobOrderPartStatus(
        PurchaseRequest $purchaseRequest,
        string $partStatus
    ): void {
        if (
            $this->isRestockRequest(
                $purchaseRequest
            )
        ) {
            return;
        }

        $jobOrder =
            JobOrder::where(
                'job_order_no',
                $purchaseRequest
                    ->job_order_no
            )->first();

        if (! $jobOrder) {
            return;
        }

        if (! in_array($jobOrder->part_status, ['Issued', 'Partial'], true)) {
            $jobOrder->update(['part_status' => $partStatus]);
        }
    }

    /* =========================================================
       GENERATE PR NUMBER
    ========================================================= */

    private function generatePrNo(): string
    {
        $year =
            now()->format('Y');

        $lastPr =
            PurchaseRequest::where(
                'pr_no',
                'like',
                "PR-{$year}-%"
            )
                ->where(
                    'pr_no',
                    'not like',
                    '%-P'
                )
                ->orderByDesc(
                    'id'
                )
                ->lockForUpdate()
                ->first();

        if (! $lastPr) {
            return "PR-{$year}-0001";
        }

        preg_match(
            '/PR-'
            . $year
            . '-(\d+)/',
            $lastPr->pr_no,
            $matches
        );

        $nextNumber =
            isset($matches[1])
                ? (int)
                    $matches[1] + 1
                : 1;

        $newPrNo =
            'PR-'
            . $year
            . '-'
            . str_pad(
                $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );

        while (
            PurchaseRequest::where(
                'pr_no',
                $newPrNo
            )->exists()
        ) {
            $nextNumber++;

            $newPrNo =
                'PR-'
                . $year
                . '-'
                . str_pad(
                    $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
        }

        return $newPrNo;
    }
}
