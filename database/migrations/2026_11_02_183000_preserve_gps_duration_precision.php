<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table): void {
            $table->decimal('duration_minutes', 12, 4)->nullable()->change();
            $table->decimal('total_minutes', 12, 4)->nullable()->change();
            $table->decimal('in_motion_minutes', 12, 4)->nullable()->change();
            $table->decimal('idling_minutes', 12, 4)->nullable()->change();
            $table->decimal('engine_hours', 12, 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table): void {
            $table->integer('duration_minutes')->nullable()->change();
            $table->unsignedInteger('total_minutes')->nullable()->change();
            $table->unsignedInteger('in_motion_minutes')->nullable()->change();
            $table->unsignedInteger('idling_minutes')->nullable()->change();
            $table->decimal('engine_hours', 10, 2)->nullable()->change();
        });
    }
};
