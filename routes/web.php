<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\TopbarController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'Login.login')->name('login');

Route::post('/login', [LoginController::class, 'login'])
    ->name('login.submit');

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/topbar/summary', [TopbarController::class, 'summary'])
        ->name('topbar.summary');

    Route::post(
        '/topbar/notifications/read-all',
        [TopbarController::class, 'markAllNotificationsRead']
    )->name('topbar.notifications.read-all');

    require base_path('routes/maintenance.php');
    require base_path('routes/warehouse.php');
    require base_path('routes/purchase.php');
    require base_path('routes/operation.php');
    require base_path('routes/admin.php');
});
