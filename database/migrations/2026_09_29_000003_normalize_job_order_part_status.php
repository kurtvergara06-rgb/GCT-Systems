<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('job_orders')
            ->where(function ($query) {
                $query->whereNull('part_needed')
                    ->orWhereRaw("TRIM(part_needed) = ''");
            })
            ->update([
                'part_needed' => null,
                'part_status' => 'No Parts Needed',
            ]);

        DB::table('job_orders')
            ->whereNotNull('part_needed')
            ->whereRaw("TRIM(part_needed) <> ''")
            ->where(function ($query) {
                $query->whereNull('part_status')
                    ->orWhereIn('part_status', ['', 'Unknown', 'No Parts Needed']);
            })
            ->update([
                'part_status' => 'Not Requested',
            ]);
    }

    public function down(): void
    {
        DB::table('job_orders')
            ->where('part_status', 'No Parts Required')
            ->update([
                'part_status' => 'No Parts Needed',
            ]);
    }
};
