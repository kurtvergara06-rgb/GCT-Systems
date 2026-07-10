<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('bus_no');
            $table->string('driver_name')->nullable();
            $table->decimal('distance_km', 12, 2)->default(0);
            $table->decimal('fuel_liters', 12, 2)->default(0);
            $table->decimal('km_per_liter', 10, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['bus_no', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_reports');
    }
};