<?php

namespace App\Providers;

use App\Http\Controllers\Admin\GenericBatchFileProcessingController;
use App\Http\Controllers\Admin\GenericBatchModalRedirectController;
use App\Http\Controllers\Admin\GenericBatchProfileController;
use App\Http\Controllers\Admin\GenericBatchRecordController;
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
        Route::middleware(['web', 'auth', 'role:admin:head,admin:staff'])
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
                )->middleware('system.permission:administration,full_control')->name('batch-file-processing.generic.records.update');

                Route::patch(
                    '/batch-file-processing/generic/{batchUpload}/save-process',
                    [GenericBatchRecordController::class, 'saveAndProcess']
                )->middleware('system.permission:administration,full_control')->name('batch-file-processing.generic.save-process');

                Route::patch(
                    '/batch-file-processing/generic/{batchUpload}/confirm',
                    [GenericBatchFileProcessingController::class, 'confirm']
                )->middleware('system.permission:administration,full_control')->name('batch-file-processing.generic.confirm');
            });

        View::composer('Admin.Data_Management.batch-file-processing', function ($view): void {
            $view->with('recordsExtracted', GpsTripRecord::count());
        });
    }
}
