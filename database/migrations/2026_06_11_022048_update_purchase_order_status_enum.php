<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_orders')
            ->where('status', 'For Purchase')
            ->update(['status' => 'Ordered']);

        DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM(
            'Ordered',
            'For Pick-up',
            'For Delivery',
            'Delivered',
            'Picked Up'
        ) DEFAULT 'Ordered'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM(
            'For Purchase',
            'Ordered',
            'For Pick-up',
            'For Delivery',
            'Delivered',
            'Picked Up'
        ) DEFAULT 'For Purchase'");
    }
};
