<?php

use App\Http\Controllers\Admin\AnalyticsStageController;
use App\Http\Controllers\Admin\DelayPredictionController;
use App\Http\Controllers\Admin\DescriptiveAnalyticsController;
use App\Http\Controllers\Admin\FuelPredictionController;
use App\Http\Controllers\Admin\InventoryPredictionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('analytics')->group(function (): void {
    Route::get('/descriptive', [DescriptiveAnalyticsController::class, 'index'])
        ->name('analytics.descriptive');

    Route::redirect('/fleet-trip', '/analytics/descriptive?domain=fleet-trip')
        ->name('analytics.fleet-trip');

    Route::redirect('/fuel', '/analytics/descriptive?domain=fuel')
        ->name('analytics.fuel');

    Route::redirect('/bus-health', '/analytics/descriptive?domain=bus-health')
        ->name('analytics.bus-health');

    Route::redirect('/inventory', '/analytics/descriptive?domain=inventory')
        ->name('analytics.inventory');

    Route::redirect('/recommendations', '/analytics/prescriptive')
        ->name('analytics.recommendations');

    Route::get('/delay-predictions', [DelayPredictionController::class, 'index'])
        ->name('analytics.delay-predictions');

    Route::get('/fuel-predictions', [FuelPredictionController::class, 'index'])
        ->name('analytics.fuel-predictions');

    Route::post('/inventory-predictions', [InventoryPredictionController::class, 'index'])
        ->name('analytics.inventory-predictions');

    Route::get('/{stage}', [AnalyticsStageController::class, 'show'])
        ->whereIn('stage', ['diagnostic', 'predictive', 'prescriptive'])
        ->name('analytics.stage');
});
