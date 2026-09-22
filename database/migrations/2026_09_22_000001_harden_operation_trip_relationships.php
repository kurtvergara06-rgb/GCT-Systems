<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_assignments', function (Blueprint $table) {
            $table
                ->foreignId('original_bus_id')
                ->nullable()
                ->after('bus_id')
                ->constrained('buses')
                ->nullOnDelete();
        });

        Schema::table('daily_driver_reports', function (Blueprint $table) {
            $table
                ->foreignId('trip_schedule_id')
                ->nullable()
                ->after('bus_id')
                ->constrained('trip_schedules')
                ->nullOnDelete();

            $table
                ->foreignId('trip_assignment_id')
                ->nullable()
                ->after('trip_schedule_id')
                ->constrained('trip_assignments')
                ->nullOnDelete();
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table
                ->foreignId('trip_assignment_id')
                ->nullable()
                ->after('trip_schedule_id')
                ->constrained('trip_assignments')
                ->nullOnDelete();
        });

        $duplicateReplacement = DB::table('incident_replacements')
            ->select('incident_id')
            ->groupBy('incident_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateReplacement) {
            throw new \RuntimeException(
                'Cannot add the one-replacement-per-incident constraint because '
                .'incident '.$duplicateReplacement->incident_id.' already has duplicate replacement rows.'
            );
        }

        Schema::table('incident_replacements', function (Blueprint $table) {
            $table->unique(
                'incident_id',
                'incident_replacements_incident_id_unique'
            );
        });

        $this->backfillIncidentAssignments();
        $this->backfillEffectiveBuses();
    }

    public function down(): void
    {
        DB::table('trip_assignments')
            ->whereNotNull('original_bus_id')
            ->orderBy('id')
            ->chunkById(100, function ($assignments): void {
                foreach ($assignments as $assignment) {
                    DB::table('trip_assignments')
                        ->where('id', $assignment->id)
                        ->update(['bus_id' => $assignment->original_bus_id]);
                }
            });

        Schema::table('incident_replacements', function (Blueprint $table) {
            $table->dropUnique('incident_replacements_incident_id_unique');
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_assignment_id');
        });

        Schema::table('daily_driver_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_assignment_id');
            $table->dropConstrainedForeignId('trip_schedule_id');
        });

        Schema::table('trip_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('original_bus_id');
        });
    }

    private function backfillIncidentAssignments(): void
    {
        DB::table('incidents')
            ->whereNotNull('trip_schedule_id')
            ->whereNull('trip_assignment_id')
            ->orderBy('id')
            ->chunkById(100, function ($incidents): void {
                foreach ($incidents as $incident) {
                    $assignmentId = DB::table('trip_assignments')
                        ->where('trip_schedule_id', $incident->trip_schedule_id)
                        ->value('id');

                    if ($assignmentId) {
                        DB::table('incidents')
                            ->where('id', $incident->id)
                            ->update(['trip_assignment_id' => $assignmentId]);
                    }
                }
            });
    }

    private function backfillEffectiveBuses(): void
    {
        DB::table('trip_assignments')
            ->orderBy('id')
            ->chunkById(100, function ($assignments): void {
                foreach ($assignments as $assignment) {
                    $replacements = DB::table('incident_replacements')
                        ->join(
                            'incidents',
                            'incidents.id',
                            '=',
                            'incident_replacements.incident_id'
                        )
                        ->where(
                            'incidents.trip_schedule_id',
                            $assignment->trip_schedule_id
                        )
                        ->orderBy('incident_replacements.dispatched_at')
                        ->orderBy('incident_replacements.id')
                        ->get([
                            'incident_replacements.original_bus_id',
                            'incident_replacements.replacement_bus_id',
                        ]);

                    if ($replacements->isEmpty()) {
                        continue;
                    }

                    DB::table('trip_assignments')
                        ->where('id', $assignment->id)
                        ->update([
                            'original_bus_id' => $replacements->first()->original_bus_id
                                ?: $assignment->bus_id,
                            'bus_id' => $replacements->last()->replacement_bus_id,
                        ]);
                }
            });
    }
};
