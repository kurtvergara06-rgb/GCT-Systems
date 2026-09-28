<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CleanupDemoMlArtifacts extends Command
{
    protected $signature = 'ml:cleanup-demo
        {--execute : Remove the listed demo files instead of performing a dry run}
        {--force : Skip the interactive confirmation when executing}
        {--switch-to-genuine : Back up .env and select genuine sources after cleanup}
        {--root= : Override the project root (intended for isolated command tests)}';

    protected $description = 'Safely remove Model #3/#4 demo artifacts and optionally select genuine-data mode';

    public function handle(Filesystem $files): int
    {
        $root = $this->option('root')
            ? rtrim((string) $this->option('root'), '\\/')
            : base_path();
        $execute = (bool) $this->option('execute');
        $targets = $this->targets($root);
        $existing = array_values(array_filter($targets, fn (string $path): bool => $files->isFile($path)));

        $this->info($execute ? 'Demo ML cleanup execution plan:' : 'Demo ML cleanup dry run:');

        if ($existing === []) {
            $this->line('No demo model artifacts or exported demo training files were found.');
        } else {
            foreach ($existing as $path) {
                $this->line(' - '.$this->relativePath($root, $path));
            }
        }

        if ($this->option('switch-to-genuine')) {
            $this->line(' - .env: ALLOW_DEMO_ML=false');
            $this->line(' - .env: DELAY_DATA_SOURCE=genuine');
            $this->line(' - .env: INVENTORY_DATA_SOURCE=genuine');
        }

        if (! $execute) {
            $this->warn('Dry run only. Re-run with --execute after reviewing these exact targets.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Remove these demo artifacts now?', false)) {
            $this->warn('Cleanup cancelled; no files were removed.');

            return self::SUCCESS;
        }

        foreach ($existing as $path) {
            $files->delete($path);
        }

        if ($this->option('switch-to-genuine')) {
            $this->switchEnvironmentToGenuine($files, $root);

            if ($root === base_path()) {
                $this->callSilently('config:clear');
            }
        }

        $this->newLine();
        $this->info(count($existing).' demo file(s) removed. Database records were not changed.');
        $this->line('When genuine history is sufficient, retrain with:');
        $this->line('  cd python_engine');
        $this->line('  .venv/Scripts/python.exe -m delay.prepare_training_data');
        $this->line('  .venv/Scripts/python.exe -m delay.train_model');
        $this->line('  .venv/Scripts/python.exe -m inventory.prepare_training_data');
        $this->line('  .venv/Scripts/python.exe -m inventory.train_model');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function targets(string $root): array
    {
        $relative = [
            'python_engine/delay/models/demo_delay_arrival_rf.pkl',
            'python_engine/delay/models/demo_delay_arrival_features.json',
            'python_engine/delay/models/demo_delay_arrival_report.txt',
            'python_engine/delay/models/demo_delay_arrival_state.json',
            'python_engine/inventory/models/demo_inventory_demand_rf.pkl',
            'python_engine/inventory/models/demo_inventory_demand_features.json',
            'python_engine/inventory/models/demo_inventory_demand_report.txt',
            'python_engine/inventory/models/demo_inventory_demand_state.json',
            'training_data/delay/demo_delay_training.csv',
            'training_data/delay/demo_delay_training.csv.meta.json',
            'training_data/delay/demo_delay_training_features.csv',
            'training_data/inventory/demo_inventory_training.csv',
            'training_data/inventory/demo_inventory_training_features.csv',
        ];

        return array_map(
            fn (string $path): string => $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path),
            $relative
        );
    }

    private function switchEnvironmentToGenuine(Filesystem $files, string $root): void
    {
        $environmentPath = $root.DIRECTORY_SEPARATOR.'.env';

        if (! $files->isFile($environmentPath)) {
            $this->warn('.env was not found; source settings were not changed.');

            return;
        }

        $backupDirectory = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'ml-cleanup-backups';
        $files->ensureDirectoryExists($backupDirectory);
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.'.env.'.now()->format('Ymd_His_u').'.bak';
        $files->copy($environmentPath, $backupPath);

        $contents = $files->get($environmentPath);
        $contents = $this->setEnvironmentValue($contents, 'ALLOW_DEMO_ML', 'false');
        $contents = $this->setEnvironmentValue($contents, 'DELAY_DATA_SOURCE', 'genuine');
        $contents = $this->setEnvironmentValue($contents, 'INVENTORY_DATA_SOURCE', 'genuine');
        $files->put($environmentPath, $contents);

        $this->info('.env updated; backup written to '.$this->relativePath($root, $backupPath));
    }

    private function setEnvironmentValue(string $contents, string $key, string $value): string
    {
        $replacement = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace($pattern, $replacement, $contents);
        }

        return rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
    }

    private function relativePath(string $root, string $path): string
    {
        $prefix = rtrim($root, '\\/').DIRECTORY_SEPARATOR;

        return str_starts_with($path, $prefix)
            ? str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)))
            : $path;
    }
}
