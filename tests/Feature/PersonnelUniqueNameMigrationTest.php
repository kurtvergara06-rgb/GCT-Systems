<?php

namespace Tests\Feature;

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
        $this->assertTrue($mechanicIndexes->contains(
            fn (array $index): bool => ($index['name'] ?? null) === 'mechanics_mechanic_name_unique'
                && ($index['unique'] ?? false)
        ));
    }
}
