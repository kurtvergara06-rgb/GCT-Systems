<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        $duplicates = DB::table('activity_logs')
            ->select(
                'user_id',
                'activity',
                'event_type',
                'module',
                'created_at',
                DB::raw('MIN(id) as keep_id'),
                DB::raw('COUNT(*) as duplicate_count')
            )
            ->whereIn('event_type', ['Login', 'Logout'])
            ->groupBy('user_id', 'activity', 'event_type', 'module', 'created_at')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('activity_logs')
                ->where('user_id', $duplicate->user_id)
                ->where('activity', $duplicate->activity)
                ->where('event_type', $duplicate->event_type)
                ->where('module', $duplicate->module)
                ->where('created_at', $duplicate->created_at)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }
    }

    public function down(): void
    {
        // Historical duplicate cleanup is intentionally irreversible.
    }
};
