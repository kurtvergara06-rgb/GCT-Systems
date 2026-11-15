<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_no', 30)->unique();

            $table
                ->foreignId('trip_schedule_id')
                ->nullable()
                ->constrained('trip_schedules')
                ->nullOnDelete();

            $table
                ->foreignId('bus_id')
                ->nullable()
                ->constrained('buses')
                ->nullOnDelete();

            $table->string('driver_id')->nullable();
            $table->string('driver_name')->nullable();

            $table->string('incident_type', 50);
            $table->string('location')->nullable();
            $table->text('description')->nullable();

            $table->timestamp('incident_reported_at');
            $table->string('status', 30)->default('Reported');

            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->unsignedBigInteger('reported_by')->nullable()->index();
            $table->unsignedBigInteger('resolved_by')->nullable();

            $table->timestamps();

            $table->index(['status', 'incident_type']);
            $table->index('incident_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
