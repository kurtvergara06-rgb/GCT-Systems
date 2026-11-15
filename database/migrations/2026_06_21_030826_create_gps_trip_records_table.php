<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_trip_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('batch_upload_id')
                ->constrained('batch_uploads')
                ->cascadeOnDelete();

            $table->string('bus_no', 50);
            $table->string('grouping')->nullable();

            $table->dateTime('beginning_at')->nullable();
            $table->text('initial_location')->nullable();

            $table->dateTime('ending_at')->nullable();
            $table->text('final_location')->nullable();

            $table->decimal('engine_hours', 10, 2)->nullable();

            $table->unsignedInteger('total_minutes')->nullable();
            $table->unsignedInteger('in_motion_minutes')->nullable();
            $table->unsignedInteger('idling_minutes')->nullable();

            $table->decimal('mileage_km', 10, 2)->nullable();

            $table->string('severity', 30)->default('Normal');

            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->index('bus_no');
            $table->index('grouping');
            $table->index('beginning_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_trip_records');
    }
};