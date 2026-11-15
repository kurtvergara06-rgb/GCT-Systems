<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table) {
            if (! Schema::hasColumn('gps_trip_records', 'record_no')) {
                $table->string('record_no')->nullable()->after('batch_upload_id');
            }
        });

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `gps_trip_records` MODIFY `bus_no` VARCHAR(50) NULL;');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE gps_trip_records ALTER COLUMN bus_no DROP NOT NULL;');
        }
    }

    public function down(): void
    {
        Schema::table('gps_trip_records', function (Blueprint $table) {
            if (Schema::hasColumn('gps_trip_records', 'record_no')) {
                $table->dropColumn('record_no');
            }
        });

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `gps_trip_records` MODIFY `bus_no` VARCHAR(50) NOT NULL;');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE gps_trip_records ALTER COLUMN bus_no SET NOT NULL;');
        }
    }
};
