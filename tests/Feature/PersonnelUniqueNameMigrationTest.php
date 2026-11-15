<?php

namespace Tests\Feature;

use App\Models\Operation\Mechanic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonnelUniqueNameMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_name_migration_is_safe_to_resume_after_partial_ddl_application(): void
    {
        $migration = require database_path(
            'migrations/2026_10_08_000001_add_unique_names_to_personnel_masters.php'
        );

        $migration->up();

        $driverIndexes = collect(Schema::getIndexes('drivers'));
        $mechanicIndexes = collect(Schema::getIndexes('mechanics'));

        $this->assertTrue($driverIndexes->contains(
            fn (array $index): bool => ($index['name'] ?? null) === 'drivers_driver_name_unique'
                && ($index['unique'] ?? false)
        ));
        $this->assertFalse($mechanicIndexes->contains(
            fn (array $index): bool => ($index['name'] ?? null) === 'mechanics_mechanic_name_unique'
        ));
    }

    public function test_unique_name_migration_preserves_legacy_mechanics_with_the_same_name(): void
    {
        Mechanic::query()->insert([
            [
                'mechanic_id' => 'MECH-LEGACY-001',
                'mechanic_name' => 'Legacy Shared Name',
                'shift' => 'Morning',
                'employment_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mechanic_id' => 'MECH-LEGACY-002',
                'mechanic_name' => 'Legacy Shared Name',
                'shift' => 'Afternoon',
                'employment_status' => 'Inactive',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path(
            'migrations/2026_10_08_000001_add_unique_names_to_personnel_masters.php'
        );

        $migration->up();

        $this->assertSame(2, Mechanic::query()
            ->where('mechanic_name', 'Legacy Shared Name')
            ->count());
        $this->assertEqualsCanonicalizing(
            ['MECH-LEGACY-001', 'MECH-LEGACY-002'],
            Mechanic::query()
                ->where('mechanic_name', 'Legacy Shared Name')
                ->pluck('mechanic_id')
                ->all()
        );
        $this->assertFalse(collect(Schema::getIndexes('mechanics'))->contains(
            fn (array $index): bool => ($index['name'] ?? null) === 'mechanics_mechanic_name_unique'
        ));
    }
}
