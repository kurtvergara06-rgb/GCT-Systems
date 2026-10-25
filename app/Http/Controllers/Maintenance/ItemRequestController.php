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
    public function index()
    {
        $requests = ItemRequest::with('jobOrder')->latest()->paginate(10);
        $eligibleJobs = JobOrder::query()
            ->where('review_status', 'Approved')
            ->whereNotNull('part_needed')
            ->where('part_needed', '!=', '')
            ->whereDoesntHave('itemRequest')
            ->orderByDesc('created_at')
            ->get(['id', 'job_order_no', 'bus_no', 'part_needed']);

        return view('Maintenance.item-requests', compact('requests', 'eligibleJobs'));
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
