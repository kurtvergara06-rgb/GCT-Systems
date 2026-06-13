<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_requests', 'source_type')) {
                $table->string('source_type')
                    ->nullable()
                    ->after('status');
            }

            if (! Schema::hasColumn('purchase_requests', 'source_inventory_item_id')) {
                $table->unsignedBigInteger('source_inventory_item_id')
                    ->nullable()
                    ->after('source_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'source_inventory_item_id')) {
                $table->dropColumn('source_inventory_item_id');
            }

            if (Schema::hasColumn('purchase_requests', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};