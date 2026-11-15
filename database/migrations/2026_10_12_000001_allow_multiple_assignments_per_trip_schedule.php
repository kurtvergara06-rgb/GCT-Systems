<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_assignments', function (Blueprint $table): void {
            // Add normal index first to back foreign key before dropping unique index
            $table->index('trip_schedule_id', 'trip_assignments_trip_schedule_id_index');
            $table->dropUnique('trip_assignments_trip_schedule_id_unique');

            // Prevent assigning the same bus or same driver twice to the same trip schedule
            $table->unique(['trip_schedule_id', 'bus_id'], 'trip_assignments_schedule_bus_unique');
            $table->unique(['trip_schedule_id', 'driver_id'], 'trip_assignments_schedule_driver_unique');
        });
    }

    public function down(): void
    {
        Schema::table('trip_assignments', function (Blueprint $table): void {
            $table->dropUnique('trip_assignments_schedule_driver_unique');
            $table->dropUnique('trip_assignments_schedule_bus_unique');
            $table->unique('trip_schedule_id', 'trip_assignments_trip_schedule_id_unique');
            $table->dropIndex('trip_assignments_trip_schedule_id_index');
        });
    }
};
