<?php

namespace App\Providers;

use App\Http\Controllers\Admin\AdminImportExportController;
use App\Http\Controllers\Admin\StructuredImportReviewController;
use App\Http\Controllers\Admin\TransferActivityController;
use App\Models\Admin\BatchUpload;
use App\Models\Admin\DataActivity;
use App\Observers\BatchUploadObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
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

        Route::middleware(['web', 'auth'])->group(function () {
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
            )->name('admin.import-export.activity.approve');

            Route::get(
                '/admin/import-export/recent-activities',
                [TransferActivityController::class, 'recent']
            )->name('admin.import-export.activities.recent');

            Route::post(
                '/admin/import-export/export',
                [AdminImportExportController::class, 'export']
            )->name('admin.import-export.export');
        });

        View::composer('Admin.Data_Management.uploading-data', function ($view) {
            $monthStart = now()->startOfMonth();

            $recentTransferActivities = DataActivity::query()
                ->with('processor')
                ->where(function ($query) {
                    $query->where('activity_type', 'Import')
                        ->orWhere(function ($exportQuery) {
                            $exportQuery->where('activity_type', 'Export')
                                ->where('total_records', '>', 0);
                        });
                })
                ->latest()
                ->limit(6)
                ->get();

            $transferStats = [
                'imports' => DataActivity::where('activity_type', 'Import')
                    ->where('created_at', '>=', $monthStart)
                    ->count(),
                'exports' => DataActivity::where('activity_type', 'Export')
                    ->where('total_records', '>', 0)
                    ->where('created_at', '>=', $monthStart)
                    ->count(),
                'imported_records' => DataActivity::where('activity_type', 'Import')
                    ->where('status', 'Completed')
                    ->sum('successful_records'),
                'review' => DataActivity::whereIn('activity_type', ['Import', 'Export'])
                    ->whereIn('status', ['For Review', 'Needs Correction', 'Failed'])
                    ->count(),
            ];

            $view->with(compact('recentTransferActivities', 'transferStats'));
        });
    }
}
