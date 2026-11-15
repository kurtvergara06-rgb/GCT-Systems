<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auto_schedule_reserve_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_assignment_id')->nullable()->unique()->constrained('trip_assignments')->nullOnDelete();
            $table->unsignedBigInteger('trip_schedule_id')->index();
            $table->unsignedBigInteger('bus_id')->index();
            $table->string('trip_code', 100);
            $table->string('bus_no', 100);
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('approved_by', 120);
            $table->string('approval_method', 30);
            $table->dateTime('approved_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_schedule_reserve_approvals');
    }
};
