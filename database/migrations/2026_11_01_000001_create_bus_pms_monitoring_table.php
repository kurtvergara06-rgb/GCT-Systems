<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bus_pms_monitoring', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bus_id')->unique()->constrained('buses')->restrictOnDelete();
            $table->date('last_completed_pms_date');
            $table->decimal('last_completed_pms_odometer_km', 12, 2);
            $table->unsignedInteger('interval_km')->default(10000);
            $table->unsignedTinyInteger('interval_months')->default(3);
            $table->boolean('is_active')->default(true);
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_pms_monitoring');
    }
};
