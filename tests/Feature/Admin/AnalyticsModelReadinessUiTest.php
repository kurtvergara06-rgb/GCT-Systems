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
                'model_version' => '1.2.0',
                'split_strategy' => 'chronological_80_20',
                'selected_model' => [
                    'key' => 'random_forest',
                    'name' => 'Random Forest',
                    'family' => 'Nonlinear ensemble (200 trees)',
                ],
                'candidate_models' => [
                    [
                        'key' => 'operator_baseline',
                        'name' => 'Operator Route Baseline',
                        'family' => 'Published route estimate',
                        'selected' => false,
                        'status' => 'evaluated',
                        'metrics' => ['mae' => 12.5, 'rmse' => 15.2, 'r2' => 0.31],
                    ],
                    [
                        'key' => 'random_forest',
                        'name' => 'Random Forest',
                        'family' => 'Nonlinear ensemble (200 trees)',
                        'selected' => true,
                        'status' => 'evaluated',
                        'metrics' => ['mae' => 8.1, 'rmse' => 10.4, 'r2' => 0.68],
                    ],
                ],
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

    public function test_predictive_landing_page_uses_trip_eta_without_removed_model_readiness_panel(): void
    {
        $this->fakeModelStatuses();

        $response = $this->actingAs($this->adminUser())
            ->get(route('analytics.stage', ['stage' => 'predictive']));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.predictive.layout')
            ->assertSeeText('Predictive Analytics')
            ->assertSeeText('Trip Delay & ETA')
            ->assertSeeText('Recorded Trips')
            ->assertDontSeeText('Production ML Readiness')
            ->assertDontSeeText('ETA Model Comparison');
    }

    public function test_prescriptive_page_keeps_decision_support_without_removed_readiness_panel(): void
    {
        $this->fakeModelStatuses();

        $response = $this->actingAs($this->adminUser())
            ->get(route('analytics.stage', ['stage' => 'prescriptive']));

        $response->assertOk()
            ->assertViewIs('Admin.Analytics.prescriptive.layout')
            ->assertViewHas('operationsPrescriptive')
            ->assertSeeText('Operations Scheduling')
            ->assertSeeText('Recommended Departure & Trip Schedules')
            ->assertDontSeeText('Demonstration mode:')
            ->assertDontSeeText('Live model readiness is shown separately below')
            ->assertDontSeeText('Production ML Readiness');
    }
}
