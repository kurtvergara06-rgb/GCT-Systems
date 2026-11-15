<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_bus_rental_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_bus_rental_id')->constrained('external_bus_rentals')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->json('previous_values')->nullable();
            $table->json('new_values');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['external_bus_rental_id', 'created_at'], 'external_rental_audit_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_bus_rental_audits');
    }
};
