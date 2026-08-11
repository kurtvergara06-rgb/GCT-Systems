<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\NotificationCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('account')
    ->controller(AccountController::class)
    ->group(function () {
        Route::get('/profile', 'profile')
            ->name('account.profile');

        Route::put('/profile', 'updateProfile')
            ->name('account.profile.update');

        Route::get('/settings', 'settings')
            ->name('account.settings');

        Route::put('/settings/password', 'updatePassword')
            ->name('account.password.update');
    });

Route::middleware(['web', 'auth'])->post(
    '/admin/notifications/{notification}/read',
    [NotificationCenterController::class, 'markRead']
)->name('admin.notifications.read');
