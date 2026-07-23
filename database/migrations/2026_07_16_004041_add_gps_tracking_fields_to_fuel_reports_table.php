<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_reports', function (Blueprint $table) {
            $table->foreignId('gps_trip_record_id')
                ->nullable()
                ->after('driver_name')
                ->constrained('gps_trip_records')
                ->nullOnDelete();

            $table->string('distance_source', 20)
                ->default('GPS')
                ->after('distance_km');

            $table->string('status', 30)
                ->default('No Data')
                ->after('km_per_liter');

            $table->text('manual_distance_reason')
                ->nullable()
                ->after('remarks');

            $table->index([
                'report_date',
                'bus_no',
                'distance_source',
            ], 'fuel_reports_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_reports', function (Blueprint $table) {
            $table->dropIndex('fuel_reports_lookup_index');

            $table->dropConstrainedForeignId('gps_trip_record_id');

            $table->dropColumn([
                'distance_source',
                'status',
                'manual_distance_reason',
            ]);
        });
    }
};