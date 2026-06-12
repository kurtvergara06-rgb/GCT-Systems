<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'item_code')) {
                $table->string('item_code')->nullable()->after('id');
            }

            if (! Schema::hasColumn('inventory_items', 'parts_name')) {
                $table->string('parts_name')->nullable()->after('item_code');
            }

            if (! Schema::hasColumn('inventory_items', 'category')) {
                $table->string('category')->nullable()->after('parts_name');
            }

            if (! Schema::hasColumn('inventory_items', 'on_hand')) {
                $table->integer('on_hand')->default(0)->after('category');
            }

            if (! Schema::hasColumn('inventory_items', 'unit')) {
                $table->string('unit')->default('pcs')->after('on_hand');
            }

            if (! Schema::hasColumn('inventory_items', 'reorder_level')) {
                $table->integer('reorder_level')->default(0)->after('unit');
            }

            if (! Schema::hasColumn('inventory_items', 'status')) {
                $table->string('status')->default('In Stock')->after('reorder_level');
            }

            if (! Schema::hasColumn('inventory_items', 'supplier')) {
                $table->string('supplier')->nullable()->after('status');
            }

            if (! Schema::hasColumn('inventory_items', 'location')) {
                $table->string('location')->nullable()->after('supplier');
            }
        });

        if (Schema::hasColumn('inventory_items', 'item_name')) {
            DB::statement("
                UPDATE inventory_items
                SET parts_name = item_name
                WHERE parts_name IS NULL OR parts_name = ''
            ");
        }

        if (Schema::hasColumn('inventory_items', 'quantity')) {
            DB::statement("
                UPDATE inventory_items
                SET on_hand = quantity
                WHERE on_hand = 0
            ");
        }

        if (Schema::hasColumn('inventory_items', 'stock')) {
            DB::statement("
                UPDATE inventory_items
                SET on_hand = stock
                WHERE on_hand = 0
            ");
        }

        if (Schema::hasColumn('inventory_items', 'stock_quantity')) {
            DB::statement("
                UPDATE inventory_items
                SET on_hand = stock_quantity
                WHERE on_hand = 0
            ");
        }

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

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'location')) {
                $table->dropColumn('location');
            }

            if (Schema::hasColumn('inventory_items', 'supplier')) {
                $table->dropColumn('supplier');
            }

            if (Schema::hasColumn('inventory_items', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('inventory_items', 'reorder_level')) {
                $table->dropColumn('reorder_level');
            }

            if (Schema::hasColumn('inventory_items', 'unit')) {
                $table->dropColumn('unit');
            }

            if (Schema::hasColumn('inventory_items', 'on_hand')) {
                $table->dropColumn('on_hand');
            }

            if (Schema::hasColumn('inventory_items', 'category')) {
                $table->dropColumn('category');
            }

            if (Schema::hasColumn('inventory_items', 'parts_name')) {
                $table->dropColumn('parts_name');
            }

            if (Schema::hasColumn('inventory_items', 'item_code')) {
                $table->dropColumn('item_code');
            }
        });
    }
};