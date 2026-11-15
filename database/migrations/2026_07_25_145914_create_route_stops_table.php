<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shuttle_route_id')
                ->constrained('shuttle_routes')
                ->cascadeOnDelete();

            $table->string('stop_name');

            $table->unsignedInteger('stop_order');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};