<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reserve buses are shared standby assets, not permanently owned.
        // Remove only master-data pivots; never touch trip assignments,
        // approvals, attendance, or historical operations.
        DB::table('driver_authorized_buses')
            ->whereIn('bus_id', DB::table('buses')->select('id')->where('fleet_purpose', 'Reserve'))
            ->delete();

        DB::table('bus_operation_clients')
            ->whereIn('bus_id', DB::table('buses')->select('id')->where('fleet_purpose', 'Reserve'))
            ->delete();

        DB::table('buses')
            ->where('fleet_purpose', 'Reserve')
            ->update(['client_allocation_type' => null]);
    }

    public function down(): void
    {
        // Do not recreate old permanent allocations: doing so could violate
        // exclusive active driver ownership or restore outdated client links.
    }
};
