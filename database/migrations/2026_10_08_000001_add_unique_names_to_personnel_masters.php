<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addUniqueIndexIfMissing(
            'drivers',
            'driver_name',
            'drivers_driver_name_unique'
        );

        // mechanic_id is the stable personnel identifier used by attendance
        // history. A mechanic's name is a display value and legacy databases
        // may legitimately contain the same name for different mechanic IDs.
        // Keep those records intact and let the existing application validation
        // prevent new ambiguous names instead of enforcing a destructive or
        // deployment-blocking database constraint here.
    }

    public function down(): void
    {
        $this->dropUniqueIndexIfPresent('drivers', 'drivers_driver_name_unique');

        // Older environments may already have completed the original version
        // of this migration, so retain a guarded cleanup for rollback safety.
        $this->dropUniqueIndexIfPresent('mechanics', 'mechanics_mechanic_name_unique');
    }

    private function addUniqueIndexIfMissing(
        string $table,
        string $column,
        string $indexName
    ): void {
        if (! Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        $duplicateExists = DB::query()
            ->fromSub(
                DB::table($table)
                    ->select($column)
                    ->whereNotNull($column)
                    ->groupBy($column)
                    ->havingRaw('COUNT(*) > 1'),
                'duplicate_names'
            )
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException(
                "Cannot add {$indexName}: duplicate {$column} values must be reconciled first."
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName): void {
            $blueprint->unique($column, $indexName);
        });
    }

    private function dropUniqueIndexIfPresent(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropUnique($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
