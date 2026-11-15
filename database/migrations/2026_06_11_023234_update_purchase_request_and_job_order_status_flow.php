<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_requests MODIFY status ENUM(
            'Draft',
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
        ) DEFAULT 'Draft'");

        DB::statement("ALTER TABLE job_orders MODIFY part_status ENUM(
            'None',
            'Pending',
            'Requested',
            'Approved',
            'Rejected',
            'For Purchase',
            'Ordered',
            'For Pick-up',
            'For Delivery',
            'Delivered',
            'Picked Up',
            'Issued'
        ) DEFAULT 'None'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_requests MODIFY status ENUM(
            'Draft',
            'Submitted',
            'Approved',
            'Rejected',
            'For Purchase',
            'Issued'
        ) DEFAULT 'Draft'");

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