<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('po_no')->unique();
            $table->date('po_date');

            $table->string('supplier_name');
            $table->text('supplier_address_tel')->nullable();

            $table->string('terms')->nullable();
            $table->string('terms_of_payment')->nullable();
            $table->text('purpose')->nullable();

            $table->json('items')->nullable();

            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);

            $table->enum('status', [
                'Ordered',
                'For Pick-up',
                'For Delivery',
                'Delivered',
                'Picked Up',
            ])->default('Ordered');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
