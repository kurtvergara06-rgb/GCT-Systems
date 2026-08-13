<?php

namespace App\Providers;

use App\Http\Controllers\Admin\AdminImportExportController;
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
                [AdminImportExportController::class, 'import']
            )->name('admin.import-export.import');

            Route::post(
                '/admin/import-export/export',
                [AdminImportExportController::class, 'export']
            )->name('admin.import-export.export');
        });

        View::composer('Admin.Data_Management.data-history', function ($view) {
            $request = request();

            $query = DataActivity::query()
                ->with('processor')
                ->latest();

            if ($request->filled('search')) {
                $search = trim((string) $request->query('search'));

                $query->where(function ($builder) use ($search) {
                    $builder->where('file_name', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('data_type', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%");
                });
            }

            if ($request->filled('type') && $request->query('type') !== 'All Types') {
                $query->where('activity_type', $request->query('type'));
            }

            if ($request->filled('module') && $request->query('module') !== 'All Modules') {
                $query->where('module', $request->query('module'));
            }

            if ($request->filled('status') && $request->query('status') !== 'All Status') {
                $query->where('status', $request->query('status'));
            }

            $history = $query
                ->paginate(10)
                ->withQueryString();

            $stats = [
                'total' => DataActivity::count(),
                'successful' => DataActivity::where('status', 'Completed')->count(),
                'processed_files' => DataActivity::whereIn('activity_type', [
                    'Batch Processing',
                    'Import',
                ])->where('status', 'Completed')->count(),
                'failed' => DataActivity::where('status', 'Failed')->count(),
            ];

            $view->with(compact('history', 'stats'));
        });

        View::composer('Admin.Data_Management.uploading-data', function ($view) {
            $monthStart = now()->startOfMonth();

            $recentTransferActivities = DataActivity::query()
                ->with('processor')
                ->whereIn('activity_type', ['Import', 'Export'])
                ->latest()
                ->limit(6)
                ->get();

            $transferStats = [
                'imports' => DataActivity::where('activity_type', 'Import')
                    ->where('created_at', '>=', $monthStart)
                    ->count(),
                'exports' => DataActivity::where('activity_type', 'Export')
                    ->where('created_at', '>=', $monthStart)
                    ->count(),
                'imported_records' => DataActivity::where('activity_type', 'Import')
                    ->where('status', 'Completed')
                    ->sum('successful_records'),
                'review' => DataActivity::whereIn('activity_type', ['Import', 'Export'])
                    ->whereIn('status', ['For Review', 'Failed'])
                    ->count(),
            ];

            $view->with(compact('recentTransferActivities', 'transferStats'));
        });
    }
}
