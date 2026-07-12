<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ScheduledPurchase;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduledPurchaseController extends Controller
{
    private array $frequencies = [
        'Weekly', 'Biweekly', 'Monthly', 'Quarterly',
        'Semiannual', 'Yearly', 'Custom',
    ];

    public function index(Request $request)
    {
        $query = ScheduledPurchase::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('schedule_no', 'like', "%{$search}%")
                    ->orWhere('schedule_name', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%");
            });
        }

        if ($request->filled('frequency') && $request->frequency !== 'All Frequencies') {
            $query->where('frequency', $request->frequency);
        }

        if ($request->filled('status') && $request->status !== 'All Statuses') {
            match ($request->status) {
                'Active', 'Paused', 'Completed' => $query->where('status', $request->status),
                'Due Soon' => $query->where('status', 'Active')
                    ->whereBetween('next_purchase_date', [today(), today()->addDays(7)]),
                'Overdue' => $query->where('status', 'Active')
                    ->whereDate('next_purchase_date', '<', today()),
                default => null,
            };
        }

        $schedules = $query
            ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
            ->orderBy('next_purchase_date')
            ->paginate(8)
            ->withQueryString();

        return view('Purchase.scheduled-purchase', [
            'schedules' => $schedules,
            'frequencies' => $this->frequencies,
            'totalSchedules' => ScheduledPurchase::count(),
            'activeSchedules' => ScheduledPurchase::where('status', 'Active')->count(),
            'pausedSchedules' => ScheduledPurchase::where('status', 'Paused')->count(),
            'dueThisMonth' => ScheduledPurchase::where('status', 'Active')
                ->whereYear('next_purchase_date', now()->year)
                ->whereMonth('next_purchase_date', now()->month)
                ->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['schedule_no'] = $this->generateScheduleNo();
        ScheduledPurchase::create($data);

        return back()->with('success', 'Purchase schedule created successfully.');
    }

    public function update(Request $request, ScheduledPurchase $scheduledPurchase): RedirectResponse
    {
        $scheduledPurchase->update($this->validated($request));
        return back()->with('success', 'Purchase schedule updated successfully.');
    }

    public function toggleStatus(ScheduledPurchase $scheduledPurchase): RedirectResponse
    {
        if ($scheduledPurchase->status === 'Completed') {
            return back()->with('error', 'Completed schedules can no longer be resumed.');
        }

        $scheduledPurchase->update([
            'status' => $scheduledPurchase->status === 'Paused' ? 'Active' : 'Paused',
        ]);

        return back()->with('success', 'Schedule status updated.');
    }

    public function complete(ScheduledPurchase $scheduledPurchase): RedirectResponse
    {
        $scheduledPurchase->update(['status' => 'Completed']);
        return back()->with('success', 'Schedule marked as completed.');
    }

    public function destroy(ScheduledPurchase $scheduledPurchase): RedirectResponse
    {
        $scheduledPurchase->delete();
        return back()->with('success', 'Purchase schedule deleted.');
    }

    public function createPo(ScheduledPurchase $scheduledPurchase): RedirectResponse
    {
        if ($scheduledPurchase->status !== 'Active') {
            return back()->with('error', 'Only active schedules can create a PO.');
        }

        if ($scheduledPurchase->next_purchase_date->isFuture()) {
            return back()->with('error', 'This schedule is not due yet.');
        }

        $purchaseOrder = DB::transaction(function () use ($scheduledPurchase) {
            $quantity = max(0.01, (float) $scheduledPurchase->quantity);
            $total = (float) $scheduledPurchase->estimated_cost;

            $po = PurchaseOrder::create([
                'po_no' => $this->generatePoNo(),
                'po_date' => now()->toDateString(),
                'purchase_request_id' => null,
                'supplier_name' => $scheduledPurchase->supplier_name,
                'supplier_address_tel' => $scheduledPurchase->supplier_contact,
                'terms' => null,
                'terms_of_payment' => null,
                'purpose' => 'Scheduled purchase: ' . $scheduledPurchase->schedule_name,
                'items' => [[
                    'pr_no' => $scheduledPurchase->schedule_no,
                    'bus_no' => 'SCHEDULED',
                    'employee' => '',
                    'item_description' => $scheduledPurchase->item,
                    'quantity' => $quantity,
                    'unit' => $scheduledPurchase->unit,
                    'cost' => round($total / $quantity, 2),
                    'amount' => round($total, 2),
                ]],
                'gross_amount' => $total,
                'delivery_fee' => 0,
                'discount' => 0,
                'vat' => 0,
                'net_amount' => $total,
                'status' => 'Ordered',
            ]);

            $scheduledPurchase->update([
                'last_po_id' => $po->id,
                'last_purchased_at' => now(),
                'next_purchase_date' => $this->nextDate($scheduledPurchase),
            ]);

            return $po;
        });

        return redirect()->route('purchase-orders')
            ->with('success', "{$purchaseOrder->po_no} created from scheduled purchase.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'schedule_name' => 'required|string|max:255',
            'supplier_name' => 'required|string|max:255',
            'supplier_contact' => 'nullable|string|max:255',
            'item' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:50',
            'frequency' => ['required', Rule::in($this->frequencies)],
            'custom_interval_days' => 'nullable|required_if:frequency,Custom|integer|min:1',
            'start_date' => 'required|date',
            'next_purchase_date' => 'required|date',
            'estimated_cost' => 'required|numeric|min:0',
            'status' => ['required', Rule::in(['Active', 'Paused', 'Completed'])],
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function nextDate(ScheduledPurchase $schedule): string
    {
        $date = Carbon::parse($schedule->next_purchase_date);

        return match ($schedule->frequency) {
            'Weekly' => $date->addWeek()->toDateString(),
            'Biweekly' => $date->addWeeks(2)->toDateString(),
            'Monthly' => $date->addMonthNoOverflow()->toDateString(),
            'Quarterly' => $date->addMonthsNoOverflow(3)->toDateString(),
            'Semiannual' => $date->addMonthsNoOverflow(6)->toDateString(),
            'Yearly' => $date->addYearNoOverflow()->toDateString(),
            'Custom' => $date->addDays(max(1, (int) $schedule->custom_interval_days))->toDateString(),
            default => $date->addMonthNoOverflow()->toDateString(),
        };
    }

    private function generateScheduleNo(): string
    {
        return $this->nextNumber('SCH', ScheduledPurchase::class, 'schedule_no');
    }

    private function generatePoNo(): string
    {
        return $this->nextNumber('PO', PurchaseOrder::class, 'po_no');
    }

    private function nextNumber(string $prefix, string $model, string $column): string
    {
        $year = now()->format('Y');
        $last = $model::where($column, 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('id')->value($column);
        $number = 1;

        if ($last && preg_match('/' . $prefix . '-' . $year . '-(\d+)/', $last, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        do {
            $value = "{$prefix}-{$year}-" . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while ($model::where($column, $value)->exists());

        return $value;
    }
}