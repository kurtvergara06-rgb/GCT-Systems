<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PrepareAccountOnlyCutover extends Command
{
    private const SOURCE = 'gct_account_source';
    private const TARGET = 'gct_account_target';
    private const RESTORE = 'gct_account_restore';
    private const TABLES = ['users', 'role_permissions'];

    protected $signature = 'gct:account-cutover
        {--report= : Path for a dry-run report, or the report to verify for apply}
        {--backup-manifest= : JSON manifest of a verified and restore-tested source backup}
        {--check-backup : Compare a restored scratch database and write a backup manifest}
        {--backup-file= : Backup file used with --check-backup}
        {--apply : Insert account-only rows after a matching dry run and verified backup}
        {--verify : Compare an already populated target with the source without writing}';

    protected $description = 'Dry-run, transfer, or verify account-only data between dedicated database connections';

    public function handle(): int
    {
        try {
            $this->assertConnections();
            $source = DB::connection(self::SOURCE);
            $target = DB::connection(self::TARGET);
            foreach (self::TABLES as $table) {
                if (! Schema::connection(self::SOURCE)->hasTable($table)) {
                    throw new RuntimeException("Source is missing {$table}.");
                }
            }
            $sourceSnapshot = $this->snapshot(self::SOURCE);
            $this->assertSourceAccounts($sourceSnapshot);
            $this->assertOperationalTablesEmpty();

            if ($this->option('check-backup')) {
                if ($this->option('apply') || $this->option('verify') || $this->option('report')) {
                    throw new RuntimeException('Backup check cannot be combined with transfer, verify, or report options.');
                }
                foreach (self::TABLES as $table) {
                    if (Schema::connection(self::TARGET)->hasTable($table)
                        && $target->table($table)->exists()) {
                        throw new RuntimeException("Target {$table} is not empty.");
                    }
                }
                $this->assertRestoreConnection();
                foreach (self::TABLES as $table) {
                    if (! Schema::connection(self::RESTORE)->hasTable($table)) {
                        throw new RuntimeException("Restored scratch database is missing {$table}.");
                    }
                }
                if ($this->snapshot(self::RESTORE) !== $sourceSnapshot
                    || $this->tableCounts(self::RESTORE) !== $this->tableCounts(self::SOURCE)) {
                    throw new RuntimeException('Restored scratch database does not match source account rows and table counts.');
                }
                $backup = $this->option('backup-file');
                if (! $backup || ! is_file($backup) || ! is_readable($backup)) {
                    throw new RuntimeException('A readable source backup file is required.');
                }
                $manifestPath = $this->option('backup-manifest');
                if (! $manifestPath || file_exists($manifestPath)) {
                    throw new RuntimeException('A new backup manifest path is required.');
                }
                $manifest = [
                    'source_database' => config('database.connections.'.self::SOURCE.'.database'),
                    'backup_file' => realpath($backup),
                    'sha256' => hash_file('sha256', $backup),
                    'verified_at' => now()->toIso8601String(),
                    'restore_tested_at' => now()->toIso8601String(),
                    'restored_account_snapshot' => $sourceSnapshot,
                ];
                if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                    throw new RuntimeException('Could not write backup manifest.');
                }
                $this->info('Backup restore matched source accounts and all table counts; manifest written.');
                return self::SUCCESS;
            }

            $this->assertSchema();
            $targetSnapshot = $this->snapshot(self::TARGET);

            if ($this->option('verify')) {
                if ($this->option('apply') || $this->option('report')
                    || $this->option('backup-file') || $this->option('backup-manifest')
                    || $sourceSnapshot !== $targetSnapshot) {
                    throw new RuntimeException('Target account rows do not exactly match the source, or options conflict.');
                }
                $this->info('Verified: account tables match; all other target tables are empty.');
                return self::SUCCESS;
            }

            if (! $this->option('apply')) {
                if ($this->option('backup-file') || $this->option('backup-manifest')) {
                    throw new RuntimeException('Backup options require --check-backup or --apply.');
                }
                $this->assertAccountTablesEmpty($targetSnapshot);
                $report = [
                    'version' => 1,
                    'source_database' => config('database.connections.'.self::SOURCE.'.database'),
                    'target_database' => config('database.connections.'.self::TARGET.'.database'),
                    'source' => $sourceSnapshot,
                    'target' => $targetSnapshot,
                    'checked_at' => now()->toIso8601String(),
                ];
                if ($path = $this->option('report')) {
                    if (file_exists($path)) {
                        throw new RuntimeException('Dry-run report path already exists; choose a new path.');
                    }
                    if (file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                        throw new RuntimeException('Could not write the dry-run report.');
                    }
                }
                $this->info(sprintf(
                    'Dry run only: %d users and %d role-permission rows; target account and operational tables empty.',
                    $sourceSnapshot['users']['count'],
                    $sourceSnapshot['role_permissions']['count'],
                ));
                return self::SUCCESS;
            }

            if ($this->option('backup-file')) {
                throw new RuntimeException('--backup-file is only valid with --check-backup.');
            }
            $report = $this->readJsonFile($this->option('report'), 'dry-run report');
            $this->assertRecent($report['checked_at'] ?? null, 'dry run');
            if (($report['version'] ?? null) !== 1
                || ($report['source_database'] ?? null) !== config('database.connections.'.self::SOURCE.'.database')
                || ($report['target_database'] ?? null) !== config('database.connections.'.self::TARGET.'.database')
                || ($report['source'] ?? null) !== $sourceSnapshot
                || ($report['target'] ?? null) !== $targetSnapshot) {
                throw new RuntimeException('Source or target changed since dry run; generate a fresh report.');
            }
            $this->assertAccountTablesEmpty($targetSnapshot);
            $this->assertVerifiedBackup($this->option('backup-manifest'), $sourceSnapshot);

            $target->transaction(function () use ($source, $target, $sourceSnapshot): void {
                $this->assertAccountTablesEmpty($this->snapshot(self::TARGET));
                $this->assertOperationalTablesEmpty();
                foreach (self::TABLES as $table) {
                    $source->table($table)->orderBy('id')->chunk(200, function ($rows) use ($target, $table): void {
                        $target->table($table)->insert($rows->map(fn ($row) => (array) $row)->all());
                    });
                }
                if ($this->snapshot(self::TARGET) !== $sourceSnapshot) {
                    throw new RuntimeException('Post-copy account verification failed; target transaction rolled back.');
                }
            });

            $this->info('Account-only transfer committed to the target. Run --verify before cutover.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            // Do not print connection exceptions: they can include hosts or credentials.
            $this->error($e instanceof RuntimeException && $e->getFile() === __FILE__
                ? $e->getMessage()
                : 'Cutover check failed; inspect connection, schema, and file access in the restricted environment.');
            return self::FAILURE;
        }
    }

    private function assertConnections(): void
    {
        if (DB::getDefaultConnection() === self::SOURCE || DB::getDefaultConnection() === self::TARGET) {
            throw new RuntimeException('Use a separate default app connection; cutover connections are command-only.');
        }
        foreach ([self::SOURCE => 'defaultdb', self::TARGET => 'gct_production_clean'] as $name => $database) {
            $config = config('database.connections.'.$name);
            foreach (['host', 'database', 'username', 'password'] as $field) {
                if (! is_string($config[$field] ?? null) || trim($config[$field]) === '') {
                    throw new RuntimeException("Dedicated {$name} connection is incomplete.");
                }
            }
            if (! app()->environment('testing') && ($config['driver'] !== 'mysql' || $config['database'] !== $database)) {
                throw new RuntimeException("Dedicated {$name} connection has an unexpected driver or database name.");
            }
        }
        if (config('database.connections.'.self::SOURCE.'.host') === config('database.connections.'.self::TARGET.'.host')
            && config('database.connections.'.self::SOURCE.'.database') === config('database.connections.'.self::TARGET.'.database')) {
            throw new RuntimeException('Source and target resolve to the same database.');
        }
    }

    private function assertRestoreConnection(): void
    {
        $config = config('database.connections.'.self::RESTORE);
        foreach (['host', 'database', 'username', 'password'] as $field) {
            if (! is_string($config[$field] ?? null) || trim($config[$field]) === '') {
                throw new RuntimeException('Dedicated restore connection is incomplete.');
            }
        }
        if (! app()->environment('testing') && $config['driver'] !== 'mysql') {
            throw new RuntimeException('Restore connection must use MySQL.');
        }
        foreach ([self::SOURCE, self::TARGET] as $name) {
            if ($config['host'] === config('database.connections.'.$name.'.host')
                && $config['database'] === config('database.connections.'.$name.'.database')) {
                throw new RuntimeException('Restore database must be separate from source and target.');
            }
        }
    }

    private function tableCounts(string $connection): array
    {
        $counts = [];
        foreach (Schema::connection($connection)->getTableListing() as $table) {
            $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if (str_starts_with($name, 'sqlite_')) {
                continue;
            }
            $counts[$name] = DB::connection($connection)->table($table)->count();
        }
        ksort($counts);
        return $counts;
    }

    private function assertSchema(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::connection(self::SOURCE)->hasTable($table)
                || ! Schema::connection(self::TARGET)->hasTable($table)) {
                throw new RuntimeException("Missing {$table} table; migrate the empty target schema first.");
            }
            $shape = static function (string $connection) use ($table): array {
                $columns = array_map(static fn (array $column): array => [
                    'name' => $column['name'],
                    'type' => strtolower((string) $column['type']),
                    'nullable' => (bool) $column['nullable'],
                    'default' => $column['default'] ?? null,
                    'auto_increment' => (bool) ($column['auto_increment'] ?? false),
                ], Schema::connection($connection)->getColumns($table));
                usort($columns, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
                return $columns;
            };
            $sourceColumns = $shape(self::SOURCE);
            $targetColumns = $shape(self::TARGET);
            if ($sourceColumns !== $targetColumns) {
                throw new RuntimeException("{$table} schema differs between source and target.");
            }
        }
    }

    private function snapshot(string $connection): array
    {
        $snapshot = [];
        foreach (self::TABLES as $table) {
            $hash = hash_init('sha256');
            $count = 0;
            DB::connection($connection)->table($table)->orderBy('id')->chunk(200, function ($rows) use (&$count, $hash): void {
                foreach ($rows as $row) {
                    $values = (array) $row;
                    ksort($values);
                    hash_update($hash, json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
                    $count++;
                }
            });
            $snapshot[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
        }
        return $snapshot;
    }

    private function assertSourceAccounts(array $snapshot): void
    {
        if ($snapshot['users']['count'] === 0 || $snapshot['role_permissions']['count'] === 0) {
            throw new RuntimeException('Source has no users or role permissions.');
        }
        $ids = DB::connection(self::SOURCE)->table('users')->pluck('id')->mapWithKeys(fn ($id) => [$id => true]);
        $keys = DB::connection(self::SOURCE)->table('role_permissions')->pluck('role_key')->flip();
        foreach (DB::connection(self::SOURCE)->table('role_permissions')->whereNotNull('updated_by')->pluck('updated_by') as $id) {
            if (! isset($ids[$id])) {
                throw new RuntimeException('A role permission has an invalid updated_by account reference.');
            }
        }
        foreach (DB::connection(self::SOURCE)->table('users')->get(['department', 'role', 'status']) as $user) {
            if (strcasecmp((string) $user->status, 'Active') !== 0) {
                continue;
            }
            $department = strtolower(trim((string) $user->department));
            $role = strtolower(trim((string) $user->role));
            if (($department === 'admin' && $role === 'head') || $role === 'system admin') {
                continue;
            }
            $key = str_replace(' ', '_', $department).'_'.$role;
            if (! isset($keys[$key])) {
                throw new RuntimeException('An active account lacks a matching role_permissions row.');
            }
        }
    }

    private function assertOperationalTablesEmpty(): void
    {
        foreach (Schema::connection(self::TARGET)->getTableListing() as $table) {
            $name = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if (in_array($name, [...self::TABLES, 'migrations'], true) || str_starts_with($name, 'sqlite_')) {
                continue;
            }
            if (DB::connection(self::TARGET)->table($table)->exists()) {
                throw new RuntimeException("Target table {$table} is populated; account-only cutover refuses to proceed.");
            }
        }
    }

    private function assertAccountTablesEmpty(array $snapshot): void
    {
        foreach (self::TABLES as $table) {
            if ($snapshot[$table]['count'] !== 0) {
                throw new RuntimeException("Target {$table} is not empty; existing data will never be overwritten.");
            }
        }
    }

    private function readJsonFile(?string $path, string $label): array
    {
        if (! $path || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("A readable {$label} file is required.");
        }
        $value = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new RuntimeException("Invalid {$label}.");
        }
        return $value;
    }

    private function assertRecent(?string $value, string $label): void
    {
        if (! $value || ! strtotime($value) || strtotime($value) > time()
            || strtotime($value) < time() - 86400) {
            throw new RuntimeException("A {$label} completed within the last 24 hours is required.");
        }
    }

    private function assertVerifiedBackup(?string $path, array $sourceSnapshot): void
    {
        $manifest = $this->readJsonFile($path, 'backup manifest');
        $this->assertRecent($manifest['verified_at'] ?? null, 'backup verification');
        $this->assertRecent($manifest['restore_tested_at'] ?? null, 'backup restore test');
        if (($manifest['source_database'] ?? null) !== config('database.connections.'.self::SOURCE.'.database')
            || ($manifest['restored_account_snapshot'] ?? null) !== $sourceSnapshot
            || ! is_string($manifest['backup_file'] ?? null)
            || ! is_file($manifest['backup_file'])
            || ! is_readable($manifest['backup_file'])
            || ! hash_equals((string) ($manifest['sha256'] ?? ''), hash_file('sha256', $manifest['backup_file']))) {
            throw new RuntimeException('Backup manifest or backup file failed verification.');
        }
    }
}
