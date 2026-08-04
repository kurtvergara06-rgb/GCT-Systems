<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $table): void {
                $table->id();
                $table->string('driver_id')->unique();
                $table->string('driver_name');
                $table->string('shift')->default('Morning');
                $table->string('contact_number')->nullable();
                $table->string('license_number')->nullable();
                $table->date('license_expiration')->nullable();
                $table->enum('employment_status', ['Active', 'Inactive'])->default('Active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mechanics')) {
            Schema::create('mechanics', function (Blueprint $table): void {
                $table->id();
                $table->string('mechanic_id')->unique();
                $table->string('mechanic_name');
                $table->string('shift')->default('Morning');
                $table->string('specialization')->nullable();
                $table->string('contact_number')->nullable();
                $table->enum('employment_status', ['Active', 'Inactive'])->default('Active');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('driver_attendances')) {
            DB::table('driver_attendances')
                ->select('driver_id', 'driver_name', 'shift')
                ->whereNotNull('driver_id')
                ->orderBy('id')
                ->get()
                ->unique('driver_id')
                ->each(function (object $row): void {
                    DB::table('drivers')->updateOrInsert(
                        ['driver_id' => $row->driver_id],
                        [
                            'driver_name' => $row->driver_name,
                            'shift' => $row->shift ?: 'Morning',
                            'employment_status' => 'Active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                });
        }

        if (Schema::hasTable('mechanic_attendances')) {
            DB::table('mechanic_attendances')
                ->select('mechanic_id', 'mechanic_name', 'shift')
                ->whereNotNull('mechanic_id')
                ->orderBy('id')
                ->get()
                ->unique('mechanic_id')
                ->each(function (object $row): void {
                    DB::table('mechanics')->updateOrInsert(
                        ['mechanic_id' => $row->mechanic_id],
                        [
                            'mechanic_name' => $row->mechanic_name,
                            'shift' => $row->shift ?: 'Morning',
                            'employment_status' => 'Active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                });
        }

        $this->replaceUniqueIndex(
            'driver_attendances',
            'driver_attendances_driver_id_unique',
            'driver_attendances_driver_date_unique',
            ['driver_id', 'attendance_date']
        );

        $this->replaceUniqueIndex(
            'mechanic_attendances',
            'mechanic_attendances_mechanic_id_unique',
            'mechanic_attendances_mechanic_date_unique',
            ['mechanic_id', 'attendance_date']
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanics');
        Schema::dropIfExists('drivers');
    }

    private function replaceUniqueIndex(
        string $table,
        string $oldIndex,
        string $newIndex,
        array $columns
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        $indexes = collect(Schema::getIndexes($table));

        if ($indexes->contains(fn (array $index): bool => ($index['name'] ?? '') === $oldIndex)) {
            Schema::table($table, function (Blueprint $blueprint) use ($oldIndex): void {
                $blueprint->dropUnique($oldIndex);
            });
        }

        $indexes = collect(Schema::getIndexes($table));

        if (! $indexes->contains(fn (array $index): bool => ($index['name'] ?? '') === $newIndex)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $newIndex): void {
                $blueprint->unique($columns, $newIndex);
            });
        }
    }
};
