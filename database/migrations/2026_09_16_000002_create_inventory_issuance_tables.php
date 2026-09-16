<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('issue_no')->unique();
            $table->string('issued_to');
            $table->string('purpose');
            $table->string('reference_no')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index('issued_at');
            $table->index('issue_no');
        });

        Schema::create('inventory_issuance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_issuance_id')
                ->constrained('inventory_issuances')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->nullable()
                ->constrained('inventory_items')
                ->nullOnDelete();
            $table->string('item_code')->nullable();
            $table->string('item_name');
            $table->integer('quantity');
            $table->string('unit')->nullable();
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->foreignId('stock_movement_id')
                ->nullable()
                ->constrained('stock_movements')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('inventory_issuance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_issuance_items');
        Schema::dropIfExists('inventory_issuances');
    }
};