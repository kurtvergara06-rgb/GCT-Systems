<?php

namespace App\Observers;

use App\Models\Admin\BatchUpload;
use App\Models\Admin\DataActivity;

class BatchUploadObserver
{
    public function created(BatchUpload $batchUpload): void
    {
        $this->syncActivity($batchUpload);
    }

    public function updated(BatchUpload $batchUpload): void
    {
        $this->syncActivity($batchUpload);
    }

    public function deleted(BatchUpload $batchUpload): void
    {
        DataActivity::query()
            ->where('reference_type', 'batch_upload')
            ->where('reference_id', $batchUpload->id)
            ->update([
                'status' => 'Deleted',
                'updated_at' => now(),
            ]);
    }

    private function syncActivity(BatchUpload $batchUpload): void
    {
        $status = match ($batchUpload->status) {
            'Processed' => 'Completed',
            'In Review' => 'For Review',
            'Failed' => 'Failed',
            default => $batchUpload->status ?: 'Processing',
        };

        $totalRecords = max((int) $batchUpload->total_records, 0);
        $successfulRecords = max((int) $batchUpload->processed_records, 0);
        $failedRecords = max((int) $batchUpload->failed_records, 0);
        $skippedRecords = max(
            $totalRecords - $successfulRecords - $failedRecords,
            0
        );

        $activity = DataActivity::query()
            ->where('reference_type', 'batch_upload')
            ->where('reference_id', $batchUpload->id)
            ->first();

        $completedAt = $activity?->completed_at;

        if ($batchUpload->status === 'Processed' && $completedAt === null) {
            $completedAt = now();
        }

        if ($batchUpload->status !== 'Processed' && $status !== 'Deleted') {
            $completedAt = null;
        }

        DataActivity::updateOrCreate(
            [
                'reference_type' => 'batch_upload',
                'reference_id' => $batchUpload->id,
            ],
            [
                'activity_type' => 'Batch Processing',
                'module' => $batchUpload->module ?: 'Operation',
                'data_type' => $batchUpload->data_type ?: 'GPS Trip Records',
                'file_name' => $batchUpload->file_name,
                'source' => 'Raw / Semi-Structured File',
                'status' => $status,
                'total_records' => $totalRecords,
                'successful_records' => $successfulRecords,
                'failed_records' => $failedRecords,
                'skipped_records' => $skippedRecords,
                'processed_by' => $batchUpload->uploaded_by,
                'details' => [
                    'file_type' => $batchUpload->file_type,
                    'stored_name' => $batchUpload->stored_name,
                ],
                'error_message' => $batchUpload->error_message,
                'completed_at' => $completedAt,
            ]
        );
    }
}
