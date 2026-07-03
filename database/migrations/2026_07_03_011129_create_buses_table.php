<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();

            $table->string('bus_no')->unique();
            $table->string('plate_no')->nullable();
            $table->string('bus_model')->nullable();
            $table->string('year_model')->nullable();
            $table->unsignedInteger('capacity')->nullable();

            $table->string('route_grouping')->nullable();

            $table->enum('status', [
                'Active',
                'Inactive',
                'Under Maintenance',
            ])->default('Active');

            /*
             | Latest processed GPS mileage.
             | This will later update automatically from GPS Batch Processing.
             */
            $table->decimal('latest_gps_km', 12, 2)->nullable();

            $table->timestamp('latest_gps_at')->nullable();

            /*
             | PMS values.
             | These will later connect to PMS Scheduling.
             */
            $table->decimal('last_pms_km', 12, 2)->default(0);
            $table->decimal('pms_interval_km', 12, 2)->default(5000);
            $table->decimal('next_pms_km', 12, 2)->default(5000);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};