<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        $conflictingBusIds = DB::table('driver_authorized_buses')
            ->select('bus_id')
            ->groupBy('bus_id')
            ->havingRaw('COUNT(DISTINCT driver_id) > 1')
            ->orderBy('bus_id')
            ->pluck('bus_id');

        if ($conflictingBusIds->isNotEmpty()) {
            $busLabels = DB::table('buses')
                ->whereIn('id', $conflictingBusIds)
                ->orderBy('bus_no')
                ->pluck('bus_no')
                ->all();

            throw new \RuntimeException(
                'Cannot enforce one authorized driver per bus. Resolve the duplicate driver authorizations for: '
                .implode(', ', $busLabels)
                .'. No authorization records were changed.'
            );
        }

        Schema::table('driver_authorized_buses', function (Blueprint $table): void {
            $table->unique('bus_id', 'driver_authorized_buses_bus_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('driver_authorized_buses', function (Blueprint $table): void {
            $table->dropUnique('driver_authorized_buses_bus_id_unique');
        });
    }
};
