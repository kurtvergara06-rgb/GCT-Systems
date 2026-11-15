<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('job_orders', 'part_status')) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->string('part_status')->default('Not Requested')->after('status');
            });
        }

        DB::table('job_orders')
            ->whereNull('part_status')
            ->orWhere('part_status', '')
            ->orWhere('part_status', 'Unknown')
            ->update([
                'part_status' => 'Not Requested',
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_orders', 'part_status')) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->dropColumn('part_status');
            });
        }
    }
};