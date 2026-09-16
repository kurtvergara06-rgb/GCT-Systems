<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_driver_reports', function (Blueprint $table) {
            $table->id();

            $table->string('ddr_no', 30)->unique();
            $table->date('report_date')->index();
            $table->string('driver_id')->nullable()->index();
            $table->string('driver_name', 100);
            $table->unsignedBigInteger('bus_id')->nullable()->index();
            $table->string('trip_ticket', 50)->index();
            $table->string('from_location', 150);
            $table->string('to_location', 150);
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->unsignedInteger('passengers')->default(0);
            $table->unsignedBigInteger('encoded_by')->nullable()->index();
            $table->timestamps();

            $table->index([
                'report_date',
                'trip_ticket',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_driver_reports');
    }
};