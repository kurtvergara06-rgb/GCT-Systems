<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
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

    public function __invoke(Request $request)
    {
        $recordView = $request->input('record_view') === 'history'
            ? 'history'
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

        $purchaseRequests = $query
            ->latest()
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
            ->orderByDesc('created_at')
            ->get();

        $selectedJobOrder = null;
        if ($request->filled('job_order_id')) {
            $selectedJobOrder = JobOrder::find($request->job_order_id);
        }

        $statuses = $recordView === 'history'
            ? ['Issued']
            : array_values(array_filter($this->statuses, fn ($status) => $status !== 'Issued'));
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
            'isMaintenanceAdmin',
            'recordView',
            'workToPerformByJobOrder'
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
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();
        $department = strtolower(trim((string) ($user->department ?? '')));
        $role = strtolower(trim((string) ($user->role ?? '')));

        $department = preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $department));
        $role = preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $role));

        $isMaintenanceHead = $department === 'maintenance'
            && in_array($role, ['head', 'admin', 'maintenance head', 'maintenance admin'], true);

        $isSystemAdmin = $department === 'admin'
            && in_array($role, ['head', 'admin', 'system admin'], true);

        return $isMaintenanceHead || $isSystemAdmin;
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

        preg_match('/PR-' . $year . '-(\d+)/', $lastPr->pr_no, $matches);
        $nextNumber = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;
            $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPrNo;
    }
}
