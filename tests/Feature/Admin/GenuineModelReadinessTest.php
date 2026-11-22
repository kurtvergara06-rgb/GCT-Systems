<?php

namespace Tests\Feature\Admin;

use App\Services\AiModelStatusService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenuineModelReadinessTest extends TestCase
{
    private function models(array $overrides = []): \Illuminate\Support\Collection
    {
        $claimed = array_merge([
            'success' => true, 'model_ready' => true, 'is_production_model' => true,
            'data_source' => 'genuine', 'provenance_verified' => true,
            'sample_count' => 156, 'model_version' => 'claimed-version',
            'bus_model_ready' => true, 'driver_model_ready' => false,
            'bus_sample_count' => 367, 'driver_sample_count' => 6,
        ], $overrides);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response($claimed)]);
        return collect(app(AiModelStatusService::class)->all())->keyBy('key');
    }

    public function test_endpoint_genuine_and_verified_flags_cannot_bypass_independent_audit(): void
    {
        foreach ($this->models() as $model) {
            $this->assertTrue($model->reachable);
            $this->assertFalse($model->ready);
            $this->assertFalse($model->provenance_verified);
            $this->assertSame('Provenance Not Verified', $model->state);
            $this->assertSame('Source Not Verified', $model->data_source);
            $this->assertSame('Verification Pending', $model->count_label);
            $this->assertSame(0, $model->sample_count);
            $this->assertStringContainsString('reported loaded', $model->availability);
        }
    }

    public function test_reported_rows_are_retained_but_never_added_to_verified_counts(): void
    {
        $models = $this->models();
        $this->assertSame(156, $models['fuel']->reported_sample_count);
        $schedule = $models['scheduling'];
        $this->assertNull($schedule->reported_sample_count);
        $this->assertSame(0, $schedule->sample_count);
        $this->assertSame(367, $schedule->details[0]->reported_sample_count);
        $this->assertSame(6, $schedule->details[1]->reported_sample_count);
        $this->assertSame(0, $schedule->details[0]->sample_count);
        $this->assertSame(0, $schedule->details[1]->sample_count);
    }

    public function test_conflicting_sample_source_wins_over_genuine_claim(): void
    {
        $model = $this->models(['source' => 'sample'])['delay'];
        $this->assertSame('Sample / Synthetic Data Reported', $model->data_source);
        $this->assertFalse($model->ready);
        $this->assertSame(0, $model->sample_count);
        $this->assertStringContainsString('Conflicting', $model->reason);
    }

    public function test_unknown_and_zero_history_are_pending_not_verified_zero_records(): void
    {
        $model = $this->models(['data_source' => '', 'sample_count' => 0, 'model_ready' => false])['eta'];
        $this->assertSame('Verification Pending', $model->count_label);
        $this->assertSame('Source Not Verified', $model->data_source);
        $this->assertSame(0, $model->reported_sample_count);
        $this->assertStringContainsString('not reported ready', $model->availability);
    }

    public function test_unverified_benchmarks_are_not_presented_as_validated_comparisons(): void
    {
        $model = $this->models(['candidate_models' => [['name' => 'claimed winner', 'selected' => true]]])['eta'];
        $this->assertSame([], $model->candidate_models);
        $this->assertSame('', $model->selected_model);
        $this->assertSame('', $model->model_version);
    }
}
