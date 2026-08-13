<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove the legacy middleware-generated login row. Successful login
        // is already recorded explicitly as "Logged in to FROMS".
        DB::table('activity_logs')
            ->where('event_type', 'Login')
            ->where('activity', 'Submit Login')
            ->delete();

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'created_at'],
                'activity_logs_user_created_at_idx'
            );

            $table->index(
                ['module', 'event_type', 'created_at'],
                'activity_logs_module_event_created_at_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex('activity_logs_user_created_at_idx');
            $table->dropIndex('activity_logs_module_event_created_at_idx');
        });
    }
};
