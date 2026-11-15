<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasOriginLegacy = Schema::hasColumn(
            'shuttle_routes',
            'origin_location_source'
        );

        $hasDestinationLegacy = Schema::hasColumn(
            'shuttle_routes',
            'destination_location_source'
        );

        $hasDistanceLegacy = Schema::hasColumn(
            'shuttle_routes',
            'distance_manually_adjusted'
        );

        $hasTimeLegacy = Schema::hasColumn(
            'shuttle_routes',
            'time_manually_adjusted'
        );

        if (
            $hasOriginLegacy
            || $hasDestinationLegacy
            || $hasDistanceLegacy
            || $hasTimeLegacy
        ) {
            DB::table('shuttle_routes')
                ->orderBy('id')
                ->chunkById(
                    200,
                    function ($routes) use (
                        $hasOriginLegacy,
                        $hasDestinationLegacy,
                        $hasDistanceLegacy,
                        $hasTimeLegacy
                    ): void {
                        foreach ($routes as $route) {
                            $updates = [];

                            if (
                                $hasOriginLegacy
                                && Schema::hasColumn(
                                    'shuttle_routes',
                                    'origin_source'
                                )
                                && empty($route->origin_source)
                                && ! empty(
                                    $route->origin_location_source
                                )
                            ) {
                                $updates['origin_source'] =
                                    $route->origin_location_source;
                            }

                            if (
                                $hasDestinationLegacy
                                && Schema::hasColumn(
                                    'shuttle_routes',
                                    'destination_source'
                                )
                                && empty(
                                    $route->destination_source
                                )
                                && ! empty(
                                    $route->destination_location_source
                                )
                            ) {
                                $updates['destination_source'] =
                                    $route->destination_location_source;
                            }

                            if (
                                $hasDistanceLegacy
                                && Schema::hasColumn(
                                    'shuttle_routes',
                                    'distance_is_manual'
                                )
                                && (bool) $route->distance_manually_adjusted
                            ) {
                                $updates['distance_is_manual'] =
                                    true;
                            }

                            if (
                                $hasTimeLegacy
                                && Schema::hasColumn(
                                    'shuttle_routes',
                                    'time_is_manual'
                                )
                                && (bool) $route->time_manually_adjusted
                            ) {
                                $updates['time_is_manual'] =
                                    true;
                            }

                            if ($updates !== []) {
                                DB::table('shuttle_routes')
                                    ->where(
                                        'id',
                                        $route->id
                                    )
                                    ->update($updates);
                            }
                        }
                    }
                );
        }

        Schema::table(
            'shuttle_routes',
            function (Blueprint $table) use (
                $hasOriginLegacy,
                $hasDestinationLegacy,
                $hasDistanceLegacy,
                $hasTimeLegacy
            ): void {
                $columns = [];

                if ($hasOriginLegacy) {
                    $columns[] =
                        'origin_location_source';
                }

                if ($hasDestinationLegacy) {
                    $columns[] =
                        'destination_location_source';
                }

                if ($hasDistanceLegacy) {
                    $columns[] =
                        'distance_manually_adjusted';
                }

                if ($hasTimeLegacy) {
                    $columns[] =
                        'time_manually_adjusted';
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'shuttle_routes',
            function (Blueprint $table): void {
                if (
                    ! Schema::hasColumn(
                        'shuttle_routes',
                        'origin_location_source'
                    )
                ) {
                    $table->string(
                        'origin_location_source',
                        50
                    )->nullable();
                }

                if (
                    ! Schema::hasColumn(
                        'shuttle_routes',
                        'destination_location_source'
                    )
                ) {
                    $table->string(
                        'destination_location_source',
                        50
                    )->nullable();
                }

                if (
                    ! Schema::hasColumn(
                        'shuttle_routes',
                        'distance_manually_adjusted'
                    )
                ) {
                    $table->boolean(
                        'distance_manually_adjusted'
                    )->default(false);
                }

                if (
                    ! Schema::hasColumn(
                        'shuttle_routes',
                        'time_manually_adjusted'
                    )
                ) {
                    $table->boolean(
                        'time_manually_adjusted'
                    )->default(false);
                }
            }
        );

        DB::table('shuttle_routes')
            ->orderBy('id')
            ->chunkById(
                200,
                function ($routes): void {
                    foreach ($routes as $route) {
                        DB::table('shuttle_routes')
                            ->where(
                                'id',
                                $route->id
                            )
                            ->update([
                                'origin_location_source' =>
                                    $route->origin_source
                                    ?? null,

                                'destination_location_source' =>
                                    $route->destination_source
                                    ?? null,

                                'distance_manually_adjusted' =>
                                    (bool) (
                                        $route->distance_is_manual
                                        ?? false
                                    ),

                                'time_manually_adjusted' =>
                                    (bool) (
                                        $route->time_is_manual
                                        ?? false
                                    ),
                            ]);
                    }
                }
            );
    }
};
