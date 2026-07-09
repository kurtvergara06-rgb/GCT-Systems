<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $database = DB::getDatabaseName();

        $busNoUniqueExists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'pms_schedules')
            ->where('index_name', 'pms_schedules_bus_no_unique')
            ->exists();

        if ($busNoUniqueExists) {
            Schema::table('pms_schedules', function (Blueprint $table) {
                $table->dropUnique('pms_schedules_bus_no_unique');
            });
        }

        $busTaskUniqueExists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'pms_schedules')
            ->where('index_name', 'pms_schedules_bus_no_maintenance_type_unique')
            ->exists();

        if (! $busTaskUniqueExists) {
            Schema::table('pms_schedules', function (Blueprint $table) {
                $table->unique(
                    ['bus_no', 'maintenance_type'],
                    'pms_schedules_bus_no_maintenance_type_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('pms_schedules', function (Blueprint $table) {
            $table->dropUnique('pms_schedules_bus_no_maintenance_type_unique');
        });
    }
};