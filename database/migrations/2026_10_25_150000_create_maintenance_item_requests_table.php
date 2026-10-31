<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_item_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_order_id')->constrained('job_orders')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable();
            $table->string('status', 32)->default('Submitted');
            $table->json('requested_parts');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique('job_order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_item_requests');
    }
};
