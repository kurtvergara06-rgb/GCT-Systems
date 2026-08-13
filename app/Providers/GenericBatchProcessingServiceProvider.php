<?php

namespace App\Providers;

use App\Http\Controllers\Admin\GenericBatchFileProcessingController;
use App\Http\Controllers\Admin\GenericBatchModalRedirectController;
use App\Http\Controllers\Admin\GenericBatchProfileController;
use App\Http\Controllers\Admin\GenericBatchRecordController;
use App\Models\Admin\BatchProcessedRecord;
use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class GenericBatchProcessingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])
            ->group(function () {
                Route::post(
                    '/batch-file-processing/generic-upload',
                    [GenericBatchFileProcessingController::class, 'upload']
                )->name('batch-file-processing.generic.upload');

                Route::get(
                    '/batch-file-processing/generic-profiles',
                    GenericBatchProfileController::class
                )->name('batch-file-processing.generic.profiles');

                Route::get(
                    '/batch-file-processing/generic/{batchUpload}',
                    GenericBatchModalRedirectController::class
                )->name('batch-file-processing.generic.review');

                Route::put(
                    '/batch-file-processing/generic/{batchUpload}/records',
                    [GenericBatchRecordController::class, 'update']
                )->name('batch-file-processing.generic.records.update');

                Route::patch(
                    '/batch-file-processing/generic/{batchUpload}/save-process',
                    [GenericBatchRecordController::class, 'saveAndProcess']
                )->name('batch-file-processing.generic.save-process');

                Route::patch(
                    '/batch-file-processing/generic/{batchUpload}/confirm',
                    [GenericBatchFileProcessingController::class, 'confirm']
                )->name('batch-file-processing.generic.confirm');
            });

        View::composer(
            'Admin.Data_Management.batch-file-processing',
            function ($view): void {
                $view->with(
                    'recordsExtracted',
                    GpsTripRecord::count() + BatchProcessedRecord::count()
                );

                $genericBatchId = request()->integer('generic_batch_id');
                $genericReviewBatch = null;
                $genericReviewRecords = collect();
                $genericReviewHeaders = collect();

                if ($genericBatchId) {
                    $genericReviewBatch = BatchUpload::query()
                        ->whereKey($genericBatchId)
                        ->where('data_type', '!=', 'GPS Trip Records')
                        ->with([
                            'processedRecords' => fn ($query) => $query->orderBy('id'),
                            'dataActivity.processor',
                        ])
                        ->first();

                    if ($genericReviewBatch) {
                        $genericReviewRecords = $genericReviewBatch->processedRecords;
                        $genericReviewHeaders = $genericReviewRecords
                            ->flatMap(fn (BatchProcessedRecord $record) => array_keys($record->payload ?? []))
                            ->unique()
                            ->values();
                    }
                }

                $view->with([
                    'genericReviewBatch' => $genericReviewBatch,
                    'genericReviewRecords' => $genericReviewRecords,
                    'genericReviewHeaders' => $genericReviewHeaders,
                    'genericStructuredBatch' => null,
                    'genericStructuredRecords' => collect(),
                    'genericStructuredHeaders' => collect(),
                ]);

                $data = $view->getData();
                $selectedBatch = $data['selectedBatch'] ?? null;

                if (! $selectedBatch || $selectedBatch->data_type === 'GPS Trip Records') {
                    return;
                }

                if ($selectedBatch->status === 'Processed') {
                    $selectedBatch->load([
                        'processedRecords' => fn ($query) => $query->orderBy('id'),
                    ]);

                    $structuredRecords = $selectedBatch->processedRecords;
                    $search = strtolower(trim((string) request('search', '')));

                    if ($search !== '') {
                        $structuredRecords = $structuredRecords
                            ->filter(function (BatchProcessedRecord $record) use ($search) {
                                $haystack = strtolower(json_encode(
                                    $record->payload ?? [],
                                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                ));

                                return str_contains($haystack, $search);
                            })
                            ->values();
                    }

                    $structuredHeaders = $selectedBatch->processedRecords
                        ->flatMap(fn (BatchProcessedRecord $record) => array_keys($record->payload ?? []))
                        ->unique()
                        ->values();

                    $view->with([
                        'genericStructuredBatch' => $selectedBatch,
                        'genericStructuredRecords' => $structuredRecords,
                        'genericStructuredHeaders' => $structuredHeaders,
                        'selectedRecord' => null,
                        'allSelectedRecords' => collect(),
                    ]);

                    return;
                }

                $gpsBatch = BatchUpload::query()
                    ->where('data_type', 'GPS Trip Records')
                    ->latest()
                    ->first();

                if (! $gpsBatch) {
                    $view->with([
                        'selectedBatchId' => null,
                        'selectedBatch' => null,
                        'selectedRecord' => null,
                        'allSelectedRecords' => collect(),
                        'records' => GpsTripRecord::query()
                            ->whereRaw('1 = 0')
                            ->paginate(25),
                    ]);

                    return;
                }

                $gpsBatch->load([
                    'tripRecords' => fn ($query) => $query->orderBy('beginning_at'),
                ]);

                $recordsQuery = GpsTripRecord::query()
                    ->with('batchUpload')
                    ->where('batch_upload_id', $gpsBatch->id)
                    ->whereHas('batchUpload', function ($query) {
                        $query->where('status', 'Processed');
                    });

                if (request()->filled('search')) {
                    $search = trim((string) request('search'));

                    $recordsQuery->where(function ($query) use ($search) {
                        $query->where('record_no', 'like', "%{$search}%")
                            ->orWhere('bus_no', 'like', "%{$search}%")
                            ->orWhere('grouping', 'like', "%{$search}%")
                            ->orWhere('trip_type', 'like', "%{$search}%")
                            ->orWhere('initial_location', 'like', "%{$search}%")
                            ->orWhere('final_location', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhere('coordinates', 'like', "%{$search}%");
                    });
                }

                $view->with([
                    'selectedBatchId' => $gpsBatch->id,
                    'selectedBatch' => $gpsBatch,
                    'selectedRecord' => $gpsBatch->tripRecords
                        ->sortByDesc('beginning_at')
                        ->first(),
                    'allSelectedRecords' => $gpsBatch->tripRecords,
                    'records' => $recordsQuery
                        ->latest('beginning_at')
                        ->paginate(25)
                        ->withQueryString(),
                ]);
            }
        );
    }
}
