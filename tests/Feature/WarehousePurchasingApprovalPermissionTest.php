<?php

namespace Tests\Feature;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WarehousePurchasingApprovalPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $department): User
    {
        return User::factory()->create([
            'department' => $department,
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function denyApproval(User $user, string $module): void
    {
        $role = RolePermission::query()->where('role_key', $user->permissionRoleKey())->firstOrFail();
        $permissions = $role->permissions;
        data_set($permissions, $module.'.view', true);
        data_set($permissions, $module.'.edit', true);
        data_set($permissions, $module.'.approve', false);
        $role->update(['permissions' => $permissions]);
    }

    public function test_purchase_head_without_approve_permission_cannot_approve_or_reject_po(): void
    {
        $head = $this->actor('Purchase');
        $this->denyApproval($head, 'purchase');
        $order = PurchaseOrder::query()->create([
            'po_no' => 'PO-APPROVE-GATE-001',
            'po_date' => today(),
            'status' => 'For Review',
            'supplier_name' => 'Audit Supplier',
            'net_amount' => 100,
        ]);

        $this->actingAs($head)->get(route('purchase-orders'))->assertOk()
            ->assertDontSee('data-approve-url=', false);
        $this->actingAs($head)->patch(route('purchase-orders.approve', $order), [])
            ->assertForbidden();
        $this->actingAs($head)->patch(route('purchase-orders.review-decision', $order), [
            'decision' => 'reject', 'review_remarks' => 'Rejected without capability',
        ])->assertForbidden();

        $this->assertSame('For Review', $order->fresh()->status);
    }

    public function test_warehouse_head_without_approve_permission_cannot_forward_review_or_sign_restock(): void
    {
        $head = $this->actor('Warehouse');
        $this->denyApproval($head, 'warehouse');

        $request = PurchaseRequest::query()->create([
            'pr_no' => 'PR-WH-APPROVE-GATE',
            'job_order_no' => 'JO-WH-APPROVE-GATE',
            'bus_no' => 'BUS-WH-APPROVE-GATE',
            'item' => 'Brake pads - Qty: 1 pcs',
            'quantity' => 1,
            'status' => 'Submitted',
        ]);

        $this->actingAs($head)->get(route('part-requests'))->assertOk()
            ->assertDontSee('id="warehouseRestockPrModal"', false);
        $this->actingAs($head)->post(route('part-requests.send-to-purchase', $request))
            ->assertForbidden();
        $this->actingAs($head)->post(route('part-requests.return-for-revision', $request), [
            'revision_remarks' => 'Not permitted',
        ])->assertForbidden();
        $this->actingAs($head)->post(route('part-requests.restock.store'), [])
            ->assertForbidden();

        $this->assertSame('Submitted', $request->fresh()->status);
    }

    public function test_approval_mutation_routes_require_capability_middleware(): void
    {
        foreach (['purchase-orders.approve', 'purchase-orders.review-decision'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('system.permission:purchase,approve', $route->gatherMiddleware());
        }
        foreach ([
            'part-requests.send-to-purchase',
            'part-requests.return-for-revision',
            'part-requests.restock.store',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('system.permission:warehouse,approve', $route->gatherMiddleware());
        }
    }
}
