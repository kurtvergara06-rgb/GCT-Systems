<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_assignments', function (Blueprint $table): void {
            $table->dropForeign(['driver_attendance_id']);
        });

        Schema::table('trip_assignments', function (Blueprint $table): void {
            $table->unsignedBigInteger('driver_attendance_id')->nullable()->change();
            $table->foreign('driver_attendance_id')
                ->references('id')
                ->on('driver_attendances')
                ->restrictOnDelete();
            $table->foreignId('driver_master_id')
                ->nullable()
                ->after('driver_attendance_id')
                ->constrained('drivers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_assignments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('driver_master_id');
        });

        // Keep driver_attendance_id nullable on rollback. New planned
        // assignments intentionally have no attendance row, so forcing NOT
        // NULL here could destroy valid operational history.
    }
};
