<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            'Pending Purchase',
            'Delivering',
            'Delivered',
            'Issued'
        ) DEFAULT 'Draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_requests MODIFY status ENUM(
            'Draft',
            'Submitted',
            'For Issuance',
            'Rejected',
            'Issued'
        ) DEFAULT 'Draft'");
    }
};