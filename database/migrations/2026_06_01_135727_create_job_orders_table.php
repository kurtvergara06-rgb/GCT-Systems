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
            $table->string('service');
            $table->enum('type', ['PMS', 'Repair']);
            $table->string('assigned_mechanic')->nullable();
            $table->enum('status', ['On Hold', 'On Going', 'Completed', 'Urgent Repair'])->default('On Hold');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->dateTime('date_reported');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_orders');
    }
};