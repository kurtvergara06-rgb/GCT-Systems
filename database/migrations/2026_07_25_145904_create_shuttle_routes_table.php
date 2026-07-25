<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shuttle_routes', function (Blueprint $table) {
            $table->id();

            $table->string('route_code')->unique();

            $table->string('route_name');

            $table->string('origin');

            $table->string('destination');

            $table->decimal(
                'distance_km',
                8,
                2
            )->nullable();

            $table->unsignedInteger(
                'estimated_time_minutes'
            )->nullable();

            $table->enum(
                'status',
                [
                    'Active',
                    'Inactive',
                ]
            )->default('Active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shuttle_routes');
    }
};