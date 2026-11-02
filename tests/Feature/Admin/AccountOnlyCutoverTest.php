<?php

namespace Tests\Feature\Admin;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RealisticSampleDataSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountOnlyCutoverTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['gct_account_source', 'gct_account_target', 'gct_account_restore'] as $connection) {
            config()->set('database.connections.'.$connection, [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'host' => $connection,
                'username' => 'isolated-test',
                'password' => 'isolated-test',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
            DB::purge($connection);
            Schema::connection($connection)->create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('password');
                $table->string('department');
                $table->string('role');
                $table->string('status');
                $table->timestamp('email_verified_at')->nullable();
                $table->boolean('must_change_password')->default(false);
            });
            Schema::connection($connection)->create('role_permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('role_key');
                $table->json('permissions');
                $table->unsignedBigInteger('updated_by')->nullable();
            });
            Schema::connection($connection)->create('job_orders', function (Blueprint $table): void {
                $table->id();
            });
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        foreach (['gct_account_source', 'gct_account_target', 'gct_account_restore'] as $connection) {
            DB::purge($connection);
        }
        parent::tearDown();
    }

    public function test_dry_run_backup_gate_transfer_and_verification_preserve_account_state(): void
    {
        $hash = Hash::make('local-test-password');
        DB::connection('gct_account_source')->table('users')->insert([
            [
                'id' => 7, 'name' => 'Existing Admin', 'email' => 'admin@example.test',
                'password' => $hash, 'department' => 'Admin', 'role' => 'head',
                'status' => 'Active', 'email_verified_at' => '2026-10-01 10:20:30',
                'must_change_password' => false,
            ],
            [
                'id' => 9, 'name' => 'Pending Staff', 'email' => 'pending@example.test',
                'password' => $hash, 'department' => 'Operation', 'role' => 'staff',
                'status' => 'Pending', 'email_verified_at' => null,
                'must_change_password' => true,
            ],
        ]);
        DB::connection('gct_account_source')->table('role_permissions')->insert([
            'id' => 3, 'role_key' => 'operation_staff',
            'permissions' => '{"operation":{"view":true,"edit":true}}',
            'updated_by' => 7,
        ]);

        $report = $this->path('dry-run.json');
        $this->artisan('gct:account-cutover', ['--report' => $report])->assertExitCode(0);
        $this->assertSame(0, DB::connection('gct_account_target')->table('users')->count());

        $this->artisan('gct:account-cutover', ['--apply' => true, '--report' => $report])
            ->assertExitCode(1);
        $this->assertSame(0, DB::connection('gct_account_target')->table('users')->count());

        $backup = $this->path('backup.sql');
        file_put_contents($backup, 'isolated test backup marker');
        foreach (['users', 'role_permissions'] as $table) {
            DB::connection('gct_account_restore')->table($table)->insert(
                DB::connection('gct_account_source')->table($table)->get()->map(fn ($row) => (array) $row)->all()
            );
        }
        $manifest = $this->path('backup-manifest.json');
        $this->artisan('gct:account-cutover', [
            '--check-backup' => true,
            '--backup-file' => $backup,
            '--backup-manifest' => $manifest,
        ])->assertExitCode(0);

        $this->artisan('gct:account-cutover', [
            '--apply' => true,
            '--report' => $report,
            '--backup-manifest' => $manifest,
        ])->assertExitCode(0);
        $verifyExit = Artisan::call('gct:account-cutover', ['--verify' => true]);
        $this->assertSame(0, $verifyExit, Artisan::output());

        $source = DB::connection('gct_account_source')->table('users')->orderBy('id')->get()->toArray();
        $target = DB::connection('gct_account_target')->table('users')->orderBy('id')->get()->toArray();
        $this->assertEquals($source, $target);
        $this->assertSame($hash, $target[0]->password);
        $this->assertSame('Pending', $target[1]->status);
        $this->assertSame(0, DB::connection('gct_account_target')->table('job_orders')->count());
        $this->assertSame(1, DB::connection('gct_account_target')->table('role_permissions')->count());

        $this->artisan('gct:account-cutover', [
            '--apply' => true,
            '--report' => $report,
            '--backup-manifest' => $manifest,
        ])->assertExitCode(1);
    }

    public function test_dry_run_refuses_populated_operational_target(): void
    {
        DB::connection('gct_account_source')->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'unchanged-hash', 'department' => 'Admin', 'role' => 'head',
            'status' => 'Active',
        ]);
        DB::connection('gct_account_source')->table('role_permissions')->insert([
            'id' => 1, 'role_key' => 'admin_head', 'permissions' => '{}',
        ]);
        DB::connection('gct_account_target')->table('job_orders')->insert(['id' => 1]);

        $this->artisan('gct:account-cutover')->assertExitCode(1);
        $this->assertSame(0, DB::connection('gct_account_target')->table('users')->count());
    }

    public function test_target_connection_skips_migration_rows_and_seeders_refuse_target(): void
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection('gct_account_target');

        try {
            Schema::dropIfExists('role_permissions');
            $rolesMigration = require database_path('migrations/2026_08_09_060500_create_role_permissions_table.php');
            $rolesMigration->up();
            $this->assertSame(0, DB::table('role_permissions')->count());

            $catalogMigration = require database_path('migrations/2026_11_01_000003_create_maintenance_job_catalog.php');
            $catalogMigration->up();
            $this->assertSame(0, DB::table('maintenance_job_catalog')->count());

            Schema::create('mechanic_specialization_options', function (Blueprint $table): void {
                $table->id();
                $table->string('normalized_name');
                $table->string('name');
            });
            $specializationMigration = require database_path('migrations/2026_10_22_133000_populate_mechanic_specialization_options.php');
            $specializationMigration->up();
            $this->assertSame(0, DB::table('mechanic_specialization_options')->count());

            foreach ([DatabaseSeeder::class, DemoDataSeeder::class, RealisticSampleDataSeeder::class] as $seeder) {
                try {
                    (new $seeder)->run();
                    $this->fail("{$seeder} unexpectedly seeded the account-only target.");
                } catch (\RuntimeException $e) {
                    $this->assertStringContainsString('disabled', $e->getMessage());
                }
            }
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    public function test_source_change_invalidates_dry_run_without_writing_target(): void
    {
        DB::connection('gct_account_source')->table('users')->insert([
            'id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'unchanged-hash', 'department' => 'Admin', 'role' => 'head',
            'status' => 'Active',
        ]);
        DB::connection('gct_account_source')->table('role_permissions')->insert([
            'id' => 1, 'role_key' => 'admin_head', 'permissions' => '{}',
        ]);
        $report = $this->path('stale-report.json');
        $this->artisan('gct:account-cutover', ['--report' => $report])->assertExitCode(0);

        DB::connection('gct_account_source')->table('users')->insert([
            'id' => 2, 'name' => 'Pending User', 'email' => 'pending@example.test',
            'password' => 'second-hash', 'department' => 'Operation', 'role' => 'staff',
            'status' => 'Pending',
        ]);

        $this->artisan('gct:account-cutover', [
            '--apply' => true, '--report' => $report,
        ])->assertExitCode(1);
        $this->assertSame(0, DB::connection('gct_account_target')->table('users')->count());
    }

    private function path(string $name): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gct-account-'.bin2hex(random_bytes(8)).'-'.$name;
        $this->temporaryFiles[] = $path;
        return $path;
    }
}
