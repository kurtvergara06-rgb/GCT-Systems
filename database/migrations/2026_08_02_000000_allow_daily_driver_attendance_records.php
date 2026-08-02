<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_attendances', function (Blueprint $table) {
            $table->dropUnique(
                'driver_attendances_driver_id_unique'
            );

            $table->unique(
                ['driver_id', 'attendance_date'],
                'driver_attendances_driver_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('driver_attendances', function (Blueprint $table) {
            $table->dropUnique(
                'driver_attendances_driver_date_unique'
            );

            $table->unique(
                'driver_id',
                'driver_attendances_driver_id_unique'
            );
        });
    }
};
