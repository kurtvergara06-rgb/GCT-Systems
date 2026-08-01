<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_assignments', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('trip_schedule_id')
                ->unique()
                ->constrained('trip_schedules')
                ->cascadeOnDelete();

            $table
                ->foreignId('driver_attendance_id')
                ->constrained('driver_attendances')
                ->restrictOnDelete();

            $table->string('driver_id');
            $table->string('driver_name');

            $table
                ->foreignId('bus_id')
                ->constrained('buses')
                ->restrictOnDelete();

            $table
                ->unsignedBigInteger('assigned_by')
                ->nullable()
                ->index();

            $table->timestamps();

            $table->index([
                'driver_id',
                'bus_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_assignments');
    }
};