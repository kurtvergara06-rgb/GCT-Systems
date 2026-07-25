<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(
            'driver_attendances',
            function (Blueprint $table) {

                $table->id();

                $table
                    ->string('driver_id')
                    ->unique();

                $table
                    ->string('driver_name');

                $table
                    ->string('shift');

                $table
                    ->string('bus_assignment')
                    ->nullable();

                $table
                    ->date('attendance_date');

                $table
                    ->time('time_in')
                    ->nullable();

                $table
                    ->time('time_out')
                    ->nullable();

                $table
                    ->enum(
                        'status',
                        [
                            'Present',
                            'Late',
                            'Absent',
                            'On Leave',
                            'On Duty',
                        ]
                    )
                    ->default('Present');

                $table->timestamps();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'driver_attendances'
        );
    }
};