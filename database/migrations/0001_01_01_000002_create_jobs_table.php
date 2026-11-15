<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();

            $table->string('job_order_no')->unique();
            $table->string('bus_no');
            $table->text('problem_issue');
            $table->string('maintenance_type');
            $table->string('assigned_mechanic');
            $table->string('part_needed')->nullable();

            $table->dateTime('start_date')->nullable();
            $table->dateTime('completion_date')->nullable();

            $table->enum('status', [
                'On Hold',
                'On Going',
                'Completed',
                'Urgent Repair'
            ])->default('On Going');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_orders');
    }
};