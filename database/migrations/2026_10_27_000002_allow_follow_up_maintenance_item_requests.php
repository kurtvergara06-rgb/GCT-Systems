<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maintenance_item_requests', function (Blueprint $table): void {
            $table->index('job_order_id', 'maintenance_item_requests_job_order_id_index');
        });
        Schema::table('maintenance_item_requests', function (Blueprint $table): void {
            $table->dropUnique('maintenance_item_requests_job_order_id_unique');
        });
    }

    public function down(): void
    {
        $hasFollowUpRequests = DB::table('maintenance_item_requests')
            ->select('job_order_id')
            ->groupBy('job_order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasFollowUpRequests) {
            throw new \RuntimeException(
                'Cannot restore the single-IR constraint while follow-up Item Requests exist.'
            );
        }

        Schema::table('maintenance_item_requests', function (Blueprint $table): void {
            $table->unique('job_order_id');
        });
        Schema::table('maintenance_item_requests', function (Blueprint $table): void {
            $table->dropIndex('maintenance_item_requests_job_order_id_index');
        });
    }
};
