<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_driver_report_trip_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_driver_report_id')->constrained('daily_driver_reports')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('trip_ticket', 50);
            $table->string('from_location', 150);
            $table->string('to_location', 150);
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->unsignedInteger('passengers');
            $table->timestamps();
            $table->unique(['daily_driver_report_id', 'sequence'], 'ddr_trip_sequence_unique');
            $table->index('trip_ticket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_driver_report_trip_entries');
    }
};
