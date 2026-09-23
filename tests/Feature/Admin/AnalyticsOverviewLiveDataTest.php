<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsOverviewLiveDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_uses_live_database_values_instead_of_hard_coded_metrics(): void
    {
        $user = User::factory()->create();
        $batchId = DB::table('batch_uploads')->insertGetId([
            'file_name' => 'overview-test.csv',
            'stored_name' => 'overview-test.csv',
            'file_path' => 'testing/overview-test.csv',
            'file_type' => 'csv',
            'module' => 'Operation',
            'data_type' => 'GPS Trip Records',
            'bus_no' => 'GCT-101',
            'status' => 'Processed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('buses')->insert([
            'bus_no' => 'GCT-101',
            'status' => 'Active',
            'latest_gps_km' => 4800,
            'latest_gps_at' => now(),
            'last_pms_km' => 0,
            'pms_interval_km' => 5000,
            'next_pms_km' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $startedAt = now()->startOfDay()->addHours(8);
        DB::table('gps_trip_records')->insert([
            'batch_upload_id' => $batchId,
            'record_no' => 'OVERVIEW-GPS-001',
            'bus_no' => 'GCT-101',
            'grouping' => 'Lipa - Batangas',
            'trip_type' => 'Shuttle Service',
            'beginning_at' => $startedAt,
            'initial_location' => 'Lipa',
            'ending_at' => $startedAt->copy()->addMinutes(60),
            'final_location' => 'Batangas',
            'duration_minutes' => 60,
            'total_minutes' => 60,
            'in_motion_minutes' => 50,
            'idling_minutes' => 10,
            'mileage_km' => 12.50,
            'engine_hours' => 1.0,
            'severity' => 'Normal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fuel_reports')->insert([
            'report_date' => now()->toDateString(),
            'bus_no' => 'GCT-101',
            'distance_km' => 12.50,
            'fuel_liters' => 2.50,
            'km_per_liter' => 5.00,
            'status' => 'Completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_items')->insert([
            'item_code' => 'OV-001',
            'parts_name' => 'Overview Brake Pad',
            'item_name' => 'Overview Brake Pad',
            'category' => 'Brakes',
            'on_hand' => 1,
            'quantity_available' => 1,
            'unit' => 'pcs',
            'unit_of_measurement' => 'pcs',
            'reorder_level' => 2,
            'status' => 'Low Stock',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('analytics.overview', [
            'period' => 'this-month',
        ]));

        $response->assertOk()
            ->assertViewHas('tripCount', 1)
            ->assertViewHas('totalDistance', fn ($value) => abs((float) $value - 12.5) < 0.001)
            ->assertViewHas('totalFuel', fn ($value) => abs((float) $value - 2.5) < 0.001)
            ->assertViewHas('pmsAttentionCount', 1)
            ->assertViewHas('inventoryAttentionCount', 1)
            ->assertViewHas('pmsMilestoneValue', '200 km')
            ->assertSeeText('12.5 km')
            ->assertSeeText('2.5 L')
            ->assertDontSeeText('26,126 km')
            ->assertDontSeeText('3,842 L')
            ->assertDontSeeText('286 Trips');
    }

    public function test_overview_reports_honest_empty_states_when_no_operational_records_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('analytics.overview'));

        $response->assertOk()
            ->assertViewHas('tripCount', 0)
            ->assertViewHas('totalDistance', 0.0)
            ->assertViewHas('totalFuel', 0.0)
            ->assertViewHas('pmsAttentionCount', 0)
            ->assertViewHas('inventoryAttentionCount', 0)
            ->assertViewHas('projectedFuelValue', 'Not enough data')
            ->assertViewHas('findings', fn ($findings) => $findings->isEmpty())
            ->assertSeeText('No threshold-based priority finding is supported by the available records.')
            ->assertDontSeeText('BUS-015 exceeded its PMS mileage threshold.');
    }

    public function test_overview_normalizes_legacy_period_aliases(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('analytics.overview', ['period' => 'last-3-months']))
            ->assertOk()
            ->assertViewHas('period', 'last-90-days')
            ->assertViewHas('periodLabel', 'Last 90 Days');
    }
}
