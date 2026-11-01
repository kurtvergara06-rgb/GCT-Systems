<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fuel_reports', function (Blueprint $table) {
            $table->string('gas_station', 150)->nullable();
            $table->string('po_reference', 80)->nullable();
            $table->time('issued_at')->nullable();
            $table->string('plate_number', 30)->nullable();
            $table->string('entry_type', 30)->nullable();
        });
        Schema::create('fuel_report_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_report_id')->constrained('fuel_reports')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('trip_date');
            $table->string('ticket_number', 80)->nullable();
            $table->string('origin', 150);
            $table->string('destination', 150);
            $table->decimal('distance_km', 10, 2);
            $table->timestamps();
            $table->unique(['fuel_report_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_report_trips');
        Schema::table('fuel_reports', function (Blueprint $table) {
            $table->dropColumn(['gas_station', 'po_reference', 'issued_at', 'plate_number', 'entry_type']);
        });
    }
};
