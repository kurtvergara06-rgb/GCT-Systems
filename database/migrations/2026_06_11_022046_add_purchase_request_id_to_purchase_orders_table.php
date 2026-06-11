<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'purchase_request_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreignId('purchase_request_id')
                    ->nullable()
                    ->after('po_date')
                    ->constrained('purchase_requests')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_orders', 'purchase_request_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('purchase_request_id');
            });
        }
    }
};

