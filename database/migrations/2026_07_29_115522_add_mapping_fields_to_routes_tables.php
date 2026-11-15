<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shuttle_routes', function (Blueprint $table) {
            $table->decimal(
                'origin_latitude',
                10,
                7
            )->nullable()->after('origin');

            $table->decimal(
                'origin_longitude',
                10,
                7
            )->nullable()->after('origin_latitude');

            $table->string(
                'origin_location_source',
                50
            )->nullable()->after('origin_longitude');

            $table->decimal(
                'destination_latitude',
                10,
                7
            )->nullable()->after('destination');

            $table->decimal(
                'destination_longitude',
                10,
                7
            )->nullable()->after('destination_latitude');

            $table->string(
                'destination_location_source',
                50
            )->nullable()->after('destination_longitude');

            $table->decimal(
                'calculated_distance_km',
                10,
                2
            )->nullable()->after('distance_km');

            $table->unsignedInteger(
                'calculated_time_minutes'
            )->nullable()->after('estimated_time_minutes');

            $table->boolean(
                'distance_manually_adjusted'
            )->default(false);

            $table->boolean(
                'time_manually_adjusted'
            )->default(false);

            $table->longText(
                'route_geometry'
            )->nullable();

            $table->timestamp(
                'route_calculated_at'
            )->nullable();
        });

        Schema::table('route_stops', function (Blueprint $table) {
            $table->decimal(
                'latitude',
                10,
                7
            )->nullable()->after('stop_name');

            $table->decimal(
                'longitude',
                10,
                7
            )->nullable()->after('latitude');

            $table->string(
                'location_source',
                50
            )->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'location_source',
            ]);
        });

        Schema::table('shuttle_routes', function (Blueprint $table) {
            $table->dropColumn([
                'origin_latitude',
                'origin_longitude',
                'origin_location_source',
                'destination_latitude',
                'destination_longitude',
                'destination_location_source',
                'calculated_distance_km',
                'calculated_time_minutes',
                'distance_manually_adjusted',
                'time_manually_adjusted',
                'route_geometry',
                'route_calculated_at',
            ]);
        });
    }
};