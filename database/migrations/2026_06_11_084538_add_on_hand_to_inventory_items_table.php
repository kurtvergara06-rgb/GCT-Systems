<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_items', 'on_hand')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->integer('on_hand')->default(0)->after('category');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Optional: Copy old quantity/stock column into on_hand if it exists
        |--------------------------------------------------------------------------
        */
        if (Schema::hasColumn('inventory_items', 'quantity')) {
            DB::statement('UPDATE inventory_items SET on_hand = quantity');
        }

        if (Schema::hasColumn('inventory_items', 'stock')) {
            DB::statement('UPDATE inventory_items SET on_hand = stock');
        }

        if (Schema::hasColumn('inventory_items', 'stock_quantity')) {
            DB::statement('UPDATE inventory_items SET on_hand = stock_quantity');
        }

        /*
        |--------------------------------------------------------------------------
        | Recalculate status
        |--------------------------------------------------------------------------
        */
        if (Schema::hasColumn('inventory_items', 'status') && Schema::hasColumn('inventory_items', 'reorder_level')) {
            $items = DB::table('inventory_items')->get();

            foreach ($items as $item) {
                $onHand = (int) ($item->on_hand ?? 0);
                $reorderLevel = (int) ($item->reorder_level ?? 0);

                if ($onHand <= 0) {
                    $status = 'Out of Stock';
                } elseif ($onHand <= $reorderLevel) {
                    $status = 'Low Stock';
                } else {
                    $status = 'In Stock';
                }

                DB::table('inventory_items')
                    ->where('id', $item->id)
                    ->update([
                        'status' => $status,
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_items', 'on_hand')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropColumn('on_hand');
            });
        }
    }
};