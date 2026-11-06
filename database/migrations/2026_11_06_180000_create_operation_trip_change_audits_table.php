<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_trip_change_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_schedule_id')->constrained('trip_schedules')->cascadeOnDelete();
            $table->foreignId('trip_assignment_id')->nullable();
            $table->foreignId('performed_by')->nullable();
            $table->string('action', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->string('previous_driver_id', 100)->nullable();
            $table->string('new_driver_id', 100)->nullable();
            $table->string('previous_driver_name')->nullable();
            $table->string('new_driver_name')->nullable();
            $table->foreignId('previous_bus_id')->nullable();
            $table->foreignId('new_bus_id')->nullable();
            $table->string('previous_bus_label')->nullable();
            $table->string('new_bus_label')->nullable();
            $table->text('reason');
            $table->timestamps();

            $table->index(['trip_schedule_id', 'created_at'], 'trip_change_audit_trip_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_trip_change_audits');
    }
};
