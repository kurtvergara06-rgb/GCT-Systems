<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table) {
            $table->time('actual_departure_time')->nullable()->after('estimated_arrival_time');
            $table->time('actual_arrival_time')->nullable()->after('actual_departure_time');
        });

        Schema::table('trip_assignments', function (Blueprint $table) {
            $table->unsignedInteger('actual_duration_minutes')->nullable()->after('assigned_by');
        });

        Schema::table('gps_trip_records', function (Blueprint $table) {
            $table->unsignedBigInteger('trip_assignment_id')->nullable()->after('batch_upload_id');
            $table->foreign('trip_assignment_id')->references('id')->on('trip_assignments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table) {
            $table->dropForeign(['trip_assignment_id']);
            $table->dropColumn('trip_assignment_id');
        });

        Schema::table('trip_assignments', function (Blueprint $table) {
            $table->dropColumn('actual_duration_minutes');
        });

        Schema::table('trip_schedules', function (Blueprint $table) {
            $table->dropColumn(['actual_departure_time', 'actual_arrival_time']);
        });
    }
};
