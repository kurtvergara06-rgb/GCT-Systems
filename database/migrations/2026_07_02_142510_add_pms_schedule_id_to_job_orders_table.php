<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('job_orders', 'pms_schedule_id')) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->foreignId('pms_schedule_id')
                    ->nullable()
                    ->after('bus_no')
                    ->constrained('pms_schedules')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_orders', 'pms_schedule_id')) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->dropForeign(['pms_schedule_id']);
                $table->dropColumn('pms_schedule_id');
            });
        }
    }
};