<?php

namespace Tests\Feature\Admin;

use App\Services\AiModelStatusService;
use ReflectionMethod;
use Tests\TestCase;

class GenuineModelReadinessTest extends TestCase
{
    private function standard(array $status): object
    {
        return (new ReflectionMethod(AiModelStatusService::class, 'normalizeStandard'))
            ->invoke(app(AiModelStatusService::class), 'inventory', 'Inventory Model', '#4', 'fa-boxes', $status, 'GENUINE GCT RECORDS');
    }

    private function scheduling(array $status): object
    {
        return (new ReflectionMethod(AiModelStatusService::class, 'normalizeScheduling'))
            ->invoke(app(AiModelStatusService::class), $status);
    }

    public function test_sample_inventory_rows_are_excluded_from_verified_production_counts(): void
    {
        $model = $this->standard([
            'success' => true,
            'model_ready' => true,
            'data_source' => 'sample',
            'sample_count' => 47872,
            'model_version' => 'prototype',
        ]);

        $this->assertSame('No Training Data', $model->state);
        $this->assertSame(0, $model->sample_count);
        $this->assertFalse($model->ready);
        $this->assertSame('', $model->model_version);
        $this->assertSame('No verified genuine training dataset', $model->dataset_type);
    }

    public function test_unknown_provenance_is_not_counted_as_genuine(): void
    {
        $model = $this->standard(['model_ready' => true, 'sample_count' => 500]);

        $this->assertSame('Data Not Verified', $model->state);
        $this->assertSame(0, $model->sample_count);
        $this->assertFalse($model->ready);
    }

    public function test_genuine_but_insufficient_training_data_is_reported_honestly(): void
    {
        $model = $this->standard([
            'model_ready' => false,
            'data_source' => 'genuine',
            'sample_count' => 25,
            'reason' => 'Insufficient genuine trip history.',
        ]);

        $this->assertSame('Insufficient Data', $model->state);
        $this->assertSame(25, $model->sample_count);
        $this->assertFalse($model->ready);
    }

    public function test_validated_genuine_trained_model_remains_ready(): void
    {
        $model = $this->standard([
            'model_ready' => true,
            'is_production_model' => true,
            'data_source' => 'genuine',
            'sample_count' => 156,
        ]);

        $this->assertSame('Ready', $model->state);
        $this->assertSame(156, $model->sample_count);
        $this->assertTrue($model->ready);
    }

    public function test_scheduling_does_not_assume_missing_source_is_genuine(): void
    {
        $model = $this->scheduling([
            'bus_model_ready' => true,
            'driver_model_ready' => true,
            'bus_sample_count' => 367,
            'driver_sample_count' => 6,
        ]);

        $this->assertSame('Data Not Verified', $model->state);
        $this->assertSame(0, $model->sample_count);
        $this->assertFalse($model->ready);
    }
}
