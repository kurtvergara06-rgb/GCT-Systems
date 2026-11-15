<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\TopbarController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'show'])->name('login');

Route::get('/registration/pending', fn () => response()->view('Login.pending-approval')->header('Cache-Control', 'no-store'))
    ->name('registration.pending');
Route::get('/register', [RegistrationController::class, 'show'])->name('register');
Route::post('/register', [RegistrationController::class, 'store'])
    ->middleware('throttle:5,1')->name('register.submit');
Route::get('/email/verify/{id}/{hash}', [RegistrationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:10,1')->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:20,1')->name('auth.google.callback');

Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
    ->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:5,1')->name('password.update');

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
