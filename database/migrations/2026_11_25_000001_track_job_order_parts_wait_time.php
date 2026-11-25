<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table): void {
            $table->timestamp('parts_wait_started_at')->nullable();
            $table->unsignedBigInteger('parts_wait_seconds')->default(0);
        });

        // Existing waits can be timed from deployment onward.
        DB::table('job_orders')
            ->where('review_status', 'Approved')
            ->where('status', '!=', 'Completed')
            ->whereNotNull('actual_started_at')
            ->whereRaw("TRIM(COALESCE(part_needed, '')) <> ''")
            ->whereRaw("COALESCE(part_status, '') <> 'Issued'")
            ->update(['parts_wait_started_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table): void {
            $table->dropColumn(['parts_wait_started_at', 'parts_wait_seconds']);
        });
    }
};
