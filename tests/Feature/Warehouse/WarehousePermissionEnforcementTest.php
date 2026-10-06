<?php

namespace Tests\Feature\Warehouse;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Warehouse\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehousePermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create([
            'department' => 'Warehouse',
            'role' => 'staff',
            'status' => 'Active',
        ]);
    }

    private function permissions(bool $view, bool $edit, bool $approve): void
    {
        $role = RolePermission::where('role_key', 'warehouse_staff')->firstOrFail();
        $permissions = $role->permissions;
        data_set($permissions, 'warehouse.view', $view);
        data_set($permissions, 'warehouse.edit', $edit);
        data_set($permissions, 'warehouse.approve', $approve);
        $role->update(['permissions' => $permissions]);
    }

    private function request(string $suffix = 'PERM'): PurchaseRequest
    {
        return PurchaseRequest::create([
            'pr_no' => "PR-{$suffix}",
            'job_order_no' => "JO-{$suffix}",
            'bus_no' => "BUS-{$suffix}",
            'item' => 'Brake Pad - Qty: 1 pcs',
            'quantity' => 1,
            'status' => 'Approved',
            'warehouse_status' => 'Pending Warehouse Approval',
            'source_type' => 'Maintenance Request',
        ]);
    }

    public function test_view_permission_blocks_all_warehouse_pages(): void
    {
        $staff = $this->staff();
        $this->permissions(false, true, true);

        foreach (['warehouse.dashboard', 'inventory', 'part-requests', 'incoming-deliveries', 'stock-movements'] as $route) {
            $this->actingAs($staff)->get(route($route))->assertForbidden();
        }
    }

    public function test_edit_permission_blocks_direct_inventory_receive_and_issue_requests(): void
    {
        $staff = $this->staff();
        $this->permissions(true, false, true);
        $item = InventoryItem::create([
            'item_code' => 'PERM-ITEM',
            'item_name' => 'Brake Pad',
            'category' => 'Parts',
            'quantity_available' => 2,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 0,
        ]);
        $request = $this->request('EDIT');
        $request->update(['warehouse_status' => 'Approved for Issue']);
        $order = PurchaseOrder::create([
            'po_no' => 'PO-PERM-EDIT',
            'po_date' => today(),
            'supplier_name' => 'Permission Supplier',
            'items' => [['item_description' => 'Brake Pad', 'quantity' => 1, 'unit' => 'pcs', 'cost' => 1]],
            'status' => 'For Delivery',
        ]);

        $this->actingAs($staff)->post(route('inventory.store'))->assertForbidden();
        $this->actingAs($staff)->post(route('incoming-deliveries.receive', $order))->assertForbidden();
        $this->actingAs($staff)->post(route('part-requests.issue', $request))->assertForbidden();

        $this->assertNull($order->fresh()->inventory_posted_at);
        $this->assertSame(2, (int) $item->fresh()->quantity_available);
    }

    public function test_approve_permission_controls_send_to_purchase_workflow_for_staff(): void
    {
        $staff = $this->staff();
        InventoryItem::create([
            'item_code' => 'PERM-APPROVE',
            'item_name' => 'Brake Pad',
            'category' => 'Parts',
            'quantity_available' => 0,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 0,
        ]);
        $request = $this->request('APPROVE');

        $this->permissions(true, true, false);
        $this->actingAs($staff)
            ->post(route('part-requests.send-to-purchase', $request))
            ->assertForbidden();

        $this->assertSame(
            0,
            PurchaseRequest::query()
                ->where('job_order_no', $request->job_order_no)
                ->where('id', '!=', $request->id)
                ->count()
        );

        $this->permissions(true, true, true);
        $this->actingAs($staff)
            ->post(route('part-requests.send-to-purchase', $request))
            ->assertRedirect()
            ->assertSessionHas('success');

        $missingRequest = PurchaseRequest::query()
            ->where('job_order_no', $request->job_order_no)
            ->where('id', '!=', $request->id)
            ->firstOrFail();

        $this->assertSame('For Purchase', $missingRequest->status);
        $this->assertSame('Approved', $request->fresh()->status);
    }

    public function test_system_admin_retains_full_warehouse_access(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('warehouse.dashboard'))->assertOk();
        $this->actingAs($admin)->post(route('inventory.store'), [
            'item_code' => 'ADMIN-ITEM',
            'item_name' => 'Admin Added Item',
            'category' => 'Parts',
            'on_hand' => 0,
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 0,
        ])->assertRedirect('/inventory');

        $this->assertDatabaseHas('inventory_items', ['item_code' => 'ADMIN-ITEM']);
    }
}
