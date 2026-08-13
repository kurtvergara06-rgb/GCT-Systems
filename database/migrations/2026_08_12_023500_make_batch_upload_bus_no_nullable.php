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
            $table->string('bus_no', 50)->nullable()->change();
        });

        if (Schema::hasTable('data_activities')) {
            DB::table('batch_uploads')
                ->orderBy('id')
                ->chunkById(200, function ($batches) {
                    foreach ($batches as $batch) {
                        $total = max((int) ($batch->total_records ?? 0), 0);
                        $processed = max((int) ($batch->processed_records ?? 0), 0);
                        $failed = max((int) ($batch->failed_records ?? 0), 0);
                        $skipped = max($total - $processed - $failed, 0);

                        DB::table('data_activities')
                            ->where('reference_type', 'batch_upload')
                            ->where('reference_id', $batch->id)
                            ->update([
                                'skipped_records' => $skipped,
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        DB::table('batch_uploads')
            ->whereNull('bus_no')
            ->update(['bus_no' => 'Multiple Buses']);

        Schema::table('batch_uploads', function (Blueprint $table) {
            $table->string('bus_no', 50)->nullable(false)->change();
        });
    }
};
