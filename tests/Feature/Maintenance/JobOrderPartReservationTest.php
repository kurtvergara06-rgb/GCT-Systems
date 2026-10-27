<?php

namespace Tests\Feature\Maintenance;

use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\JobOrderPartReservation;
use App\Models\Maintenance\ItemRequest;
use App\Models\Warehouse\InventoryItem;
use App\Services\Maintenance\ItemRequestPartsService;
use App\Services\Maintenance\PartsReservationService;
use App\Services\Maintenance\PurchaseRequestPartsService;
use App\Services\Maintenance\JobOrderWorkflowService;
use App\Services\PartParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobOrderPartReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_job_orders_cannot_reserve_the_same_ten_filters(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'OIL-FILTER-RES-01',
            'item_name' => 'Oil Filter',
            'parts_name' => 'Oil Filter',
            'category' => 'Filters',
            'quantity_available' => 10,
            'on_hand' => 10,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $service = app(PartsReservationService::class);
        $orders = collect(range(1, 3))->map(fn (int $n) => JobOrder::create([
            'job_order_no' => sprintf('JO-2026-%04d', 900 + $n),
            'bus_no' => 'BUS-RES-'.$n,
            'problem_issue' => 'Oil filter replacement',
            'maintenance_type' => 'Corrective',
            'part_needed' => 'Oil Filter - Qty: 10 pc',
            'part_status' => 'Not Requested',
            'review_status' => 'For Review',
            'status' => 'On Hold',
        ]));
        $results = $orders->map(fn (JobOrder $jo) => DB::transaction(fn () => $service->reserveForApproval($jo)));
        $this->assertSame([10, 0, 0], $results->map(fn (array $r) => collect($r['parts'])->sum('reserved'))->all());
        $this->assertSame(10, (int) JobOrderPartReservation::where('inventory_item_id', $item->id)->sum('quantity'));
        $this->assertSame(10, (int) $item->fresh()->quantity_available);
        $this->assertSame('none', $results[1]['status']);
        $this->assertSame('none', $results[2]['status']);
    }

    public function test_ir_and_pr_use_approved_reservation_not_stock_already_claimed_by_another_jo(): void
    {
        InventoryItem::create([
            'item_code' => 'FUEL-RES-01',
            'item_name' => 'Fuel Filter',
            'parts_name' => 'Fuel Filter',
            'category' => 'Filters',
            'quantity_available' => 10,
            'on_hand' => 10,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $first = JobOrder::create([
            'job_order_no' => 'JO-2026-0950', 'bus_no' => 'BUS-RES-50',
            'problem_issue' => 'Replace filters', 'maintenance_type' => 'Corrective',
            'part_needed' => 'Fuel Filter - Qty: 10 pcs',
            'review_status' => 'For Review', 'status' => 'On Hold',
        ]);
        $second = JobOrder::create([
            'job_order_no' => 'JO-2026-0951', 'bus_no' => 'BUS-RES-51',
            'problem_issue' => 'Replace filters', 'maintenance_type' => 'Corrective',
            'part_needed' => 'Fuel Filter - Qty: 10 pcs',
            'review_status' => 'For Review', 'status' => 'On Hold',
        ]);
        $reservations = app(PartsReservationService::class);
        DB::transaction(fn () => $reservations->reserveForApproval($first));
        $snapshot = DB::transaction(fn () => $reservations->reserveForApproval($second));
        $second->update(['inventory_snapshot' => $snapshot]);
        $this->assertSame([], app(ItemRequestPartsService::class)->availableParts((string) $second->part_needed, $second));
        $missing = app(PurchaseRequestPartsService::class)->missingParts((string) $second->part_needed, $second);
        $this->assertSame(10, (int) $missing[0]['quantity']);
        $this->assertSame([], app(PurchaseRequestPartsService::class)->missingParts((string) $first->part_needed, $first));
    }
    public function test_unrelated_stock_out_cannot_consume_reserved_filters(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'OIL-FILTER-GUARD',
            'item_name' => 'Oil Filter',
            'parts_name' => 'Oil Filter',
            'category' => 'Filters',
            'quantity_available' => 10,
            'on_hand' => 10,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $jo = JobOrder::create([
            'job_order_no' => 'JO-2026-0990', 'bus_no' => 'BUS-RES-90',
            'problem_issue' => 'Replace oil filter',
            'maintenance_type' => 'Corrective', 'part_needed' => 'Oil Filter - Qty: 10 pcs',
            'review_status' => 'For Review', 'status' => 'On Hold',
        ]);
        DB::transaction(fn () => app(PartsReservationService::class)->reserveForApproval($jo));
        $ledger = app(\App\Services\Warehouse\InventoryLedgerService::class);
        try {
            $ledger->stockOut($item, 1, 'OTHER-REQUEST');
            $this->fail('Unrelated issuance must not consume reserved stock.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('reserved', $exception->getMessage());
        }
        $this->assertSame(10, (int) $item->fresh()->quantity_available);
        $ledger->stockOut($item, 10, 'IR-OWN', null, null, $jo->id);
        $this->assertSame(0, (int) $item->fresh()->quantity_available);
    }

    public function test_sequential_item_requests_cover_requirement_without_duplicate_purchase(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'OIL-FILTER-SPLIT',
            'item_name' => 'Oil Filter',
            'parts_name' => 'Oil Filter',
            'category' => 'Filters',
            'quantity_available' => 10,
            'on_hand' => 10,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-2026-0991',
            'bus_no' => 'BUS-RES-91',
            'problem_issue' => 'Replace oil filters',
            'maintenance_type' => 'Corrective',
            'part_needed' => 'Oil Filter - Qty: 10 pcs',
            'review_status' => 'Approved',
            'status' => 'On Hold',
            'inventory_snapshot' => ['shortages' => ['Oil Filter']],
        ]);
        JobOrderPartReservation::create([
            'job_order_id' => $jobOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
        ]);
        ItemRequest::create([
            'job_order_id' => $jobOrder->id,
            'status' => 'Issued',
            'requested_parts' => [['name' => 'Oil Filter', 'quantity' => 6, 'unit' => 'pcs']],
        ]);
        ItemRequest::create([
            'job_order_id' => $jobOrder->id,
            'status' => 'Submitted',
            'requested_parts' => [['name' => 'Oil Filter', 'quantity' => 4, 'unit' => 'pc']],
        ]);

        $this->assertSame([], app(PurchaseRequestPartsService::class)->missingParts(
            (string) $jobOrder->part_needed,
            $jobOrder
        ));
    }

}
