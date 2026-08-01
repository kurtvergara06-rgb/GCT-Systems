<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('trip_code', 30)->unique();
            $table->date('trip_date');
            $table->unsignedBigInteger('shuttle_route_id')->index();
            $table->time('departure_time');
            $table->time('estimated_arrival_time');
            $table->string('shift', 30);
            $table->string('assignment_status', 30)
                ->default('Unassigned');
            $table->string('status', 30)
                ->default('Scheduled');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index([
                'trip_date',
                'departure_time',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_schedules');
    }
};