<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\BatchFileProcessingController;
use Illuminate\Support\Facades\Route;

Route::view(
    '/admin/dashboard',
    'Admin.admin-dashboard'
)->name('admin.dashboard');

Route::get('/admin/users', [AdminUserController::class, 'index'])
    ->name('admin.users');
Route::post('/admin/users', [AdminUserController::class, 'store'])
    ->name('admin.users.store');
Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])
    ->name('admin.users.update');
Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])
    ->name('admin.users.destroy');
Route::post('/admin/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
    ->name('admin.users.reset-password');
Route::patch('/admin/users/{user}/status', [AdminUserController::class, 'updateStatus'])
    ->name('admin.users.update-status');

Route::view(
    '/admin/roles-permissions',
    'Admin.User_Management.permissions'
)->name('admin.roles-permissions');

Route::view(
    '/admin/activity-logs',
    'Admin.System_Monitoring.activity-logs'
)->name('admin.activity-logs');

Route::view(
    '/admin/notifications',
    'Admin.System_Monitoring.notifications'
)->name('admin.notifications');

Route::controller(BatchFileProcessingController::class)
    ->prefix('batch-file-processing')
    ->group(function () {
        Route::get('/', 'index')->name('batch-file-processing');
        Route::post('/upload', 'upload')->name('batch-file-processing.upload');
        Route::get('/export', 'export')->name('batch-file-processing.export');
        Route::delete('/{batchUpload}', 'destroy')->name('batch-file-processing.destroy');
        Route::patch('/{batchUpload}/confirm', 'confirm')->name('batch-file-processing.confirm');
        Route::put('/records/{gpsTripRecord}', 'updateRecord')
            ->name('batch-file-processing.records.update');
        Route::put('/{batchUpload}/records/bulk-update', 'bulkUpdateRecords')
            ->name('batch-file-processing.records.bulk-update');
    });

Route::get(
    '/admin/batch-file-processing',
    [BatchFileProcessingController::class, 'index']
)->name('admin.batch-file-processing');

Route::view(
    '/admin/import-export',
    'Admin.Data_Management.uploading-data'
)->name('admin.import-export');

Route::view(
    '/admin/data-history',
    'Admin.Data_Management.data-history'
)->name('admin.data-history');

Route::redirect('/analytics', '/analytics/overview')->name('analytics');
Route::view('/analytics/overview', 'Admin.Analytics.overview')
    ->name('analytics.overview');

Route::view(
    '/admin/settings/general',
    'Admin.Settings.general-settings'
)->name('admin.settings.general');

Route::view(
    '/admin/settings/notifications',
    'Admin.Settings.notification-settings'
)->name('admin.settings.notifications');

Route::view(
    '/admin/settings/security',
    'Admin.Settings.security-settings'
)->name('admin.settings.security');
