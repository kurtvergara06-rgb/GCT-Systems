<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('warehouse_status', 40)
                ->nullable()
                ->after('status')
                ->index();
            $table->unsignedBigInteger('warehouse_approved_by')->nullable()->after('warehouse_status');
            $table->timestamp('warehouse_approved_at')->nullable()->after('warehouse_approved_by');
            $table->unsignedBigInteger('warehouse_prepared_by')->nullable()->after('warehouse_approved_at');
            $table->timestamp('warehouse_prepared_at')->nullable()->after('warehouse_prepared_by');
            $table->json('warehouse_issue_quantities')->nullable()->after('warehouse_prepared_at');
        });

        DB::table('purchase_requests')
            ->where('status', 'Issued')
            ->update(['warehouse_status' => 'Issued']);

        DB::table('purchase_requests')
            ->whereNull('warehouse_status')
            ->whereIn('status', ['Approved', 'Delivered', 'Picked Up'])
            ->update(['warehouse_status' => 'Pending Warehouse Approval']);
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['warehouse_status']);
            $table->dropColumn([
                'warehouse_status',
                'warehouse_approved_by',
                'warehouse_approved_at',
                'warehouse_prepared_by',
                'warehouse_prepared_at',
                'warehouse_issue_quantities',
            ]);
        });
    }
};
