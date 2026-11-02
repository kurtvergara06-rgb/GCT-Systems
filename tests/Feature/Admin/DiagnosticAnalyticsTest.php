<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use App\Models\Operation\Incident;
use App\Models\Operation\IncidentReplacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiagnosticAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/analytics/fleet-trip/predict' => Http::response(['success' => false], 503),
            '*/eta/predict' => Http::response(['success' => false], 503),
            '*' => Http::response([], 200),
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
        ]);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('analytics.stage', ['stage' => 'diagnostic']));

        $response->assertRedirect(route('login'));
    }

    public function test_incident_diagnostic_view_renders_empty_live_data_when_no_records(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('analytics.stage', [
            'stage' => 'diagnostic',
            'domain' => 'fleet-trip',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.diagnostic.layout')
            ->assertViewHas('activeDiagnosticView', 'incident')
            ->assertViewHas('incidentDiagnostics')
            ->assertSeeText('Incident & Replacement')
            ->assertSeeText('Primary Root Cause')
            ->assertSeeText('Average Downtime');

        $incidentData = $response->viewData('incidentDiagnostics');
        $this->assertFalse($incidentData->is_demo);
        $this->assertSame(0, $incidentData->total_incidents);
    }

    public function test_incident_diagnostic_view_renders_live_database_records(): void
    {
        $admin = $this->createAdmin();

        $busA = Bus::create([
            'bus_no' => 'GCT-101',
            'plate_no' => 'ABC-1234',
            'status' => 'Active',
            'capacity' => 50,
        ]);

        $busB = Bus::create([
            'bus_no' => 'GCT-102',
            'plate_no' => 'XYZ-5678',
            'status' => 'Active',
            'capacity' => 50,
        ]);

        $incident = Incident::create([
            'incident_no' => 'INC-LIVE-001',
            'bus_id' => $busA->id,
            'incident_type' => 'Bus Breakdown',
            'status' => 'Resolved',
            'incident_reported_at' => now()->subHours(2),
            'location' => 'South Expressway Exit 5',
            'description' => 'Radiator burst leading to engine overheating',
            'resolution_notes' => 'Towed to repair bay and replacement dispatched',
            'resolved_at' => now()->subHour(),
        ]);

        IncidentReplacement::create([
            'incident_id' => $incident->id,
            'original_bus_id' => $busA->id,
            'replacement_bus_id' => $busB->id,
            'dispatched_at' => now()->subMinutes(80),
            'dispatched_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.stage', [
            'stage' => 'diagnostic',
            'domain' => 'fleet-trip',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.diagnostic.layout')
            ->assertViewHas('activeDiagnosticView', 'incident')
            ->assertSeeText('INC-LIVE-001')
            ->assertSeeText('GCT-101')
            ->assertSeeText('GCT-102');

        $incidentData = $response->viewData('incidentDiagnostics');
        $this->assertFalse($incidentData->is_demo);
        $this->assertEquals(1, $incidentData->total_incidents);
        $this->assertEquals(1, $incidentData->breakdowns_count);
        $this->assertEquals(1, $incidentData->replacements_count);
    }

    public function test_fuel_diagnostic_view_renders_empty_live_data_when_no_records(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('analytics.stage', [
            'stage' => 'diagnostic',
            'domain' => 'fuel',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.diagnostic.layout')
            ->assertViewHas('activeDiagnosticView', 'fuel')
            ->assertViewHas('fuelDiagnostics')
            ->assertSeeText('Fuel & Consumption')
            ->assertSeeText('Demo Data')
            ->assertSeeText('Demonstration Diagnostic Dataset Active')
            ->assertSeeText('Vehicle-Specific Historical Baseline');

        $fuelData = $response->viewData('fuelDiagnostics');
        $this->assertFalse($fuelData->is_demo);
        $this->assertEquals(0.0, $fuelData->total_fuel);
    }

    public function test_fuel_diagnostic_view_calculates_objective_6_2_2_baseline_and_anomalies(): void
    {
        $admin = $this->createAdmin();

        $bus = Bus::create([
            'bus_no' => 'GCT-301',
            'plate_no' => 'FL-301',
            'status' => 'Active',
            'capacity' => 50,
        ]);

        // Create 3 historical baseline records with high efficiency (avg 4.0 km/L)
        FuelReport::create([
            'bus_no' => 'GCT-301',
            'report_date' => now()->subDays(10),
            'fuel_liters' => 50,
            'distance_km' => 200,
            'km_per_liter' => 4.0,
            'status' => 'Approved',
        ]);
        FuelReport::create([
            'bus_no' => 'GCT-301',
            'report_date' => now()->subDays(7),
            'fuel_liters' => 50,
            'distance_km' => 200,
            'km_per_liter' => 4.0,
            'status' => 'Approved',
        ]);
        FuelReport::create([
            'bus_no' => 'GCT-301',
            'report_date' => now()->subDays(4),
            'fuel_liters' => 50,
            'distance_km' => 200,
            'km_per_liter' => 4.0,
            'status' => 'Approved',
        ]);

        // Create 1 anomalous record in selected period with a 35% drop (2.6 km/L)
        FuelReport::create([
            'bus_no' => 'GCT-301',
            'report_date' => now()->subDay(),
            'fuel_liters' => 50,
            'distance_km' => 130,
            'km_per_liter' => 2.6,
            'status' => 'Approved',
        ]);

        $response = $this->actingAs($admin)->get(route('analytics.stage', [
            'stage' => 'diagnostic',
            'domain' => 'fuel',
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.diagnostic.layout')
            ->assertViewHas('activeDiagnosticView', 'fuel');

        $fuelData = $response->viewData('fuelDiagnostics');
        $this->assertFalse($fuelData->is_demo);
        $this->assertEquals(50.0, $fuelData->total_fuel);
        $this->assertGreaterThanOrEqual(1, $fuelData->anomalies_count);
        $response->assertSeeText('Vehicle-Specific Historical Baseline');
    }
}
