<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_items', 'source')) {
            Schema::table('inventory_items', function (Blueprint $table): void {
                $table->string('source', 20)
                    ->default('app')
                    ->after('status')
                    ->index();
            });
        }

        $demoCatalogCodes = [
            'OIL-ENG-15W40', 'OIL-DEO-15W40', 'OIL-DEX-III',
            'BRK-FLUID-1L', 'PWR-FLUID-1L', 'GRS-MULTI-1KG',
            'CLG-ANTIFREEZE', 'TRS-FLUID-4L', 'FLT-OIL', 'FLT-AIR',
            'FLT-FUEL', 'FLT-CABIN', 'FLT-HYD', 'FLT-SEP', 'BRK-PAD-FR',
            'BRK-PAD-RR', 'BRK-SHOE-RR', 'BRK-MC', 'BRK-CALIPER',
            'BRK-AIR-VLV', 'BATT-12V-180', 'ALT-GEN', 'STARTER-24V',
            'LMP-HL-CLR', 'LMP-TL-RR', 'WIR-HARN', 'BAT-TERMINAL',
            'TIR-11R22-5', 'TIR-295-80', 'RIM-STEEL', 'VLV-STEM',
            'SHOCK-FR', 'SHOCK-RR', 'LEAF-SPRING', 'BALL-JOINT',
            'TIE-ROD-END', 'PNT-YELLOW', 'PNT-WHITE', 'MIRR-REAR',
            'SEAT-BELT', 'AC-REFRIG', 'AC-DRYER', 'AC-COMP', 'AC-BELT',
            'SPK-PLUG', 'GKL-SET', 'PST-BELT', 'CLT-PLATE', 'CLR-PISTON',
            'TAP-ELEC', 'TIE-WIRE', 'RAG-COTTON', 'NIT-GLOVE',
        ];

        DB::table('inventory_items')
            ->where(function ($query) use ($demoCatalogCodes): void {
                $query->whereIn('item_code', $demoCatalogCodes)
                    ->orWhere('item_code', 'like', 'GCT-PART-%')
                    ->orWhere('item_code', 'like', 'DEMO-PART-%');
            })
            ->update(['source' => 'simulated']);

        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'source')) {
            DB::table('inventory_items')
                ->whereIn('id', function ($query): void {
                    $query->select('inventory_item_id')
                        ->from('stock_movements')
                        ->whereIn('source', ['demo', 'simulated'])
                        ->whereNotNull('inventory_item_id');
                })
                ->update(['source' => 'simulated']);

            DB::table('inventory_items')
                ->whereIn('id', function ($query): void {
                    $query->select('inventory_item_id')
                        ->from('stock_movements')
                        ->where('source', 'app')
                        ->whereNotNull('inventory_item_id');
                })
                ->update(['source' => 'app']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventory_items', 'source')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
