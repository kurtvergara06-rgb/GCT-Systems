<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        DB::statement("ALTER TABLE job_orders MODIFY part_status ENUM(
            'None',
            'Pending',
            'Requested',
            'Approved',
            'Rejected',
            'For Purchase',
            'Issued'
        ) DEFAULT 'None'");
    }
};