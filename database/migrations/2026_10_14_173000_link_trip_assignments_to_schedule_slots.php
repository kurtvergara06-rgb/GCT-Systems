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
            $table->unsignedSmallInteger('slot_number')->nullable()->after('trip_schedule_id');
        });

        $currentTripId = null;
        $slotNumber = 0;

        foreach (DB::table('trip_assignments')->orderBy('trip_schedule_id')->orderBy('id')->cursor() as $assignment) {
            if ($currentTripId !== $assignment->trip_schedule_id) {
                $currentTripId = $assignment->trip_schedule_id;
                $slotNumber = 0;
            }

            DB::table('trip_assignments')
                ->where('id', $assignment->id)
                ->update(['slot_number' => ++$slotNumber]);
        }

        Schema::table('trip_assignments', function (Blueprint $table) {
            $table->unique(
                ['trip_schedule_id', 'slot_number'],
                'trip_assignments_schedule_slot_unique'
            );
        });

        Schema::table('auto_schedule_slot_decisions', function (Blueprint $table) {
            $table->foreignId('fulfilled_by_assignment_id')
                ->nullable()
                ->after('decided_by')
                ->unique()
                ->constrained('trip_assignments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auto_schedule_slot_decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fulfilled_by_assignment_id');
        });

        Schema::table('trip_assignments', function (Blueprint $table) {
            $table->dropUnique('trip_assignments_schedule_slot_unique');
            $table->dropColumn('slot_number');
        });
    }
};
