<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table) {
            $table->string('trip_type')->nullable()->after('grouping');

            $table->integer('duration_minutes')->nullable()->after('ending_at');

            $table->string('location')->nullable()->after('final_location');
            $table->string('coordinates')->nullable()->after('location');

            $table->text('description')->nullable()->after('coordinates');

            $table->string('source_format')->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table) {
            $table->dropColumn([
                'trip_type',
                'duration_minutes',
                'location',
                'coordinates',
                'description',
                'source_format',
            ]);
        });
    }
};