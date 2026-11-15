<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shuttle_routes', function (Blueprint $table) {
            if (!Schema::hasColumn('shuttle_routes', 'origin_address')) {
                $table->text('origin_address')
                    ->nullable()
                    ->after('origin');
            }

            if (!Schema::hasColumn('shuttle_routes', 'origin_source')) {
                $table->string('origin_source', 50)
                    ->nullable()
                    ->after('origin_longitude');
            }

            if (!Schema::hasColumn('shuttle_routes', 'destination_address')) {
                $table->text('destination_address')
                    ->nullable()
                    ->after('destination');
            }

            if (!Schema::hasColumn('shuttle_routes', 'destination_source')) {
                $table->string('destination_source', 50)
                    ->nullable()
                    ->after('destination_longitude');
            }

            if (!Schema::hasColumn('shuttle_routes', 'distance_source')) {
                $table->string('distance_source', 50)
                    ->nullable()
                    ->after('calculated_distance_km');
            }

            if (!Schema::hasColumn('shuttle_routes', 'distance_is_manual')) {
                $table->boolean('distance_is_manual')
                    ->default(false)
                    ->after('distance_source');
            }

            if (!Schema::hasColumn('shuttle_routes', 'time_is_manual')) {
                $table->boolean('time_is_manual')
                    ->default(false)
                    ->after('calculated_time_minutes');
            }
        });

        Schema::table('route_stops', function (Blueprint $table) {
            if (!Schema::hasColumn('route_stops', 'address')) {
                $table->text('address')
                    ->nullable()
                    ->after('stop_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('route_stops', 'address')) {
                $columns[] = 'address';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('shuttle_routes', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'origin_address',
                'origin_source',
                'destination_address',
                'destination_source',
                'distance_source',
                'distance_is_manual',
                'time_is_manual',
            ] as $column) {
                if (Schema::hasColumn('shuttle_routes', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};