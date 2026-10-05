<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\PmsSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MaintenanceWorkflowHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_cannot_bypass_warehouse_delivery_and_inventory_issuance(): void
    {
        $this->assertFalse(Route::has('purchase-requests.delivered'));
        $this->assertFalse(Route::has('purchase-requests.issue'));
    }

    public function test_bus_mutations_manage_default_pms_schedules_without_writes_during_index_get(): void
    {
        $operationHead = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);
        $maintenanceHead = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);

        $unsynchronizedBus = Bus::create([
            'bus_no' => 'DIRECT-BUS-01',
            'status' => 'Active',
        ]);

        $this->actingAs($maintenanceHead)->get(route('PMS-Scheduling'))->assertOk();
        $this->assertSame(0, PmsSchedule::where('bus_no', $unsynchronizedBus->bus_no)->count());

        $this->actingAs($operationHead)->post(route('bus-master-list.store'), [
            'bus_no' => 'SYNC-BUS-01',
            'status' => 'Active',
        ])->assertRedirect();

        $bus = Bus::where('bus_no', 'SYNC-BUS-01')->firstOrFail();
        $this->assertSame(4, PmsSchedule::where('bus_no', 'SYNC-BUS-01')->count());

        $this->actingAs($operationHead)->put(route('bus-master-list.update', $bus), [
            'bus_no' => 'SYNC-BUS-RENAMED',
            'status' => 'Active',
        ])->assertRedirect();

        $this->assertSame(0, PmsSchedule::where('bus_no', 'SYNC-BUS-01')->count());
        $this->assertSame(4, PmsSchedule::where('bus_no', 'SYNC-BUS-RENAMED')->count());
    }

    public function test_manual_job_order_creation_rejects_a_bus_with_an_active_job_order(): void
    {
        $staff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $bus = Bus::create([
            'bus_no' => 'BUS-WITH-ACTIVE-JO',
            'status' => 'Active',
        ]);
        JobOrder::create([
            'job_order_no' => 'JO-EXISTING-001',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Existing repair',
            'maintenance_type' => 'Repair',
            'status' => 'On Hold',
            'part_status' => 'No Parts Required',
        ]);

        $this->actingAs($staff)->post(route('job-orders.store'), [
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Second repair',
            'maintenance_type' => 'Repair',
        ])->assertSessionHasErrors('bus_no');

        $this->assertSame(1, JobOrder::where('bus_no', $bus->bus_no)->count());
    }

    public function test_manual_job_order_creation_and_safe_deletion_keep_bus_status_in_sync(): void
    {
        $staff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $bus = Bus::create([
            'bus_no' => 'BUS-MANUAL-LIFECYCLE',
            'status' => 'Active',
        ]);

        $this->actingAs($staff)->post(route('job-orders.store'), [
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Manual repair workflow',
            'maintenance_type' => 'Repair',
        ])->assertRedirect();

        $jobOrder = JobOrder::where('bus_no', $bus->bus_no)->firstOrFail();
        $this->assertSame('Under Maintenance', $bus->fresh()->status);

        $this->actingAs($staff)
            ->delete(route('job-orders.destroy', $jobOrder))
            ->assertRedirect();

        $this->assertDatabaseMissing('job_orders', ['id' => $jobOrder->id]);
        $this->assertSame('Active', $bus->fresh()->status);
    }

    public function test_completing_the_last_job_order_returns_bus_to_active(): void
    {
        $staff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
        ]);
        $bus = Bus::create([
            'bus_no' => 'BUS-RELEASE-01',
            'status' => 'Under Maintenance',
        ]);
        $jobOrder = JobOrder::create([
            'job_order_no' => 'JO-RELEASE-001',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Repair completed',
            'maintenance_type' => 'Repair',
            'status' => 'On Going',
            'part_status' => 'No Parts Required',
        ]);

        $this->actingAs($staff)
            ->post(route('job-orders.finish', $jobOrder))
            ->assertRedirect();

        $this->assertSame('Completed', $jobOrder->fresh()->status);
        $this->assertSame('Active', $bus->fresh()->status);
    }

    public function test_fuel_report_ajax_regions_have_unique_names(): void
    {
        $view = file_get_contents(resource_path('views/Maintenance/fuel-reports.blade.php'));

        preg_match_all('/data-ajax-region="([^"]+)"/', $view, $matches);

        $this->assertNotEmpty($matches[1]);
        $this->assertSame($matches[1], array_values(array_unique($matches[1])));
        $this->assertContains('monitoring-records', $matches[1]);
        $this->assertContains('fuel-records', $matches[1]);
    }
}
