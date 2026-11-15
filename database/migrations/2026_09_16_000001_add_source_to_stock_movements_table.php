<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'source')) {
                $table->string('source')->default('app')->after('created_by');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Mark the pre-existing ledger as demo data
        |--------------------------------------------------------------------------
        | The audit established that every existing stock_movements row was
        | fabricated by DemoDataSeeder::seedStockMovements(). Those rows are
        | historical (never deleted) but labelled 'demo' so they can be isolated
        | from genuine operational transactions and excluded from future
        | Model #4 training data.
        */
        if (Schema::hasColumn('stock_movements', 'source')) {
            DB::table('stock_movements')->update(['source' => 'demo']);
        }
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};