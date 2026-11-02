<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\BatchFileProcessingController;
use App\Models\Admin\BatchUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ProcessGpsPdfBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;
    public int $tries = 1;

    public function __construct(public int $batchId)
    {
        $this->onConnection('gps_pdf')->onQueue('gps-pdf');
    }

    public function handle(BatchFileProcessingController $controller): void
    {
        $batch = BatchUpload::find($this->batchId);
        if (! $batch || $batch->status !== 'Processing') {
            return;
        }
        try {
            $result = $controller->processPdfFile($batch);
            if (($result['processed'] ?? 0) < 1) {
                throw new \RuntimeException('PDF extraction completed without saving valid GPS records.');
            }
            $batch->update([
                'status' => 'In Review',
                'progress_percentage' => 100,
                'total_records' => $result['total'] ?? 0,
                'processed_records' => $result['processed'] ?? 0,
                'failed_records' => $result['failed'] ?? 0,
                'error_message' => $result['first_error'] ?? null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $batch->update([
                'status' => 'Failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
        }
    }
}
