<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_purchases', function (Blueprint $table) {
            $table->id();

            $table->string('schedule_no')->unique();
            $table->string('schedule_name');

            $table->string('supplier_name');
            $table->string('supplier_contact')->nullable();

            $table->string('item');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 50)->default('PC');

            $table->enum('frequency', [
                'Weekly',
                'Biweekly',
                'Monthly',
                'Quarterly',
                'Semiannual',
                'Yearly',
                'Custom',
            ]);

            $table->unsignedInteger('custom_interval_days')->nullable();

            $table->date('start_date');
            $table->date('next_purchase_date');

            $table->decimal('estimated_cost', 14, 2)->default(0);

            $table->enum('status', [
                'Active',
                'Paused',
                'Completed',
            ])->default('Active');

            $table->text('notes')->nullable();

            $table->foreignId('last_po_id')
                ->nullable()
                ->constrained('purchase_orders')
                ->nullOnDelete();

            $table->timestamp('last_purchased_at')->nullable();

            $table->timestamps();

            $table->index([
                'status',
                'next_purchase_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_purchases');
    }
};