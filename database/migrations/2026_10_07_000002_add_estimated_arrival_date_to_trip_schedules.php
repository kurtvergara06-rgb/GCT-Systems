<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table): void {
            $table
                ->date('estimated_arrival_date')
                ->nullable()
                ->after('estimated_arrival_time');
        });

        DB::table('trip_schedules')
            ->select([
                'id',
                'trip_date',
                'departure_time',
                'estimated_arrival_time',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($trips): void {
                foreach ($trips as $trip) {
                    if (
                        ! $trip->trip_date
                        || ! $trip->departure_time
                        || ! $trip->estimated_arrival_time
                    ) {
                        continue;
                    }

                    $departure = Carbon::parse(
                        $trip->trip_date . ' ' . $trip->departure_time
                    );

                    $arrival = Carbon::parse(
                        $trip->trip_date . ' ' . $trip->estimated_arrival_time
                    );

                    if ($arrival->lessThanOrEqualTo($departure)) {
                        $arrival->addDay();
                    }

                    DB::table('trip_schedules')
                        ->where('id', $trip->id)
                        ->update([
                            'estimated_arrival_date' =>
                                $arrival->toDateString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table): void {
            $table->dropColumn('estimated_arrival_date');
        });
    }
};
