<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\PurchaseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePurchaseRequestRecordViewTest extends TestCase
{
    use RefreshDatabase;

    private function maintenanceHead(): User
    {
        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
            'onboarding_completed' => true,
            'must_change_password' => false,
        ]);
    }

    public function test_active_request_returns_200_and_excludes_issued_and_marks_active_tab(): void
    {
        $user = $this->maintenanceHead();

        $activePr = PurchaseRequest::create([
            'pr_no' => 'PR-2026-ACTIVE-01',
            'job_order_no' => 'JO-1001',
            'bus_no' => 'BUS-01',
            'item' => 'Alternator Belt',
            'quantity' => 1,
            'status' => 'Submitted',
        ]);

        $issuedPr = PurchaseRequest::create([
            'pr_no' => 'PR-2026-ISSUED-02',
            'job_order_no' => 'JO-1002',
            'bus_no' => 'BUS-02',
            'item' => 'Brake Shoes',
            'quantity' => 2,
            'status' => 'Issued',
        ]);

        $response = $this->actingAs($user)->get(route('purchase-requests'));

        $response->assertStatus(200);
        $response->assertSee('PR-2026-ACTIVE-01');
        $response->assertDontSee('PR-2026-ISSUED-02');
        $response->assertSee('data-maintenance-record-tab-link="active"', false);
        $response->assertSee('class="maintenance-record-tab is-active"', false);
        $response->assertSee('id="openPrModal"', false);
    }

    public function test_history_request_returns_200_and_includes_issued_and_marks_history_tab(): void
    {
        $user = $this->maintenanceHead();

        $activePr = PurchaseRequest::create([
            'pr_no' => 'PR-2026-ACTIVE-03',
            'job_order_no' => 'JO-1003',
            'bus_no' => 'BUS-03',
            'item' => 'Spark Plug',
            'quantity' => 4,
            'status' => 'Submitted',
        ]);

        $issuedPr = PurchaseRequest::create([
            'pr_no' => 'PR-2026-ISSUED-04',
            'job_order_no' => 'JO-1004',
            'bus_no' => 'BUS-04',
            'item' => 'Radiator Hose',
            'quantity' => 1,
            'status' => 'Issued',
        ]);

        $response = $this->actingAs($user)->get(route('purchase-requests', ['record_view' => 'history']));

        $response->assertStatus(200);
        $response->assertSee('PR-2026-ISSUED-04');
        $response->assertDontSee('PR-2026-ACTIVE-03');
        $response->assertSee('data-maintenance-record-tab-link="history"', false);
        $response->assertSee('class="maintenance-record-tab is-active"', false);
        $response->assertSee('Issued Purchase Requests are kept here for reference and audit history.');
        $response->assertDontSee('id="openPrModal"', false);
    }

    public function test_search_and_filter_on_history_preserves_record_view(): void
    {
        $user = $this->maintenanceHead();

        $issuedPr = PurchaseRequest::create([
            'pr_no' => 'PR-2026-ISSUED-05',
            'job_order_no' => 'JO-1005',
            'bus_no' => 'BUS-05',
            'item' => 'Clutch Disc',
            'quantity' => 1,
            'status' => 'Issued',
        ]);

        $response = $this->actingAs($user)->get(route('purchase-requests', [
            'record_view' => 'history',
            'search' => 'Clutch',
            'status' => 'Issued',
        ]));

        $response->assertStatus(200);
        $response->assertSee('PR-2026-ISSUED-05');
        $response->assertSee('<input type="hidden" name="record_view" value="history">', false);
    }

    public function test_new_pr_button_is_hidden_on_history_and_visible_on_active(): void
    {
        $user = $this->maintenanceHead();

        $activeResponse = $this->actingAs($user)->get(route('purchase-requests'));
        $activeResponse->assertStatus(200);
        $activeResponse->assertSee('id="openPrModal"', false);

        $historyResponse = $this->actingAs($user)->get(route('purchase-requests', ['record_view' => 'history']));
        $historyResponse->assertStatus(200);
        $historyResponse->assertDontSee('id="openPrModal"', false);
    }
}
