<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auto_schedule_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->date('schedule_date');
            $table->string('shift', 30)->default('all');
            $table->string('route_filter', 50)->default('all');
            $table->json('recommendations');
            $table->json('conflicts');
            $table->json('summary')->nullable();
            $table->string('status', 20)->default('Draft');
            $table->timestamps();
            $table->unique(['created_by', 'schedule_date', 'shift', 'route_filter'], 'auto_draft_owner_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_schedule_drafts');
    }
};
