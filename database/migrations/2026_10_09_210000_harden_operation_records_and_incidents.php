<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_number_sequences', function (Blueprint $table): void {
            $table->string('sequence_key')->primary();
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
        });

        Schema::create('daily_driver_report_ticket_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_driver_report_id')
                ->constrained('daily_driver_reports')
                ->cascadeOnDelete();
            $table->date('report_date');
            $table->string('normalized_ticket', 50);
            $table->string('trip_ticket', 50);
            $table->timestamps();
            $table->unique(['report_date', 'normalized_ticket'], 'ddr_ticket_date_unique');
        });

        Schema::table('daily_driver_report_trip_entries', function (Blueprint $table): void {
            $table->foreignId('trip_schedule_id')->nullable()->after('daily_driver_report_id')
                ->constrained('trip_schedules')->nullOnDelete();
            $table->foreignId('trip_assignment_id')->nullable()->after('trip_schedule_id')
                ->constrained('trip_assignments')->nullOnDelete();
        });

        Schema::table('trip_schedules', function (Blueprint $table): void {
            $table->string('route_code_snapshot', 50)->nullable()->after('shuttle_route_id');
            $table->string('route_name_snapshot')->nullable()->after('route_code_snapshot');
            $table->string('route_origin_snapshot')->nullable()->after('route_name_snapshot');
            $table->string('route_destination_snapshot')->nullable()->after('route_origin_snapshot');
            $table->decimal('planned_distance_km', 10, 2)->nullable()->after('route_destination_snapshot');
            $table->unsignedInteger('planned_duration_minutes')->nullable()->after('planned_distance_km');
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('active_breakdown_key')->nullable()->after('incident_type')->unique();
        });

        DB::table('trip_schedules')->orderBy('id')->chunkById(100, function ($trips): void {
            foreach ($trips as $trip) {
                $route = DB::table('shuttle_routes')->where('id', $trip->shuttle_route_id)->first();
                if (! $route) {
                    continue;
                }

                DB::table('trip_schedules')->where('id', $trip->id)->update([
                    'route_code_snapshot' => $route->route_code,
                    'route_name_snapshot' => $route->route_name,
                    'route_origin_snapshot' => $route->origin,
                    'route_destination_snapshot' => $route->destination,
                    'planned_distance_km' => $route->distance_km,
                    'planned_duration_minutes' => $route->estimated_time_minutes,
                ]);
            }
        });

        DB::table('daily_driver_reports')->orderBy('id')->each(function ($report): void {
            DB::table('daily_driver_report_ticket_claims')->insertOrIgnore([
                'daily_driver_report_id' => $report->id,
                'report_date' => $report->report_date,
                'normalized_ticket' => mb_strtolower(trim($report->trip_ticket)),
                'trip_ticket' => trim($report->trip_ticket),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('daily_driver_report_trip_entries')
                ->where('daily_driver_report_id', $report->id)
                ->orderBy('id')
                ->each(function ($entry) use ($report): void {
                    DB::table('daily_driver_report_ticket_claims')->insertOrIgnore([
                        'daily_driver_report_id' => $report->id,
                        'report_date' => $report->report_date,
                        'normalized_ticket' => mb_strtolower(trim($entry->trip_ticket)),
                        'trip_ticket' => trim($entry->trip_ticket),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        });
    }

    public function down(): void
    {
        Schema::table('incidents', fn (Blueprint $table) => $table->dropColumn('active_breakdown_key'));
        Schema::table('trip_schedules', fn (Blueprint $table) => $table->dropColumn([
            'route_code_snapshot', 'route_name_snapshot', 'route_origin_snapshot',
            'route_destination_snapshot', 'planned_distance_km', 'planned_duration_minutes',
        ]));
        Schema::table('daily_driver_report_trip_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trip_assignment_id');
            $table->dropConstrainedForeignId('trip_schedule_id');
        });
        Schema::dropIfExists('daily_driver_report_ticket_claims');
        Schema::dropIfExists('operation_number_sequences');
    }
};
