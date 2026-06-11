<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mechanic_attendances', function (Blueprint $table) {
            $table->id();

            $table->string('mechanic_id')->unique();
            $table->string('mechanic_name');
            $table->string('shift')->default('Morning');
            $table->string('assigned_job')->nullable();

            $table->date('attendance_date')->nullable();
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();

            $table->enum('status', [
                'Present',
                'Late',
                'Absent',
                'On Leave',
                'On Duty',
            ])->default('Present');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanic_attendances');
    }
    };