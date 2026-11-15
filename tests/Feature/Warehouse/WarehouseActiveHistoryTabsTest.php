<?php

namespace Tests\Feature\Warehouse;

use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
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
            ->assertSee('Active Part Requests')
            ->assertSee('PR-ACTIVE-TAB')
            ->assertSee('send-purchase-btn', false)
            ->assertDontSee('PR-HISTORY-TAB');

        $history = $this->get(route('part-requests', ['view' => 'history']));

        $history
            ->assertOk()
            ->assertSee('Part Request History')
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
}
