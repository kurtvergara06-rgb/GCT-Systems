<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FleetTripPredictionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsStageController extends Controller
{
    private const STAGES = [
        'descriptive' => '5.1 Descriptive',
        'diagnostic' => '5.2 Diagnostic',
        'predictive' => '5.3 Predictive',
        'prescriptive' => '5.4 Prescriptive',
    ];

    private const DOMAINS = [
        'all',
        'fleet-trip',
        'fuel',
        'bus-health',
        'inventory',
    ];

    public function show(
        Request $request,
        string $stage,
        FleetTripPredictionService $predictionService
    ): View {
        abort_unless(array_key_exists($stage, self::STAGES), 404);

        $domain = strtolower(trim((string) $request->input('domain', 'all')));

        if (! in_array($domain, self::DOMAINS, true)) {
            $domain = 'all';
        }

        $fleetView = app(FleetTripAnalyticsController::class)
            ->index($request, $predictionService);

        $fleet = $fleetView->getData();
        $fuel = app(FuelAnalyticsController::class)->data($request);

        $busOptions = collect($fleet['busOptions'] ?? [])
            ->pluck('bus_no')
            ->merge(collect($fuel['buses'] ?? [])->pluck('bus_no'))
            ->filter()
            ->map(fn ($busNo) => strtoupper(trim((string) $busNo)))
            ->unique()
            ->sort()
            ->values();

        return view('Admin.Analytics.stage', [
            'stage' => $stage,
            'stageLabel' => self::STAGES[$stage],
            'domain' => $domain,
            'fleet' => $fleet,
            'fuel' => $fuel,
            'busOptions' => $busOptions,
            'period' => $fuel['period'] ?? ($fleet['period'] ?? 'this-month'),
            'selectedBus' => strtolower((string) ($fuel['selectedBus'] ?? 'all')),
        ]);
    }
}
