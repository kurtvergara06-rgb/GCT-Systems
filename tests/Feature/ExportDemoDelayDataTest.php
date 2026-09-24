<?php

namespace Tests\Feature;

use Database\Seeders\ClientDemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportDemoDelayDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_export_uses_frontend_visible_demo_ddr_only(): void
    {
        $this->seed(ClientDemoDataSeeder::class);

        $path = storage_path('framework/testing/demo-delay-training.csv');
        @unlink($path);
        @unlink($path.'.meta.json');

        $this->artisan('delay:export-demo', ['--path' => $path])
            ->assertExitCode(0);

        $this->assertFileExists($path);
        $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));

        // Header + the 460 frontend-visible demo DDR records.
        $this->assertCount(461, $rows);
        $header = array_shift($rows);
        $tripCodeIndex = array_search('trip_code', $header, true);
        $this->assertNotFalse($tripCodeIndex);

        foreach ($rows as $row) {
            $this->assertStringStartsWith('TRIP-DEMO-', $row[$tripCodeIndex]);
        }

        $meta = json_decode(file_get_contents($path.'.meta.json'), true);
        $this->assertSame('demo', $meta['source']);
        $this->assertSame(460, $meta['reports_processed']);
        $this->assertSame(460, $meta['matched_rows']);
    }
}
