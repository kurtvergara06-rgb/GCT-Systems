<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('driver_authorized_buses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['driver_id', 'bus_id']);
        });

        Schema::table('buses', function (Blueprint $table): void {
            // Null means a legacy bus has not yet been assigned a company policy.
            $table->string('client_allocation_type', 16)->nullable();
        });

        Schema::create('bus_operation_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->foreignId('operation_client_id')->constrained('operation_clients')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['bus_id', 'operation_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_operation_clients');
        Schema::table('buses', fn (Blueprint $table) => $table->dropColumn('client_allocation_type'));
        Schema::dropIfExists('driver_authorized_buses');
        Schema::dropIfExists('operation_clients');
    }
};
