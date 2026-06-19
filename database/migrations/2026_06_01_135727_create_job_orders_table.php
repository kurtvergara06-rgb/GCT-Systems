<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('job_orders');

        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();

            $table->string('job_order_no')->unique();

            $table->string('bus_no');

            $table->text('problem_issue');

            $table->string('maintenance_type');

            $table->string('assigned_mechanic')->nullable();

            /*
             * Example saved value:
             * Transmission Fluid - Qty: 4 liter, Tire - Qty: 8 pcs
             */
            $table->text('part_needed')->nullable();

            $table->dateTime('start_date')->nullable();

            $table->dateTime('completion_date')->nullable();

            $table->enum('status', [
                'On Hold',
                'On Going',
                'Completed',
            ])->default('On Going');

            $table->enum('part_status', [
                'No Parts Needed',
                'Not Requested',
                'Submitted',
                'Approved',
                'Rejected',
                'For Purchase',
                'Ordered',
                'For Pick-up',
                'For Delivery',
                'Delivered',
                'Picked Up',
                'Issued',
            ])->default('No Parts Needed');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_orders');
    }
};