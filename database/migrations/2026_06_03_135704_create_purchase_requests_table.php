<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();

            $table->string('pr_no')->unique();
            $table->string('job_order_no');
            $table->string('bus_no');
            $table->string('item');
            $table->integer('quantity');
            $table->text('remarks')->nullable();

            $table->enum('status', [
                'Submitted',
                'Approved',
                'Rejected',
                'For Purchase',
                'Ordered',
                'For Pick-up',
                'For Delivery',
                'Delivered',
                'Picked Up',
                'Issued',
            ])->default('Submitted');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
