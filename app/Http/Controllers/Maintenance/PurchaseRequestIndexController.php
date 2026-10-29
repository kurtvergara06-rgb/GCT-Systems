<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Services\Maintenance\PurchaseRequestPartsService;
use Illuminate\Validation\ValidationException;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseRequestIndexController extends Controller
{
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

    public function __invoke(Request $request, PurchaseRequestPartsService $missingParts)
    {
        $recordView = in_array($request->input('record_view'), ['pending', 'active', 'history'], true)
            ? $request->input('record_view')
            : 'active';

        $query = $this->maintenancePurchaseRequestQuery();

        if ($recordView === 'history') {
            $query->where('status', 'Issued');
        } else {
            $query->where('status', '!=', 'Issued');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

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
            $requestedStatus = (string) $request->status;

            if ($recordView === 'history') {
                if ($requestedStatus === 'Issued') {
                    $query->where('status', 'Issued');
                }
            } elseif ($requestedStatus !== 'Issued') {
                $query->where('status', $requestedStatus);
            }
        }

        // History is always Issued; date filters narrow its audit records without changing status.
        if ($recordView === 'history') {
            $dates = $request->validate([
                'created_from' => ['nullable', 'date_format:Y-m-d'],
                'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            ]);

            if (! empty($dates['created_from'])) {
                $query->whereDate('created_at', '>=', $dates['created_from']);
            }
            if (! empty($dates['created_to'])) {
                $query->whereDate('created_at', '<=', $dates['created_to']);
            }
        }

        TableSort::apply($query, $request, ['created_at' => 'created_at', 'pr_no' => 'pr_no', 'status' => 'status'], 'created_at', 'desc');
        $purchaseRequests = $query
            ->with('requester')
            ->paginate(8)
            ->withQueryString();

        $visibleJobOrderNumbers = $purchaseRequests
            ->getCollection()
            ->pluck('job_order_no')
            ->filter()
            ->unique()
            ->values();

        $workToPerformByJobOrder = $visibleJobOrderNumbers->isEmpty()
            ? collect()
            : JobOrder::query()
                ->whereIn('job_order_no', $visibleJobOrderNumbers)
                ->pluck('work_to_perform', 'job_order_no');

        // This is the actual /purchase-requests page controller. Supply the
        // linked JO authorization data used by the read-only PR details modal.
        $prJobApprovals = $visibleJobOrderNumbers->isEmpty()
            ? collect()
            : JobOrder::query()
                ->with([
                    'approver',
                    'audits' => fn ($query) => $query
                        ->where('action', 'approved')
                        ->latest('id'),
                ])
                ->whereIn('job_order_no', $visibleJobOrderNumbers)
                ->get()
                ->keyBy('job_order_no');

        $submitted = $this->maintenancePurchaseRequestQuery()->where('status', 'Submitted')->count();
        $approved = $this->maintenancePurchaseRequestQuery()->where('status', 'Approved')->count();
        $rejected = $this->maintenancePurchaseRequestQuery()->where('status', 'Rejected')->count();
        $forPurchase = $this->maintenancePurchaseRequestQuery()->where('status', 'For Purchase')->count();
        $issued = $this->maintenancePurchaseRequestQuery()->where('status', 'Issued')->count();

        $nextPrNo = $this->generatePrNo();

        $jobOrders = JobOrder::query()
            ->whereNotNull('assigned_mechanic')
            ->where('assigned_mechanic', '!=', '')
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->where('status', '!=', 'Completed')
            ->where('review_status', 'Approved')
            ->orderByDesc('created_at')
            ->get();

        // Virtual pending PR suggestions for approved JOs with a real shortage.
        // They are not saved PRs and must be reviewed and submitted by Maintenance.
        $existingPrJoNumbers = $this->maintenancePurchaseRequestQuery()
            ->whereIn('job_order_no', $jobOrders->pluck('job_order_no'))
            ->pluck('job_order_no');
        $pendingPrJobs = $jobOrders->reject(fn (JobOrder $job) =>
            $existingPrJoNumbers->contains($job->job_order_no)
        )->map(function (JobOrder $job) use ($missingParts) {
            try {
                $parts = $missingParts->missingParts((string) $job->part_needed, $job);
            } catch (ValidationException) {
                $parts = [];
            }
            return ['job' => $job, 'parts' => $parts];
        })->filter(fn (array $proposal) => $proposal['parts'] !== [])->values();

        // Keep the create dropdown unfiltered, while Search/Sort apply to the
        // virtual suggestions displayed under the Pending tab.
        $jobOrders = $pendingPrJobs->pluck('job')->values();
        if ($request->filled('search')) {
            $needle = mb_strtolower(trim((string) $request->query('search')));
            $pendingPrJobs = $pendingPrJobs->filter(function (array $proposal) use ($needle): bool {
                $job = $proposal['job'];
                $partNames = collect($proposal['parts'])->pluck('name')->implode(' ');
                return str_contains(mb_strtolower($job->job_order_no.' '.$job->bus_no.' '.$partNames), $needle);
            })->values();
        }
        $pendingSortDesc = $request->query('sort_direction', $request->query('direction', 'desc')) !== 'asc';
        if ($request->query('sort') === 'pr_no') {
            $pendingPrJobs = $pendingPrJobs->sortBy(fn (array $proposal) => $proposal['job']->job_order_no, SORT_REGULAR, $pendingSortDesc)->values();
        } else {
            $pendingPrJobs = $pendingPrJobs->sortBy(fn (array $proposal) => $proposal['job']->created_at, SORT_REGULAR, $pendingSortDesc)->values();
        }

        if ($request->filled('status')
            && ! in_array($request->query('status'), ['All Statuses', 'Pending Review'], true)) {
            $pendingPrJobs = collect();
        }

        // Automatically open Pending when approved JOs need procurement.
        if (! $request->has('record_view') && $pendingPrJobs->isNotEmpty()) {
            $recordView = 'pending';
        }

        $selectedJobOrder = null;
        if ($request->filled('job_order_id')) {
            $selectedJobOrder = JobOrder::find($request->job_order_id);
        }

        $statuses = $recordView === 'history'
            ? ['Issued']
            : array_values(array_filter($this->statuses, fn ($status) => $status !== 'Issued'));
        $isMaintenanceAdmin = $this->canApprovePurchaseRequest();

        // Saved IR lifecycle status for each JO displayed in PR records.
        $irStatusesByJo = \App\Models\Maintenance\ItemRequest::query()
            ->whereIn('job_order_id', $prJobApprovals->pluck('id'))
            ->orderByDesc('id')
            ->get(['job_order_id', 'status'])
            ->unique('job_order_id')
            ->pluck('status', 'job_order_id');

        return view('Maintenance.purchase-requests', compact(
            'purchaseRequests',
            'submitted',
            'approved',
            'rejected',
            'forPurchase',
            'issued',
            'nextPrNo',
            'jobOrders',
            'pendingPrJobs',
            'selectedJobOrder',
            'statuses',
            'isMaintenanceAdmin',
            'recordView',
            'workToPerformByJobOrder',
            'prJobApprovals',
            'irStatusesByJo'
        ));
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

    private function canApprovePurchaseRequest(): bool
    {
        $user = Auth::user();

        return $user?->hasSystemPermission('maintenance', 'approve') ?? false;
    }

    private function generatePrNo(): string
    {
        $year = now()->format('Y');

        $lastPr = PurchaseRequest::where('pr_no', 'like', "PR-{$year}-%")
            ->where('pr_no', 'not like', '%-P')
            ->orderByDesc('id')
            ->first();

        if (! $lastPr) {
            return "PR-{$year}-0001";
        }

        preg_match('/PR-'.$year.'-(\d+)/', $lastPr->pr_no, $matches);
        $nextNumber = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        $newPrNo = 'PR-'.$year.'-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;
            $newPrNo = 'PR-'.$year.'-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPrNo;
    }
}
