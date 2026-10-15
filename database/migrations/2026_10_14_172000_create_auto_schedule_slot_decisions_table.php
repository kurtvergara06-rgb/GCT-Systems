<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auto_schedule_drafts', function (Blueprint $table) {
            $table->json('decisions')->nullable()->after('conflicts');
        });

        Schema::create('auto_schedule_slot_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_schedule_id')->constrained('trip_schedules')->cascadeOnDelete();
            $table->unsignedSmallInteger('slot_number');
            $table->string('decision', 30);
            $table->string('status', 20)->default('Pending');
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->constrained('users');
            $table->timestamp('decided_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_schedule_id', 'slot_number'], 'auto_slot_decision_unique');
            $table->index(['status', 'decision'], 'auto_slot_decision_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_schedule_slot_decisions');

        Schema::table('auto_schedule_drafts', function (Blueprint $table) {
            $table->dropColumn('decisions');
        });
    }
};
