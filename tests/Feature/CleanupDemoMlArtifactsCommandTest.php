<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupDemoMlArtifactsCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/ml-cleanup-'.Str::uuid());
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->root, storage_path('framework/testing/ml-cleanup-'))) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    public function test_dry_run_preserves_demo_artifacts(): void
    {
        $target = $this->writeDemoArtifact('python_engine/delay/models/demo_delay_arrival_rf.pkl');

        $this->artisan('ml:cleanup-demo', ['--root' => $this->root])
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();

        $this->assertFileExists($target);
    }

    public function test_execute_removes_only_known_demo_files_and_switches_sources_with_backup(): void
    {
        $delayArtifact = $this->writeDemoArtifact('python_engine/delay/models/demo_delay_arrival_rf.pkl');
        $inventoryDataset = $this->writeDemoArtifact('training_data/inventory/demo_inventory_training.csv');
        $unrelated = $this->writeDemoArtifact('training_data/inventory/genuine_inventory_training.csv');
        File::put($this->root.'/.env', implode(PHP_EOL, [
            'APP_ENV=local',
            'ALLOW_DEMO_ML=true',
            'DELAY_DATA_SOURCE=demo',
            'INVENTORY_DATA_SOURCE=demo',
            '',
        ]));

        $this->artisan('ml:cleanup-demo', [
            '--root' => $this->root,
            '--execute' => true,
            '--force' => true,
            '--switch-to-genuine' => true,
        ])->assertSuccessful();

        $this->assertFileDoesNotExist($delayArtifact);
        $this->assertFileDoesNotExist($inventoryDataset);
        $this->assertFileExists($unrelated);

        $environment = File::get($this->root.'/.env');
        $this->assertStringContainsString('ALLOW_DEMO_ML=false', $environment);
        $this->assertStringContainsString('DELAY_DATA_SOURCE=genuine', $environment);
        $this->assertStringContainsString('INVENTORY_DATA_SOURCE=genuine', $environment);

        $backups = File::glob($this->root.'/storage/app/ml-cleanup-backups/.env.*.bak');
        $this->assertCount(1, $backups);
        $this->assertStringContainsString('ALLOW_DEMO_ML=true', File::get($backups[0]));
    }

    private function writeDemoArtifact(string $relativePath): string
    {
        $path = $this->root.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'fixture');

        return $path;
    }
}
