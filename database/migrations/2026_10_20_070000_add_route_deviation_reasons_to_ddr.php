<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_driver_reports', fn (Blueprint $table) => $table->string('route_deviation_reason', 500)->nullable());
        Schema::table('daily_driver_report_trip_entries', fn (Blueprint $table) => $table->string('route_deviation_reason', 500)->nullable());
    }

    public function down(): void
    {
        Schema::table('daily_driver_report_trip_entries', fn (Blueprint $table) => $table->dropColumn('route_deviation_reason'));
        Schema::table('daily_driver_reports', fn (Blueprint $table) => $table->dropColumn('route_deviation_reason'));
    }
};
