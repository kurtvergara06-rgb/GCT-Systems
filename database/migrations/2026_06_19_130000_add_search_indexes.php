<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists (MySQL safe).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $driver = DB::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            return false;
        }

        if ($driver !== 'mysql') {
            return false;
        }

        $dbName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(1) as cnt FROM information_schema.statistics WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$dbName, $table, $indexName]
        );

        return ($result->cnt ?? 0) > 0;
    }

    public function up(): void
    {
        // purchase_requests.status
        if (Schema::hasColumn('purchase_requests', 'status') && ! $this->indexExists('purchase_requests', 'pr_status_idx')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->index('status', 'pr_status_idx');
            });
        }

        // purchase_requests.job_order_no
        if (Schema::hasColumn('purchase_requests', 'job_order_no') && ! $this->indexExists('purchase_requests', 'pr_job_order_no_idx')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->index('job_order_no', 'pr_job_order_no_idx');
            });
        }

        // job_orders.status
        if (Schema::hasColumn('job_orders', 'status') && ! $this->indexExists('job_orders', 'jo_status_idx')) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->index('status', 'jo_status_idx');
            });
        }

        // job_orders.part_status
        if (Schema::hasColumn('job_orders', 'part_status') && ! $this->indexExists('job_orders', 'jo_part_status_idx')) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->index('part_status', 'jo_part_status_idx');
            });
        }

        // purchase_orders.status
        if (Schema::hasColumn('purchase_orders', 'status') && ! $this->indexExists('purchase_orders', 'po_status_idx')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->index('status', 'po_status_idx');
            });
        }

        // inventory_items.item_name
        if (Schema::hasColumn('inventory_items', 'item_name') && ! $this->indexExists('inventory_items', 'inv_item_name_idx')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->index('item_name', 'inv_item_name_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_requests', 'status') && $this->indexExists('purchase_requests', 'pr_status_idx')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropIndex('pr_status_idx');
            });
        }

        if (Schema::hasColumn('purchase_requests', 'job_order_no') && $this->indexExists('purchase_requests', 'pr_job_order_no_idx')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropIndex('pr_job_order_no_idx');
            });
        }

        if (Schema::hasColumn('job_orders', 'status') && $this->indexExists('job_orders', 'jo_status_idx')) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->dropIndex('jo_status_idx');
            });
        }

        if (Schema::hasColumn('job_orders', 'part_status') && $this->indexExists('job_orders', 'jo_part_status_idx')) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->dropIndex('jo_part_status_idx');
            });
        }

        if (Schema::hasColumn('purchase_orders', 'status') && $this->indexExists('purchase_orders', 'po_status_idx')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropIndex('po_status_idx');
            });
        }

        if (Schema::hasColumn('inventory_items', 'item_name') && $this->indexExists('inventory_items', 'inv_item_name_idx')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropIndex('inv_item_name_idx');
            });
        }
    }
};
