<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table) {
            if (! Schema::hasColumn('gps_trip_records', 'trip_type')) {
                $table->string('trip_type')->nullable()->after('grouping');
            }

            if (! Schema::hasColumn('gps_trip_records', 'duration_minutes')) {
                $table->integer('duration_minutes')->nullable()->after('ending_at');
            }

            if (! Schema::hasColumn('gps_trip_records', 'location')) {
                $table->string('location')->nullable()->after('final_location');
            }

            if (! Schema::hasColumn('gps_trip_records', 'coordinates')) {
                $table->string('coordinates')->nullable()->after('location');
            }

            if (! Schema::hasColumn('gps_trip_records', 'description')) {
                $table->text('description')->nullable()->after('coordinates');
            }

            if (! Schema::hasColumn('gps_trip_records', 'source_format')) {
                $table->string('source_format')->nullable()->after('severity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table) {
            $columns = [
                'trip_type',
                'duration_minutes',
                'location',
                'coordinates',
                'description',
                'source_format',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('gps_trip_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};