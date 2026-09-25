<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\MaintenanceReferral;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\Incident;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createMaintenanceUser(): User
    {
        return User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'head',
            'status' => 'Active',
        ]);
    }

    public function test_dashboard_renders_successfully_for_authenticated_maintenance_user(): void
    {
        $user = $this->createMaintenanceUser();

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('Maintenance.maintenance-dashboard');
        $response->assertSee('Maintenance Dashboard');
        $response->assertSee('Active Job Orders');
        $response->assertSee('Mechanic Availability');
        $response->assertSee('PMS Attention Hub');
    }

    public function test_dashboard_correctly_computes_kpis_and_active_repairs(): void
    {
        $user = $this->createMaintenanceUser();

        $bus = Bus::create([
            'bus_no' => 'BUS-101',
            'status' => 'Active',
            'latest_gps_km' => 15200.0,
        ]);

        // 1 Active Job Order (On Going)
        $joOngoing = JobOrder::create([
            'job_order_no' => 'JO-2026-0001',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Brake pad wear',
            'maintenance_type' => 'Corrective',
            'status' => 'On Going',
            'start_date' => now(),
            'estimated_duration_value' => 2,
            'estimated_duration_unit' => 'Hours',
        ]);

        // 1 Active Job Order (On Hold)
        JobOrder::create([
            'job_order_no' => 'JO-2026-0002',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Transmission slipping',
            'maintenance_type' => 'Major Repair',
            'status' => 'On Hold',
            'part_needed' => 'Clutch Disc',
            'part_status' => 'Requested',
            'start_date' => now(),
            'estimated_duration_value' => 4,
            'estimated_duration_unit' => 'Hours',
        ]);

        // 1 Completed Job Order
        JobOrder::create([
            'job_order_no' => 'JO-2026-0003',
            'bus_no' => $bus->bus_no,
            'problem_issue' => 'Oil replacement',
            'maintenance_type' => 'PMS',
            'status' => 'Completed',
            'start_date' => now()->subDays(2),
            'completion_date' => now()->subDay(),
            'estimated_duration_value' => 1,
            'estimated_duration_unit' => 'Hours',
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalActiveJobOrders', 2);
        $response->assertViewHas('ongoingCount', 1);
        $response->assertViewHas('onHoldCount', 1);
        $response->assertViewHas('completedCount', 1);

        $response->assertSee('JO-2026-0001');
        $response->assertSee('JO-2026-0002');
        $response->assertSee('Clutch Disc');
    }

    public function test_dashboard_identifies_breakdown_repairs_and_referrals(): void
    {
        $user = $this->createMaintenanceUser();

        $bus = Bus::create([
            'bus_no' => 'BUS-202',
            'status' => 'Active',
            'latest_gps_km' => 10000.0,
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-9999',
            'bus_id' => $bus->id,
            'incident_type' => 'Bus Breakdown',
            'location' => 'EDSA Crossing',
            'description' => 'Radiator burst causing coolant leak.',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $user->id,
        ]);

        $referral = MaintenanceReferral::create([
            'incident_id' => $incident->id,
            'status' => 'Pending',
            'notes' => 'Urgent tow and cooling system overhaul required.',
            'referred_by' => $user->id,
        ]);

        // Active JO linked to referral
        $joBreakdown = JobOrder::create([
            'job_order_no' => 'JO-2026-0099',
            'bus_no' => $bus->bus_no,
            'maintenance_referral_id' => $referral->id,
            'incident_id' => $incident->id,
            'problem_issue' => 'Overheating radiator',
            'maintenance_type' => 'Emergency',
            'status' => 'On Going',
            'start_date' => now(),
            'estimated_duration_value' => 3,
            'estimated_duration_unit' => 'Hours',
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('breakdownRepairsCount', 1);
        $response->assertViewHas('referralPendingCount', 1);
        $response->assertSee('Breakdown INC-INC-9999');
        $response->assertSee('Radiator burst causing coolant leak.');
    }

    public function test_dashboard_tracks_pms_overdue_and_due_soon(): void
    {
        $user = $this->createMaintenanceUser();

        $busOverdue = Bus::create([
            'bus_no' => 'BUS-301',
            'status' => 'Active',
            'latest_gps_km' => 20500.0,
        ]);

        $busDueSoon = Bus::create([
            'bus_no' => 'BUS-302',
            'status' => 'Active',
            'latest_gps_km' => 19700.0,
        ]);

        // Overdue schedule: latest GPS (20,500) >= next PMS (20,000)
        PmsSchedule::create([
            'bus_no' => $busOverdue->bus_no,
            'maintenance_type' => 'PMS Level 2',
            'last_pms_km' => 10000,
            'next_pms_km' => 20000,
            'pms_interval_km' => 10000,
            'recommended_date' => now()->addDays(5),
        ]);

        // Due soon schedule: next PMS (20,000) - latest GPS (19,700) = 300 km <= 500
        PmsSchedule::create([
            'bus_no' => $busDueSoon->bus_no,
            'maintenance_type' => 'PMS Level 1',
            'last_pms_km' => 15000,
            'next_pms_km' => 20000,
            'pms_interval_km' => 5000,
            'recommended_date' => now()->addDays(10),
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('pmsOverdueCount', 1);
        $response->assertViewHas('pmsDueSoonCount', 1);
        $response->assertViewHas('totalPmsAttention', 2);

        $response->assertSee('BUS-301');
        $response->assertSee('BUS-302');
        $response->assertSee('Create JO');
    }

    public function test_dashboard_calculates_mechanic_roster_availability(): void
    {
        $user = $this->createMaintenanceUser();

        $mechanic1 = Mechanic::create([
            'mechanic_id' => 'MECH-001',
            'mechanic_name' => 'Roberto Cruz',
            'employment_status' => 'Active',
            'shift' => 'Day',
        ]);

        $mechanic2 = Mechanic::create([
            'mechanic_id' => 'MECH-002',
            'mechanic_name' => 'Danilo Santos',
            'employment_status' => 'Active',
            'shift' => 'Day',
        ]);

        // Log attendance for both
        MechanicAttendance::create([
            'mechanic_id' => $mechanic1->mechanic_id,
            'mechanic_name' => $mechanic1->mechanic_name,
            'attendance_date' => today(),
            'status' => 'Present',
        ]);

        MechanicAttendance::create([
            'mechanic_id' => $mechanic2->mechanic_id,
            'mechanic_name' => $mechanic2->mechanic_name,
            'attendance_date' => today(),
            'status' => 'Present',
        ]);

        // Assign mechanic 1 to an active Job Order -> On Duty
        JobOrder::create([
            'job_order_no' => 'JO-2026-MECH1',
            'bus_no' => 'BUS-404',
            'problem_issue' => 'Alternator check',
            'maintenance_type' => 'Electrical',
            'status' => 'On Going',
            'assigned_mechanic' => $mechanic1->mechanic_name,
            'start_date' => now(),
            'estimated_duration_value' => 2,
            'estimated_duration_unit' => 'Hours',
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalMechanicsCount', 2);
        $response->assertViewHas('onDutyMechanicsCount', 1);
        $response->assertViewHas('availableMechanicsCount', 1);

        $response->assertSee('Roberto Cruz');
        $response->assertSee('Danilo Santos');
        $response->assertSee('JO-2026-MECH1');
    }

    public function test_dashboard_tracks_purchase_requests_and_fuel_reports(): void
    {
        $user = $this->createMaintenanceUser();

        PurchaseRequest::create([
            'pr_no' => 'PR-2026-0001',
            'job_order_no' => 'JO-2026-0001',
            'bus_no' => 'BUS-505',
            'status' => 'Submitted',
            'item' => 'Brake Fluid',
            'quantity' => 2,
        ]);

        FuelReport::create([
            'bus_no' => 'BUS-505',
            'fuel_liters' => 100.0,
            'distance_km' => 350.0,
            'report_date' => today(),
        ]);

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertSee('350.0 km');
        $response->assertSee('100.0 L');
        $response->assertSee('3.5 km/L');
    }

    public function test_dashboard_renders_empty_state_gracefully(): void
    {
        $user = $this->createMaintenanceUser();

        $response = $this->actingAs($user)->get(route('maintenance-dashboard'));

        $response->assertStatus(200);
        $response->assertSee('No active or recent job orders');
        $response->assertSee('All PMS Schedules Up to Date');
        $response->assertSee('No breakdown referrals');
    }
}
