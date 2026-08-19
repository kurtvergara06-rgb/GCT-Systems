<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse\InventoryItem;
use App\Services\FleetTripPredictionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsStageController extends Controller
{
    private const STAGES = [
        'descriptive' => 'Descriptive',
        'diagnostic' => 'Diagnostic',
        'predictive' => 'Predictive',
        'prescriptive' => 'Prescriptive',
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

        $inventoryTotal = InventoryItem::query()->count();
        $inventoryLow = InventoryItem::query()
            ->whereColumn('on_hand', '<=', 'reorder_level')
            ->where('on_hand', '>', 0)
            ->count();
        $inventoryCritical = InventoryItem::query()
            ->where('on_hand', '<=', 0)
            ->count();

        $inventory = (object) [
            'total' => $inventoryTotal,
            'healthy' => max(0, $inventoryTotal - $inventoryLow - $inventoryCritical),
            'low' => $inventoryLow,
            'critical' => $inventoryCritical,
        ];

        $busOptions = collect($fleet['busOptions'] ?? [])
            ->pluck('bus_no')
            ->merge(collect($fuel['buses'] ?? [])->pluck('bus_no'))
            ->filter()
            ->map(fn ($busNo) => strtoupper(trim((string) $busNo)))
            ->unique()
            ->sort()
            ->values();

        $viewName = $stage === 'diagnostic'
            ? 'Admin.Analytics.diagnostic.layout'
            : 'Admin.Analytics.stage';

        return view($viewName, [
            'stage' => $stage,
            'stageLabel' => self::STAGES[$stage],
            'domain' => $domain,
            'fleet' => $fleet,
            'fuel' => $fuel,
            'inventory' => $inventory,
            'busOptions' => $busOptions,
            'period' => $fuel['period'] ?? ($fleet['period'] ?? 'this-month'),
            'selectedBus' => strtolower((string) ($fuel['selectedBus'] ?? 'all')),
        ]);
    }
}
