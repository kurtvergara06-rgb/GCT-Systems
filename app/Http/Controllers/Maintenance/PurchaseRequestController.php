<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Services\PartParser;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function __construct(
        PartParser $partParser
    ) {
        $this->partParser =
            $partParser;
    }

    /* =========================================================
       APPROVAL PERMISSION
    ========================================================= */

    private function canApprovePurchaseRequest(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user =
            Auth::user();

        $department =
            strtolower(
                trim(
                    (string)
                        ($user->department ?? '')
                )
            );

        $role =
            strtolower(
                trim(
                    (string)
                        ($user->role ?? '')
                )
            );

        $normalizedDepartment =
            preg_replace(
                '/\s+/',
                ' ',
                str_replace(
                    [
                        '_',
                        '-',
                    ],
                    ' ',
                    $department
                )
            );

        $normalizedRole =
            preg_replace(
                '/\s+/',
                ' ',
                str_replace(
                    [
                        '_',
                        '-',
                    ],
                    ' ',
                    $role
                )
            );

        $isMaintenanceHead =
            $normalizedDepartment
                === 'maintenance'
            && in_array(
                $normalizedRole,
                [
                    'head',
                    'admin',
                    'maintenance head',
                    'maintenance admin',
                ],
                true
            );

        $isSystemAdmin =
            $normalizedDepartment
                === 'admin'
            && in_array(
                $normalizedRole,
                [
                    'head',
                    'admin',
                    'system admin',
                ],
                true
            );

        return
            $isMaintenanceHead
            || $isSystemAdmin;
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

        $purchaseRequests =
            $query
                ->latest()
                ->paginate(8)
                ->withQueryString();

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
                ->get();

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
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    'A mechanic must be assigned before creating a Purchase Request.'
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
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    $existingRequest->status
                        === 'Rejected'
                        ? 'This Job Order already has a rejected Purchase Request. Revise and resubmit the same PR.'
                        : 'This Job Order already has a Purchase Request.'
                );
        }

        /* =====================================================
           PARTS
        ====================================================== */

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

        /* =====================================================
           CREATE
        ====================================================== */

        $purchaseRequest =
            PurchaseRequest::create([
                'pr_no' =>
                    $this->generatePrNo(),

                'job_order_no' =>
                    $jobOrder
                        ->job_order_no,

                'bus_no' =>
                    $jobOrder
                        ->bus_no,

                'item' =>
                    $formattedParts,

                'quantity' =>
                    $totalQuantity,

                'status' =>
                    'Submitted',

                'source_type' =>
                    'Maintenance Request',

                'remarks' =>
                    $validated[
                        'remarks'
                    ] ?? null,

                'date_requested' =>
                    now(),
            ]);

        /* Keep JO requested parts synchronized. */

        $jobOrder->update([
            'part_needed' =>
                $formattedParts,

            'part_status' =>
                'Submitted',
        ]);

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
            'Job order part status was updated to Submitted.'
        );

        return redirect()
            ->route(
                'purchase-requests'
            )
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
            return redirect()
                ->back()
                ->with(
                    'error',
                    $purchaseRequest->status
                        === 'Rejected'
                        ? 'This Purchase Request was rejected. Use Revise and Resubmit.'
                        : 'Only submitted Purchase Requests can be edited.'
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

        /* Sync new/current JO requested parts. */

        $jobOrder->update([
            'part_needed' =>
                $formattedParts,

            'part_status' =>
                'Submitted',
        ]);

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'updated',
            $purchaseRequest->id,
            'A maintenance purchase request was updated.'
        );

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

        /*
        |--------------------------------------------------------------------------
        | SAME PR NUMBER
        |--------------------------------------------------------------------------
        | Only update the existing record.
        */

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

        /*
        |--------------------------------------------------------------------------
        | Synchronize revised parts back to Job Order.
        */

        $jobOrder->update([
            'part_needed' =>
                $formattedParts,

            'part_status' =>
                'Submitted',
        ]);

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A rejected Purchase Request was revised and resubmitted.'
        );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'JobOrder',
            'status_updated',
            $jobOrder->id,
            'The related Job Order part status was returned to Submitted.'
        );

        return redirect()
            ->route(
                'purchase-requests'
            )
            ->with(
                'success',
                'Purchase Request revised and resubmitted successfully.'
            );
    }

    /* =========================================================
       APPROVE
    ========================================================= */

    public function approve(
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
                    'Inventory restock requests cannot be approved from Maintenance.'
                );
        }

        if (
            ! $this
                ->canApprovePurchaseRequest()
        ) {
            abort(
                403,
                'Only Maintenance Head can approve purchase requests.'
            );
        }

        if (
            $purchaseRequest->status
            !== 'Submitted'
        ) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Only submitted purchase requests can be approved.'
                );
        }

        $purchaseRequest->update([
            'status' =>
                'Approved',

            'approved_at' =>
                now(),

            'rejected_at' =>
                null,
        ]);

        $this
            ->updateRelatedJobOrderPartStatus(
                $purchaseRequest,
                'Approved'
            );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was approved.'
        );

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
                    'Inventory restock requests cannot be rejected from Maintenance.'
                );
        }

        if (
            ! $this
                ->canApprovePurchaseRequest()
        ) {
            abort(
                403,
                'Only Maintenance Head can reject purchase requests.'
            );
        }

        if (
            $purchaseRequest->status
            !== 'Submitted'
        ) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    'Only submitted purchase requests can be rejected.'
                );
        }

        $purchaseRequest->update([
            'status' =>
                'Rejected',

            'rejected_at' =>
                now(),

            'approved_at' =>
                null,

            'remarks' =>
                'Rejected by Maintenance Head',
        ]);

        $this
            ->updateRelatedJobOrderPartStatus(
                $purchaseRequest,
                'Rejected'
            );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was rejected.'
        );

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

        $purchaseRequest->update([
            'status' =>
                'For Purchase',
        ]);

        $this
            ->updateRelatedJobOrderPartStatus(
                $purchaseRequest,
                'For Purchase'
            );

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
       DELIVERED
    ========================================================= */

    public function markDelivered(
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
                    'Inventory restock requests cannot be marked delivered from Maintenance.'
                );
        }

        $purchaseRequest->update([
            'status' =>
                'Delivered',
        ]);

        $this
            ->updateRelatedJobOrderPartStatus(
                $purchaseRequest,
                'Delivered'
            );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was marked Delivered.'
        );

        return redirect()
            ->back()
            ->with(
                'success',
                'Purchase request marked as delivered.'
            );
    }

    /* =========================================================
       ISSUE
    ========================================================= */

    public function issue(
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
                    'Inventory restock requests cannot be issued from Maintenance.'
                );
        }

        $purchaseRequest->update([
            'status' =>
                'Issued',

            'issued_at' =>
                now(),
        ]);

        $this
            ->updateRelatedJobOrderPartStatus(
                $purchaseRequest,
                'Issued'
            );

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was marked Issued.'
        );

        return redirect()
            ->back()
            ->with(
                'success',
                'Purchase request issued successfully.'
            );
    }

    /* =========================================================
       DELETE PR
    ========================================================= */

    public function destroy(
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
            return redirect()
                ->back()
                ->with(
                    'error',
                    $purchaseRequest->status
                        === 'Rejected'
                        ? 'Rejected Purchase Requests cannot be deleted. Revise and resubmit the same PR.'
                        : 'This Purchase Request can no longer be deleted because it has already entered the approval or processing workflow.'
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

        $jobOrder->update([
            'part_status' =>
                $partStatus,
        ]);
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