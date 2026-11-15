<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_trip_time_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_client_id')->constrained('operation_clients')->cascadeOnDelete();
            $table->string('category', 16);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['operation_client_id', 'category'], 'cttc_client_category_unique');
        });

        Schema::create('client_trip_time_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_trip_time_configuration_id')
                ->constrained('client_trip_time_configurations')
                ->cascadeOnDelete();
            $table->time('slot_time');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['client_trip_time_configuration_id', 'slot_time'], 'ctts_config_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_trip_time_slots');
        Schema::dropIfExists('client_trip_time_configurations');
    }
};
