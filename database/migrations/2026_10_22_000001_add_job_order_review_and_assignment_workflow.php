<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table): void {
            $table->string('review_status', 30)->default('Approved')->after('status')->index();
            $table->foreignId('created_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('approval_signature_hash', 64)->nullable()->after('approved_at');
            $table->text('review_notes')->nullable()->after('approval_signature_hash');
            $table->unsignedInteger('revision_number')->default(0)->after('review_notes');
            $table->timestamp('actual_started_at')->nullable()->after('revision_number');
            $table->timestamp('actual_completed_at')->nullable()->after('actual_started_at');
            $table->string('parts_issue_strategy', 40)->default('complete_before_issuance')->after('actual_completed_at');
            $table->string('inventory_availability', 20)->nullable()->after('parts_issue_strategy');
            $table->json('inventory_snapshot')->nullable()->after('inventory_availability');
        });

        Schema::create('job_order_mechanics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_order_id')->constrained('job_orders')->cascadeOnDelete();
            $table->foreignId('mechanic_id')->nullable()->constrained('mechanics')->nullOnDelete();
            $table->string('mechanic_name');
            $table->text('specialization_snapshot')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['job_order_id', 'mechanic_name']);
            $table->index(['mechanic_id', 'job_order_id']);
        });

        Schema::create('job_order_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_order_id')->constrained('job_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['job_order_id', 'created_at']);
        });

        DB::table('job_orders')
            ->whereNotNull('assigned_mechanic')
            ->whereRaw("TRIM(assigned_mechanic) <> ''")
            ->orderBy('id')
            ->each(function (object $jobOrder): void {
                foreach (preg_split('/\s*,\s*/', trim((string) $jobOrder->assigned_mechanic)) ?: [] as $mechanicName) {
                    if ($mechanicName === '') {
                        continue;
                    }

                    $mechanic = DB::table('mechanics')
                        ->whereRaw('LOWER(TRIM(mechanic_name)) = ?', [mb_strtolower($mechanicName)])
                        ->first();

                    DB::table('job_order_mechanics')->insertOrIgnore([
                        'job_order_id' => $jobOrder->id,
                        'mechanic_id' => $mechanic?->id,
                        'mechanic_name' => $mechanic?->mechanic_name ?? $mechanicName,
                        'specialization_snapshot' => $mechanic?->specialization,
                        'created_at' => $jobOrder->created_at ?? now(),
                        'updated_at' => $jobOrder->updated_at ?? now(),
                    ]);
                }
            });

        DB::table('job_orders')
            ->where('status', 'Completed')
            ->whereNotNull('start_date')
            ->whereNotNull('completion_date')
            ->whereColumn('completion_date', '>=', 'start_date')
            ->update([
                'actual_started_at' => DB::raw('start_date'),
                'actual_completed_at' => DB::raw('completion_date'),
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_audits');
        Schema::dropIfExists('job_order_mechanics');

        Schema::table('job_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'review_status', 'approved_at', 'approval_signature_hash', 'review_notes',
                'revision_number', 'actual_started_at', 'actual_completed_at',
                'parts_issue_strategy', 'inventory_availability', 'inventory_snapshot',
            ]);
        });
    }
};
