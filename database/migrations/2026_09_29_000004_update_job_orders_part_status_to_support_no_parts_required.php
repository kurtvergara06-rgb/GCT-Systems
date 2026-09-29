<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_orders') && Schema::hasColumn('job_orders', 'part_status')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE job_orders MODIFY part_status VARCHAR(50) DEFAULT 'No Parts Required'");
            } else {
                Schema::table('job_orders', function (Blueprint $table) {
                    $table->string('part_status', 50)->default('No Parts Required')->change();
                });
            }

            DB::table('job_orders')
                ->where('part_status', 'No Parts Needed')
                ->orWhere(function ($q) {
                    $q->whereNull('part_needed')
                        ->orWhere('part_needed', '');
                })
                ->update(['part_status' => 'No Parts Required']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('job_orders') && Schema::hasColumn('job_orders', 'part_status')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE job_orders MODIFY part_status ENUM(
                    'No Parts Needed',
                    'Not Requested',
                    'Requested',
                    'Submitted',
                    'Approved',
                    'Rejected',
                    'For Purchase',
                    'Ordered',
                    'For Pick-up',
                    'For Delivery',
                    'Delivered',
                    'Picked Up',
                    'Issued'
                ) DEFAULT 'No Parts Needed'");
            }
        }
    }
};
