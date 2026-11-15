<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('For Review','Ordered','For Pick-up','For Delivery','Delivered','Picked Up') NOT NULL DEFAULT 'For Review'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('purchase_orders')->where('status', 'For Review')->update(['status' => 'Ordered']);
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('Ordered','For Pick-up','For Delivery','Delivered','Picked Up') NOT NULL DEFAULT 'Ordered'");
        }
    }
};
