<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('item_code')->nullable();
            $table->string('item_name');
            $table->string('reference_no')->nullable();
            $table->string('movement_type');
            $table->integer('quantity_change');
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->string('unit')->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['movement_type', 'created_at']);
            $table->index('reference_no');
            $table->index('item_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
