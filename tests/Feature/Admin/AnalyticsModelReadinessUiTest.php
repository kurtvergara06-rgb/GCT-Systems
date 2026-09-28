<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsModelReadinessUiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'name' => 'System Administrator',
            'email' => 'analytics-admin@gct.test',
            'password' => Hash::make('Password123!'),
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function fakeModelStatuses(): void
    {
        Http::fake([
            '*/eta/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'source' => 'ml',
                'data_source' => 'genuine',
                'is_production_model' => true,
                'dataset_type' => 'GENUINE GCT GPS RECORDS',
                'sample_count' => 362,
                'reason' => 'ETA model is ready from genuine GCT GPS history.',
            ]),
            '*/fuel/status' => Http::response([
                'success' => true,
                'model_ready' => true,
                'data_source' => 'genuine',
                'is_production_model' => true,
                'dataset_type' => 'GENUINE GCT RECORDS',
                'sample_count' => 156,
                'reason' => 'Fuel model is ready from genuine GCT trip history.',
            ]),
            '*/delay/status' => Http::response([
                'success' => true,
                'model_ready' => false,
                'data_source' => 'genuine',
                'is_production_model' => false,
                'dataset_type' => 'GENUINE GCT RECORDS',
                'sample_count' => 0,
                'reason' => 'Insufficient genuine operational history for production delay training.',
            ]),
            '*/inventory/status' => Http::response([
                'success' => true,
                'model_ready' => false,
                'data_source' => 'genuine',
                'is_production_model' => false,
                'dataset_type' => 'GENUINE GCT RECORDS',
                'sample_count' => 0,
                'reason' => 'Insufficient genuine inventory history for production forecasting.',
            ]),
            '*/operation/auto-scheduling/ai/training/status' => Http::response([
                'success' => true,
                'bus_model_ready' => true,
                'driver_model_ready' => true,
                'data_source' => 'genuine',
                'dataset_type' => 'GENUINE GCT GPS + ATTENDANCE RECORDS',
                'bus_sample_count' => 100,
                'driver_sample_count' => 100,
            ]),
            '*' => Http::response(['success' => false], 200),
        ]);
    }

    public function test_predictive_all_exposes_production_model_readiness(): void
    {
        $this->fakeModelStatuses();

        $response = $this->actingAs($this->adminUser())
            ->get(route('analytics.stage', ['stage' => 'predictive']));

        $response->assertOk();
        $response->assertSee('Production ML Readiness');
        $response->assertSee('ETA Model');
        $response->assertSee('Fuel Model');
        $response->assertSee('Delay Model');
        $response->assertSee('Inventory Model');
        $response->assertSee('MODEL NOT READY');
        $response->assertSee('Insufficient genuine operational history for production delay training.');
    }

    public function test_prescriptive_all_separates_demo_recommendations_from_live_readiness(): void
    {
        $this->fakeModelStatuses();

        $response = $this->actingAs($this->adminUser())
            ->get(route('analytics.stage', ['stage' => 'prescriptive']));

        $response->assertOk();
        $response->assertSee('Demonstration mode:');
        $response->assertSee('Live model readiness is shown separately below');
        $response->assertSee('Production ML Readiness');
        $response->assertSee('MODEL NOT READY');
        $response->assertSee('Insufficient genuine inventory history for production forecasting.');
    }
}
