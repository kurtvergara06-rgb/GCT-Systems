<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_replacements', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('incident_id')
                ->constrained('incidents')
                ->cascadeOnDelete();

            $table
                ->foreignId('original_bus_id')
                ->constrained('buses')
                ->restrictOnDelete();

            $table
                ->foreignId('replacement_bus_id')
                ->constrained('buses')
                ->restrictOnDelete();

            $table->timestamp('dispatched_at');
            $table->unsignedBigInteger('dispatched_by')->nullable()->index();

            $table->timestamps();

            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_replacements');
    }
};
