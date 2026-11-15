<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_bus_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_schedule_id')->constrained('trip_schedules')->cascadeOnDelete();
            $table->unsignedSmallInteger('slot_number');
            $table->string('company_name', 160);
            $table->string('plate_number', 40)->nullable();
            $table->string('driver_name', 160)->nullable();
            $table->string('contact_details', 160)->nullable();
            $table->string('status', 24)->default('Pending');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['trip_schedule_id', 'slot_number'], 'external_rental_trip_slot_unique');
            $table->index(['status', 'plate_number'], 'external_rental_vehicle_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_bus_rentals');
    }
};
