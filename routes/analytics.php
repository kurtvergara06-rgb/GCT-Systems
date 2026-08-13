<?php

use App\Http\Controllers\Admin\AnalyticsStageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('analytics')
    ->group(function (): void {
        Route::get(
            '/{stage}',
            [AnalyticsStageController::class, 'show']
        )
            ->whereIn('stage', [
                'descriptive',
                'diagnostic',
                'predictive',
                'prescriptive',
            ])
            ->name('analytics.stage');
    });
