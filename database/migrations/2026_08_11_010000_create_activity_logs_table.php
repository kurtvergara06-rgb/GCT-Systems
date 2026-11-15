<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name');
            $table->string('user_role')->nullable();
            $table->string('department')->nullable()->index();
            $table->string('activity');
            $table->string('module')->index();
            $table->string('reference')->nullable()->index();
            $table->string('event_type')->index();
            $table->text('details')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'module']);
            $table->index(['created_at', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
