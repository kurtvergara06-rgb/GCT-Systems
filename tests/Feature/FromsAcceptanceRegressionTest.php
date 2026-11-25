<?php

namespace Tests\Feature;

use App\Models\Admin\ActivityLog;
use App\Models\Admin\User;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FromsAcceptanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_export_is_admin_only_and_uses_all_list_filters(): void
    {
        $admin = $this->user('Admin');
        $maintenance = $this->user('Maintenance');
        $log = [
            'user_name' => 'QA Operator',
            'activity' => 'FROMS-021 acceptance activity',
            'module' => 'Maintenance',
            'event_type' => 'Created',
        ];
        foreach ([
            ['reference' => 'MATCH-021', 'updated_at' => now()],
            ['reference' => 'WRONG-MODULE', 'module' => 'Warehouse'],
            ['reference' => 'WRONG-EVENT', 'event_type' => 'Updated'],
            ['reference' => 'WRONG-QUERY', 'activity' => 'Different activity'],
        ] as $attributes) {
            ActivityLog::create(array_replace($log, $attributes));
        }

        $filters = ['search' => 'FROMS-021', 'module' => 'Maintenance', 'event' => 'Created', 'date' => 'today'];
        $url = route('admin.activity-logs.export', $filters);
        $this->actingAs($maintenance)->get($url)->assertForbidden();
        $this->actingAs($admin)->get(route('admin.activity-logs', $filters))->assertSee('Export CSV');
        $response = $this->get($url)->assertOk()->assertDownload('activity-logs.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('MATCH-021', $csv);
        $this->assertStringNotContainsString('WRONG-', $csv);
    }

    public function test_job_order_status_filters_match_visible_status_and_preserve_other_filters(): void
    {
        $user = $this->user('Maintenance');
        $defaults = [
            'bus_no' => 'QA-BUS',
            'problem_issue' => 'Acceptance test',
            'maintenance_type' => 'Repair',
            'start_date' => now(),
            'review_status' => 'Approved',
        ];
        foreach ([
            ['job_order_no' => 'QA-JO-ACTIVE', 'status' => 'On Going', 'part_status' => 'Issued', 'part_needed' => 'QA Filter - Qty: 1 pcs'],
            ['job_order_no' => 'QA-JO-HOLD', 'status' => 'On Hold', 'part_status' => 'Not Requested'],
            ['job_order_no' => 'QA-JO-REVIEW', 'status' => 'On Hold', 'review_status' => 'For Review'],
        ] as $attributes) {
            JobOrder::create(array_replace($defaults, $attributes));
        }

        $this->actingAs($user)->get(route('job-orders', ['status' => 'In Progress', 'part_status' => 'Fully Issued', 'sort' => 'job_order_no']))
            ->assertOk()->assertSee('QA-JO-ACTIVE')->assertDontSee('QA-JO-HOLD')->assertDontSee('QA-JO-REVIEW');
        $this->get(route('job-orders', ['status' => 'On Hold']))
            ->assertOk()->assertSee('QA-JO-HOLD')->assertDontSee('QA-JO-ACTIVE')->assertDontSee('QA-JO-REVIEW');
    }

    public function test_empty_purchase_request_is_rejected_without_inserting_a_row(): void
    {
        $count = PurchaseRequest::count();
        $this->actingAs($this->user('Maintenance'))->postJson(route('purchase-requests.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['job_order_no', 'bus_no']);
        $this->assertSame($count, PurchaseRequest::count());
    }

    public function test_empty_purchase_order_is_rejected_without_inserting_a_row(): void
    {
        $count = PurchaseOrder::count();
        $this->actingAs($this->user('Purchase'))->postJson(route('purchase-orders.store'), ['status' => 'For Review'])
            ->assertUnprocessable()->assertJsonValidationErrors(['supplier_name', 'items']);
        $this->assertSame($count, PurchaseOrder::count());
    }

    private function user(string $department): User
    {
        return User::factory()->create(['department' => $department, 'role' => 'head', 'status' => 'Active']);
    }
}
