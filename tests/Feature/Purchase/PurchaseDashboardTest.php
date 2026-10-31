<?php

namespace Tests\Feature\Purchase;

use App\Models\Admin\User;
use App\Models\Purchase\MaintenanceRequest;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\ScheduledPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseUser(): User
    {
        return User::factory()->create([
            'department' => 'Purchase',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard-purchase'));
        $response->assertRedirect('/');
    }

    public function test_purchase_dashboard_renders_successfully_with_charts_and_financial_data(): void
    {
        $user = $this->purchaseUser();

        // Create sample purchase orders
        PurchaseOrder::create([
            'po_no' => 'PO-2026-TEST-01',
            'po_date' => now()->toDateString(),
            'supplier_name' => 'Apex Parts Supply',
            'net_amount' => 5000.00,
            'status' => 'Ordered',
        ]);

        PurchaseOrder::create([
            'po_no' => 'PO-2026-TEST-02',
            'po_date' => now()->toDateString(),
            'supplier_name' => 'Metro Bus Components',
            'net_amount' => 8500.00,
            'status' => 'Delivered',
        ]);

        // Create sample purchase requests
        MaintenanceRequest::create([
            'pr_no' => 'PR-2026-TEST-01',
            'job_order_no' => 'JO-2026-0001',
            'bus_no' => 'BUS-101',
            'item' => 'Brake Pad Set - Qty: 4 set',
            'quantity' => 4,
            'status' => 'For Purchase',
            'source_type' => 'Maintenance Request',
        ]);

        // Create scheduled purchase
        ScheduledPurchase::create([
            'schedule_no' => 'SCH-TEST-01',
            'schedule_name' => 'Monthly Engine Lubricants',
            'supplier_name' => 'Apex Parts Supply',
            'item' => 'Engine Oil 15W-40',
            'quantity' => 50,
            'unit' => 'L',
            'frequency' => 'Monthly',
            'start_date' => now()->subMonth(),
            'next_purchase_date' => now()->addDays(3),
            'estimated_cost' => 12500.00,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard-purchase'));

        $response->assertOk();
        $response->assertViewHas('totalSpend', 13500.00);
        $response->assertViewHas('totalOrdersCount', 2);
        $response->assertViewHas('avgOrderValue', 6750.00);
        $response->assertViewHas('spendTrendMonthly');
        $response->assertViewHas('spendTrendWeekly');
        $response->assertViewHas('fulfillmentDistribution');
        $response->assertViewHas('topSuppliers');

        // Assert rendered HTML contains charts and key elements (using false for unescaped ampersand)
        $response->assertSee('Purchase Dashboard');
        $response->assertSee('Procurement Spend & PO Volume', false);
        $response->assertSee('Fulfillment & Order Stages', false);
        $response->assertSee('Top Suppliers by Spend');
        $response->assertSee('Requisition Sources', false);
        $response->assertSee('purchaseSpendTrendChart');
        $response->assertSee('purchaseFulfillmentChart');
        $response->assertSee('Apex Parts Supply');
        $response->assertSee('PO-2026-TEST-01');
        $response->assertSee('PR-2026-TEST-01');
    }

    public function test_purchase_dashboard_fulfills_shared_ui_contracts(): void
    {
        $user = $this->purchaseUser();

        $response = $this->actingAs($user)->get(route('dashboard-purchase'));

        $response->assertOk();
        $response->assertSee('purchase-module-page');
        $response->assertSee('records-page');
        $response->assertSee('purchase-dashboard-page');

        // Verify Blade file directly contains the mandatory asset references
        $source = file_get_contents(resource_path('views/Purchase/dashboard-purchase.blade.php'));
        $this->assertStringContainsString('resources/css/Purchase/purchase-module-ui.css', $source);
        $this->assertStringContainsString('resources/js/Purchase/dashboard-purchase.js', $source);
    }
}
