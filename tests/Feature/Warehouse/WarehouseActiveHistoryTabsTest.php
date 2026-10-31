<?php

namespace Tests\Feature\Warehouse;

use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Maintenance\JobOrder;
use App\Models\Warehouse\InventoryItem;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseActiveHistoryTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $user = User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($user);
    }

    public function test_part_requests_are_separated_into_active_and_history_views(): void
    {
        PurchaseRequest::create([
            'pr_no' => 'PR-ACTIVE-TAB',
            'job_order_no' => 'JO-ACTIVE-TAB',
            'bus_no' => 'BUS-101',
            'item' => 'Brake Pad - Qty:1 pcs',
            'quantity' => 1,
            'status' => 'Approved',
            'source_type' => 'Maintenance Request',
        ]);

        PurchaseRequest::create([
            'pr_no' => 'PR-HISTORY-TAB',
            'job_order_no' => 'JO-HISTORY-TAB',
            'bus_no' => 'BUS-102',
            'item' => 'Oil Filter - Qty:1 pcs',
            'quantity' => 1,
            'status' => 'Issued',
            'warehouse_status' => 'Issued',
            'source_type' => 'Maintenance Request',
            'issued_at' => now(),
        ]);

        $active = $this->get(route('part-requests', ['view' => 'active']));

        $active
            ->assertOk()
            ->assertSee('Purchase Request Records')
            ->assertSee('Active')
            ->assertSee('PR-ACTIVE-TAB')
            ->assertSee('send-purchase-btn', false)
            ->assertDontSee('PR-HISTORY-TAB');

        $history = $this->get(route('part-requests', ['view' => 'history']));

        $history
            ->assertOk()
            ->assertSee('Completed and closed Warehouse requisitions are kept here')
            ->assertSee('PR-HISTORY-TAB')
            ->assertDontSee('PR-ACTIVE-TAB')
            ->assertDontSee('send-purchase-btn', false)
            ->assertDontSee('approve-issue-btn', false)
            ->assertDontSee('prepare-part-btn', false)
            ->assertDontSee('issue-part-btn', false);
    }

    public function test_incoming_deliveries_are_separated_into_active_and_read_only_history(): void
    {
        PurchaseOrder::create([
            'po_no' => 'PO-ACTIVE-TAB',
            'po_date' => now(),
            'supplier_name' => 'Active Supplier',
            'items' => [[
                'item_description' => 'Brake Pad',
                'quantity' => 2,
                'unit' => 'pcs',
            ]],
            'status' => 'For Delivery',
        ]);

        PurchaseOrder::create([
            'po_no' => 'PO-HISTORY-TAB',
            'po_date' => now()->subDay(),
            'supplier_name' => 'History Supplier',
            'items' => [[
                'item_description' => 'Oil Filter',
                'quantity' => 3,
                'unit' => 'pcs',
            ]],
            'status' => 'Delivered',
            'inventory_posted_at' => now()->subHour(),
        ]);

        $active = $this->get(route('incoming-deliveries', ['view' => 'active']));

        $active
            ->assertOk()
            ->assertSee('Active Incoming Deliveries')
            ->assertSee('PO-ACTIVE-TAB')
            ->assertDontSee('PO-HISTORY-TAB')
            ->assertSee('receive-delivery-btn', false);

        $history = $this->get(route('incoming-deliveries', ['view' => 'history']));

        $history
            ->assertOk()
            ->assertSee('Delivery History')
            ->assertSee('PO-HISTORY-TAB')
            ->assertDontSee('PO-ACTIVE-TAB')
            ->assertDontSee('receive-delivery-btn', false)
            ->assertSee('Read only');
    }


    public function test_warehouse_cannot_issue_a_job_order_without_an_approved_pr(): void
    {
        InventoryItem::create([
            'item_code' => 'FILTER-JO-ISSUE',
            'parts_name' => 'Fuel Filter',
            'item_name' => 'Fuel Filter',
            'category' => 'Parts',
            'quantity_available' => 4,
            'on_hand' => 4,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);

        $jo = JobOrder::create([
            'job_order_no' => 'JO-WH-ISSUE-001',
            'bus_no' => 'BUS-200',
            'problem_issue' => 'Replace fuel filter',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'Fuel Filter - Qty: 2 pcs',
            'review_status' => 'Approved',
            'status' => 'On Hold',
            'part_status' => 'Not Requested',
        ]);

        $this->post(route('part-requests.job-order.issue', $jo))
            ->assertRedirect(route('part-requests', ['view' => 'pr']))
            ->assertSessionHas('error');
        $this->assertSame('Not Requested', $jo->fresh()->part_status);
        $this->assertDatabaseMissing('inventory_issuances', ['reference_no' => $jo->job_order_no]);
        $this->assertDatabaseHas('inventory_items', [
            'item_code' => 'FILTER-JO-ISSUE', 'quantity_available' => 4,
        ]);
    }

    public function test_linked_pr_does_not_hide_available_jo_or_enable_duplicate_issue(): void
    {
        InventoryItem::create([
            'item_code' => 'FILTER-WH-LINKED',
            'parts_name' => 'Fuel Filter',
            'item_name' => 'Fuel Filter',
            'category' => 'Parts',
            'quantity_available' => 5,
            'on_hand' => 0,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);
        $jo = JobOrder::create([
            'job_order_no' => 'JO-WH-LINKED-001',
            'bus_no' => 'BUS-303',
            'problem_issue' => 'Replace filter',
            'maintenance_type' => 'Repair',
            'assigned_mechanic' => 'Test Mechanic',
            'part_needed' => 'Fuel Filter - Qty: 2 pcs',
            'review_status' => 'Approved',
            'status' => 'On Hold',
            'part_status' => 'Approved',
        ]);
        PurchaseRequest::create([
            'pr_no' => 'PR-WH-LINKED-001',
            'job_order_no' => $jo->job_order_no,
            'bus_no' => $jo->bus_no,
            'item' => 'Fuel Filter - Qty: 2 pcs',
            'quantity' => 2,
            'status' => 'Approved',
            'source_type' => 'Maintenance Request',
        ]);

        $this->get(route('part-requests', ['view' => 'pr', 'search' => $jo->job_order_no]))
            ->assertOk()
            ->assertSee('PR-WH-LINKED-001')
            ->assertSee('JO-WH-LINKED-001')
            ->assertDontSee('action="'.route('part-requests.job-order.issue', $jo).'"', false);
    }

    public function test_retired_jo_tab_routes_to_pr_queue_without_exposing_unrequested_jobs(): void
    {
        InventoryItem::create([
            'item_code' => 'FILTER-JO-TAB',
            'parts_name' => 'Fuel Filter',
            'item_name' => 'Fuel Filter',
            'category' => 'Parts',
            'quantity_available' => 4,
            'on_hand' => 4,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 1,
        ]);

        JobOrder::create([
            'job_order_no' => 'JO-WH-TAB-001',
            'bus_no' => 'BUS-101',
            'problem_issue' => 'Replace filters',
            'maintenance_type' => 'Repair',
            'part_needed' => 'Fuel Filter - Qty: 2 pcs',
            'review_status' => 'Approved',
            'status' => 'On Hold',
        ]);

        $this->get(route('part-requests', ['view' => 'jo']))
            ->assertOk()
            ->assertSee('Purchase Request Records')
            ->assertDontSee('Job Order Requests')
            ->assertDontSee('JO-WH-TAB-001');

        $this->get(route('part-requests', ['view' => 'pr']))
            ->assertOk()
            ->assertDontSee('JO-WH-TAB-001');
    }

}
