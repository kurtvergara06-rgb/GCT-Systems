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

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('purchase_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('purchase_requests', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('rejected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'issued_at')) {
                $table->dropColumn('issued_at');
            }

            if (Schema::hasColumn('purchase_requests', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }

            if (Schema::hasColumn('purchase_requests', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });

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
};