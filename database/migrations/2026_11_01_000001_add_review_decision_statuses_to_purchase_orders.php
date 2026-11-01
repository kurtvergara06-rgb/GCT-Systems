<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('For Review','Revision Required','Rejected','Ordered','For Pick-up','For Delivery','Delivered','Picked Up') NOT NULL DEFAULT 'For Review'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('purchase_orders')
                ->whereIn('status', ['Revision Required', 'Rejected'])
                ->update(['status' => 'For Review']);

            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('For Review','Ordered','For Pick-up','For Delivery','Delivered','Picked Up') NOT NULL DEFAULT 'For Review'");
        }
    }
};
