<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_logs')
            ->where('event_type', 'Logout')
            ->where(function ($query): void {
                $query
                    ->whereNull('module')
                    ->orWhere('module', '')
                    ->orWhere('module', 'System');
            })
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->update([
                'module' => DB::raw('department'),
            ]);
    }

    public function down(): void
    {
        // Historical department attribution is intentionally preserved.
    }
};
