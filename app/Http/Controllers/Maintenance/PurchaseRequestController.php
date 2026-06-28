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

    public function __construct(PartParser $partParser)
    {
        $this->partParser = $partParser;
    }

    private function canApprovePurchaseRequest(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        $role = strtolower(trim($user->role ?? ''));
        $department = strtolower(trim($user->department ?? ''));

        return $department === 'maintenance' && $role === 'head';
    }

    private function maintenancePurchaseRequestQuery()
    {
        return PurchaseRequest::query()
            ->where('pr_no', 'not like', '%-P')
            ->where(function ($query) {
                $query->whereNull('job_order_no')
                    ->orWhere('job_order_no', '!=', 'RESTOCK');
            })
            ->where(function ($query) {
                $query->whereNull('bus_no')
                    ->orWhere('bus_no', '!=', 'RESTOCK');
            })
            ->where(function ($query) {
                $query->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            });
    }

    private function availableJobOrdersForPurchaseRequest()
    {
        /*
        |--------------------------------------------------------------------------
        | Hide Job Orders that already have an active PR
        |--------------------------------------------------------------------------
        | Submitted, Approved, For Purchase, Ordered, Delivered, etc.
        | Rejected JOs can appear again because a new PR may be created.
        */

        $jobOrdersWithActivePr = PurchaseRequest::query()
            ->where('pr_no', 'not like', '%-P')
            ->where(function ($query) {
                $query->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            })
            ->whereNotIn('status', ['Rejected', 'Issued'])
            ->whereNotNull('job_order_no')
            ->pluck('job_order_no')
            ->filter()
            ->unique()
            ->values();

        return JobOrder::query()
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->where('status', '!=', 'Completed')
            ->whereIn('part_status', ['Not Requested', 'Rejected'])
            ->whereNotIn('job_order_no', $jobOrdersWithActivePr)
            ->orderByDesc('created_at')
            ->get();
    }

    public function index(Request $request)
    {
        $query = $this->maintenancePurchaseRequestQuery();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                    ->orWhere('job_order_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('quantity', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            $query->where('status', $request->status);
        }

        $purchaseRequests = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $submitted = $this->maintenancePurchaseRequestQuery()
            ->where('status', 'Submitted')
            ->count();

        $approved = $this->maintenancePurchaseRequestQuery()
            ->where('status', 'Approved')
            ->count();

        $rejected = $this->maintenancePurchaseRequestQuery()
            ->where('status', 'Rejected')
            ->count();

        $forPurchase = $this->maintenancePurchaseRequestQuery()
            ->where('status', 'For Purchase')
            ->count();

        $issued = $this->maintenancePurchaseRequestQuery()
            ->where('status', 'Issued')
            ->count();

        $nextPrNo = $this->generatePrNo();

        $jobOrders = $this->availableJobOrdersForPurchaseRequest();

        $selectedJobOrder = null;

        if ($request->filled('job_order_id')) {
            $selectedJobOrder = JobOrder::find($request->job_order_id);

            /*
            |--------------------------------------------------------------------------
            | Do not allow a manually opened JO if it has an active PR
            |--------------------------------------------------------------------------
            */
            if ($selectedJobOrder) {
                $hasActivePr = PurchaseRequest::query()
                    ->where('job_order_no', $selectedJobOrder->job_order_no)
                    ->where('pr_no', 'not like', '%-P')
                    ->where(function ($query) {
                        $query->whereNull('source_type')
                            ->orWhere('source_type', 'Maintenance Request');
                    })
                    ->whereNotIn('status', ['Rejected', 'Issued'])
                    ->exists();

                if ($hasActivePr) {
                    $selectedJobOrder = null;
                }
            }
        }

        $statuses = $this->statuses;
        $isMaintenanceAdmin = $this->canApprovePurchaseRequest();

        return view('Maintenance.purchase-requests', compact(
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
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
            'item' => 'nullable|string|max:1000',
            'quantity' => 'nullable|integer|min:1',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if (
            strtoupper(trim($validated['job_order_no'])) === 'RESTOCK' ||
            strtoupper(trim($validated['bus_no'])) === 'RESTOCK'
        ) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Inventory restock requests are not allowed in Maintenance Purchase Requests.');
        }

        $jobOrder = JobOrder::where(
            'job_order_no',
            $validated['job_order_no']
        )->first();

        if (! $jobOrder) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Selected job order was not found.');
        }

        if ($jobOrder->status === 'Completed') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Completed job orders cannot create a purchase request.');
        }

        if (! in_array($jobOrder->part_status, ['Not Requested', 'Rejected'], true)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This job order already has a purchase request or is already being processed.');
        }

        $hasActiveRequest = PurchaseRequest::where(
            'job_order_no',
            $validated['job_order_no']
        )
            ->where('pr_no', 'not like', '%-P')
            ->where(function ($query) {
                $query->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            })
            ->whereNotIn('status', ['Rejected', 'Issued'])
            ->exists();

        if ($hasActiveRequest) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This job order already has an active purchase request.');
        }

        $parts = $this->partParser->normalizePartsInput($request->parts ?? []);

        if (count($parts) === 0 && $request->filled('item')) {
            $parts[] = [
                'name' => trim($request->item),
                'quantity' => (int) ($request->quantity ?? 1) > 0
                    ? (int) ($request->quantity ?? 1)
                    : 1,
                'unit' => trim($request->unit ?? ''),
            ];
        }

        if (count($parts) === 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Please add at least one requested part.');
        }

        $formattedParts = $this->partParser->formatParts($parts);
        $totalQuantity = $this->partParser->calculateTotalQuantity($parts);

        $purchaseRequest = PurchaseRequest::create([
            'pr_no' => $this->generatePrNo(),
            'job_order_no' => $validated['job_order_no'],
            'bus_no' => $validated['bus_no'],
            'item' => $formattedParts,
            'quantity' => $totalQuantity,
            'status' => 'Submitted',
            'source_type' => 'Maintenance Request',
            'remarks' => $validated['remarks'] ?? null,
            'date_requested' => now(),
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Submitted');

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'created',
            $purchaseRequest->id,
            'A maintenance purchase request was created.'
        );

        return redirect()
            ->route('purchase-requests')
            ->with('success', 'Purchase request created successfully.');
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be edited from Maintenance.');
        }

        if ($purchaseRequest->status !== 'Submitted') {
            return redirect()
                ->back()
                ->with('error', 'Only submitted purchase requests can be edited.');
        }

        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255',
            'bus_no' => 'required|string|max:255',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
            'item' => 'nullable|string|max:1000',
            'quantity' => 'nullable|integer|min:1',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $parts = $this->partParser->normalizePartsInput($request->parts ?? []);

        if (count($parts) === 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Please add at least one requested part.');
        }

        $formattedParts = $this->partParser->formatParts($parts);
        $totalQuantity = $this->partParser->calculateTotalQuantity($parts);

        $purchaseRequest->update([
            'item' => $formattedParts,
            'quantity' => $totalQuantity,
            'remarks' => $validated['remarks'] ?? null,
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
            ->with('success', 'Purchase request updated successfully.');
    }

    public function approve(PurchaseRequest $purchaseRequest)
    {
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be approved from Maintenance.');
        }

        if (! $this->canApprovePurchaseRequest()) {
            abort(403, 'Only Maintenance Head can approve purchase requests.');
        }

        if ($purchaseRequest->status !== 'Submitted') {
            return redirect()
                ->back()
                ->with('error', 'Only submitted purchase requests can be approved.');
        }

        $purchaseRequest->update([
            'status' => 'Approved',
            'approved_at' => now(),
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Approved');

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was approved.'
        );

        return redirect()
            ->back()
            ->with('success', 'Purchase request approved successfully.');
    }

    public function reject(PurchaseRequest $purchaseRequest)
    {
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be rejected from Maintenance.');
        }

        if (! $this->canApprovePurchaseRequest()) {
            abort(403, 'Only Maintenance Head can reject purchase requests.');
        }

        if ($purchaseRequest->status !== 'Submitted') {
            return redirect()
                ->back()
                ->with('error', 'Only submitted purchase requests can be rejected.');
        }

        $purchaseRequest->update([
            'status' => 'Rejected',
            'rejected_at' => now(),
            'remarks' => 'Rejected by Maintenance Head',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Rejected');

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was rejected.'
        );

        return redirect()
            ->back()
            ->with('success', 'Purchase request rejected successfully.');
    }

    public function markForPurchase(PurchaseRequest $purchaseRequest)
    {
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be sent to purchase from Maintenance.');
        }

        if ($purchaseRequest->status !== 'Approved') {
            return redirect()
                ->back()
                ->with('error', 'Only approved purchase requests can be sent to purchase.');
        }

        $purchaseRequest->update([
            'status' => 'For Purchase',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'For Purchase');

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was marked For Purchase.'
        );

        return redirect()
            ->back()
            ->with('success', 'Purchase request sent to purchase successfully.');
    }

    public function markDelivered(PurchaseRequest $purchaseRequest)
    {
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be marked delivered from Maintenance.');
        }

        $purchaseRequest->update([
            'status' => 'Delivered',
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Delivered');

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was marked Delivered.'
        );

        return redirect()
            ->back()
            ->with('success', 'Purchase request marked as delivered.');
    }

    public function issue(PurchaseRequest $purchaseRequest)
    {
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be issued from Maintenance.');
        }

        $purchaseRequest->update([
            'status' => 'Issued',
            'issued_at' => now(),
        ]);

        $this->updateRelatedJobOrderPartStatus($purchaseRequest, 'Issued');

        $this->broadcastSystemDataUpdated(
            'Maintenance',
            'PurchaseRequest',
            'status_updated',
            $purchaseRequest->id,
            'A maintenance purchase request was marked Issued.'
        );

        return redirect()
            ->back()
            ->with('success', 'Purchase request issued successfully.');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        if ($this->isRestockRequest($purchaseRequest)) {
            return redirect()
                ->back()
                ->with('error', 'Inventory restock requests cannot be deleted from Maintenance.');
        }

        $purchaseRequestId = $purchaseRequest->id;
        $jobOrderNo = $purchaseRequest->job_order_no;

        $purchaseRequest->delete();

        $jobOrder = JobOrder::where('job_order_no', $jobOrderNo)->first();

        if ($jobOrder && ! empty($jobOrder->part_needed)) {
            $hasOtherRequest = PurchaseRequest::where(
                'job_order_no',
                $jobOrderNo
            )
                ->where('pr_no', 'not like', '%-P')
                ->where(function ($query) {
                    $query->whereNull('source_type')
                        ->orWhere('source_type', 'Maintenance Request');
                })
                ->whereNotIn('status', ['Rejected', 'Issued'])
                ->exists();

            if (! $hasOtherRequest) {
                $jobOrder->update([
                    'part_status' => 'Not Requested',
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
            ->with('success', 'Purchase request deleted successfully.');
    }

    private function isRestockRequest(PurchaseRequest $purchaseRequest): bool
    {
        return strtoupper(trim($purchaseRequest->job_order_no ?? '')) === 'RESTOCK'
            || strtoupper(trim($purchaseRequest->bus_no ?? '')) === 'RESTOCK'
            || strtolower(trim($purchaseRequest->source_type ?? '')) === 'inventory restock';
    }

    private function updateRelatedJobOrderPartStatus(
        PurchaseRequest $purchaseRequest,
        string $partStatus
    ): void {
        if ($this->isRestockRequest($purchaseRequest)) {
            return;
        }

        $jobOrder = JobOrder::where(
            'job_order_no',
            $purchaseRequest->job_order_no
        )->first();

        if (! $jobOrder) {
            return;
        }

        $jobOrder->update([
            'part_status' => $partStatus,
        ]);
    }

    private function generatePrNo(): string
    {
        $year = now()->format('Y');

        $lastPr = PurchaseRequest::where(
            'pr_no',
            'like',
            "PR-{$year}-%"
        )
            ->where('pr_no', 'not like', '%-P')
            ->orderByDesc('id')
            ->first();

        if (! $lastPr) {
            return "PR-{$year}-0001";
        }

        preg_match('/PR-' . $year . '-(\d+)/', $lastPr->pr_no, $matches);

        $nextNumber = isset($matches[1])
            ? (int) $matches[1] + 1
            : 1;

        $newPrNo = 'PR-' . $year . '-' . str_pad(
            $nextNumber,
            4,
            '0',
            STR_PAD_LEFT
        );

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;

            $newPrNo = 'PR-' . $year . '-' . str_pad(
                $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );
        }

        return $newPrNo;
    }
}