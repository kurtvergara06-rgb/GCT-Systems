<?php
use App\Http\Controllers\Admin\AnalyticsStageController;
use Illuminate\Support\Facades\Route;
Route::middleware('auth')->prefix('analytics')->group(function (): void {
Route::redirect('/fleet-trip','/analytics/descriptive?domain=fleet-trip');
Route::redirect('/fuel','/analytics/descriptive?domain=fuel')->name('analytics.fuel');
Route::redirect('/bus-health','/analytics/descriptive?domain=bus-health')->name('analytics.bus-health');
Route::redirect('/inventory','/analytics/descriptive?domain=inventory')->name('analytics.inventory');
Route::redirect('/recommendations','/analytics/prescriptive')->name('analytics.recommendations');
Route::get('/{stage}',[AnalyticsStageController::class,'show'])->whereIn('stage',['descriptive','diagnostic','predictive','prescriptive'])->name('analytics.stage');
});