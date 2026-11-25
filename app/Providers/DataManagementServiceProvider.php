<?php

namespace App\Providers;

use App\Http\Controllers\Admin\AdminImportExportController;
use App\Http\Controllers\Admin\StructuredImportReviewController;
use App\Http\Controllers\Admin\TransferActivityController;
use App\Models\Admin\BatchUpload;
use App\Observers\BatchUploadObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DataManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Data Management bindings can be added here as processors are introduced.
    }

    public function boot(): void
    {
        BatchUpload::observe(BatchUploadObserver::class);

        Route::middleware(['web', 'auth', 'role:admin:head,admin:staff'])->group(function () {
            Route::post(
                '/admin/import-export/import',
                [StructuredImportReviewController::class, 'stage']
            )->name('admin.import-export.import');

            Route::get(
                '/admin/import-export/activity/{activity}',
                [StructuredImportReviewController::class, 'details']
            )->name('admin.import-export.activity.details');

            Route::post(
                '/admin/import-export/activity/{activity}/approve',
                [StructuredImportReviewController::class, 'approve']
            )->middleware('system.permission:administration,full_control')->name('admin.import-export.activity.approve');

            Route::get(
                '/admin/import-export/recent-activities',
                [TransferActivityController::class, 'recent']
            )->name('admin.import-export.activities.recent');

            Route::post(
                '/admin/import-export/export',
                [AdminImportExportController::class, 'export']
            )->name('admin.import-export.export');
        });
    }
}
