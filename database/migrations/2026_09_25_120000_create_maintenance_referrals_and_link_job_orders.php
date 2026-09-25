<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->unique()->constrained('incidents')->cascadeOnDelete();
            $table->string('status', 40)->default('Pending');
            $table->text('notes')->nullable();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::table('job_orders', function (Blueprint $table): void {
            $table->foreignId('maintenance_referral_id')
                ->nullable()
                ->unique()
                ->after('pms_schedule_id')
                ->constrained('maintenance_referrals')
                ->nullOnDelete();

            $table->foreignId('incident_id')
                ->nullable()
                ->after('maintenance_referral_id')
                ->constrained('incidents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('incident_id');
            $table->dropConstrainedForeignId('maintenance_referral_id');
        });

        Schema::dropIfExists('maintenance_referrals');
    }
};
