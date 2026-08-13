<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_uploads', function (Blueprint $table) {
            $table->string('module', 50)
                ->default('Operation')
                ->after('file_type');

            $table->string('data_type', 100)
                ->default('GPS Trip Records')
                ->after('module');
        });

        Schema::create('data_activities', function (Blueprint $table) {
            $table->id();
            $table->string('activity_type', 40);
            $table->string('module', 50)->nullable();
            $table->string('data_type', 100)->nullable();
            $table->string('file_name')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('status', 30)->default('Processing');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('successful_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->unsignedInteger('skipped_records')->default(0);
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('details')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['activity_type', 'status']);
            $table->index(['module', 'data_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });

        DB::table('batch_uploads')
            ->orderBy('id')
            ->chunkById(200, function ($batches) {
                $rows = [];

                foreach ($batches as $batch) {
                    $status = match ($batch->status) {
                        'Processed' => 'Completed',
                        'In Review' => 'For Review',
                        'Failed' => 'Failed',
                        default => $batch->status ?: 'Processing',
                    };

                    $rows[] = [
                        'activity_type' => 'Batch Processing',
                        'module' => $batch->module ?? 'Operation',
                        'data_type' => $batch->data_type ?? 'GPS Trip Records',
                        'file_name' => $batch->file_name,
                        'source' => 'Raw / Semi-Structured File',
                        'status' => $status,
                        'total_records' => (int) ($batch->total_records ?? 0),
                        'successful_records' => (int) ($batch->processed_records ?? 0),
                        'failed_records' => (int) ($batch->failed_records ?? 0),
                        'skipped_records' => 0,
                        'processed_by' => $batch->uploaded_by,
                        'reference_type' => 'batch_upload',
                        'reference_id' => $batch->id,
                        'details' => json_encode([
                            'file_type' => $batch->file_type,
                            'legacy_batch' => true,
                        ]),
                        'error_message' => $batch->error_message,
                        'completed_at' => $batch->status === 'Processed'
                            ? $batch->updated_at
                            : null,
                        'created_at' => $batch->created_at,
                        'updated_at' => $batch->updated_at,
                    ];
                }

                if ($rows !== []) {
                    DB::table('data_activities')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_activities');

        Schema::table('batch_uploads', function (Blueprint $table) {
            $table->dropColumn(['module', 'data_type']);
        });
    }
};
