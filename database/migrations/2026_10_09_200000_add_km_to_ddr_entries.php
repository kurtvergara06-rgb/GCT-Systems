<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_driver_reports', fn (Blueprint $table) => $table->decimal('km', 10, 2)->nullable());
        Schema::table('daily_driver_report_trip_entries', fn (Blueprint $table) => $table->decimal('km', 10, 2)->nullable());
    }

    public function down(): void
    {
        Schema::table('daily_driver_report_trip_entries', fn (Blueprint $table) => $table->dropColumn('km'));
        Schema::table('daily_driver_reports', fn (Blueprint $table) => $table->dropColumn('km'));
    }
};
