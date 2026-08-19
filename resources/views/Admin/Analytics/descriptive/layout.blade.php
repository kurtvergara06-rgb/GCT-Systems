@php
    $tabs = [
        'all' => ['All', 'fa-table-cells-large'],
        'fleet-trip' => ['Fleet & Trip', 'fa-route'],
        'fuel' => ['Fuel', 'fa-gas-pump'],
        'bus-health' => ['Bus Health', 'fa-heart-pulse'],
        'inventory' => ['Inventory', 'fa-boxes-stacked'],
    ];

    $domainViews = [
        'all' => 'Admin.Analytics.descriptive.all',
        'fleet-trip' => 'Admin.Analytics.descriptive.fleet-trip',
        'fuel' => 'Admin.Analytics.descriptive.fuel',
        'bus-health' => 'Admin.Analytics.descriptive.bus-health',
        'inventory' => 'Admin.Analytics.descriptive.inventory',
    ];

    $domainStyles = [
        'all' => 'resources/css/Admin/Analytics/descriptive/all.css',
        'fleet-trip' => 'resources/css/Admin/Analytics/descriptive/fleet-trip.css',
        'fuel' => 'resources/css/Admin/Analytics/descriptive/fuel.css',
        'bus-health' => 'resources/css/Admin/Analytics/descriptive/bus-health.css',
        'inventory' => 'resources/css/Admin/Analytics/descriptive/inventory.css',
    ];

    $activeDomain = array_key_exists($domain, $domainViews) ? $domain : 'all';

    $trendCount = max(1, $trend->count());
    $tripChartData = $trend->values()->map(function ($bucket, $index) use ($trendCount) {
        return [
            'label' => $bucket->label,
            'value' => (int) $bucket->count,
            'partial' => $index === $trendCount - 1,
        ];
    });
    $hasPartialBucket = $tripChartData->contains(fn ($point) => $point['partial']);

    $healthyPct = $inventoryTotal > 0 ? ($inventoryHealthy / $inventoryTotal) * 100 : 0;
    $lowPct = $inventoryTotal > 0 ? ($inventoryLow / $inventoryTotal) * 100 : 0;
    $criticalPct = $inventoryTotal > 0 ? ($inventoryCritical / $inventoryTotal) * 100 : 0;
    $activeAngle = $totalBuses > 0 ? ($activeBuses / $totalBuses) * 360 : 0;
    $maintenanceAngle = $totalBuses > 0 ? (($activeBuses + $underMaintenance) / $totalBuses) * 360 : 0;
    $inventoryHealthyAngle = $inventoryTotal > 0 ? ($inventoryHealthy / $inventoryTotal) * 360 : 0;
    $inventoryLowAngle = $inventoryTotal > 0 ? (($inventoryHealthy + $inventoryLow) / $inventoryTotal) * 360 : 0;
    $inventoryStatusBars = collect([
        (object) ['label' => 'Well Stocked', 'value' => $inventoryHealthy],
        (object) ['label' => 'Low Stock', 'value' => $inventoryLow],
        (object) ['label' => 'Out of Stock', 'value' => $inventoryCritical],
    ]);

    $activePct = $totalBuses > 0 ? ($activeBuses / $totalBuses) * 100 : 0;
    $maintenancePct = $totalBuses > 0 ? ($underMaintenance / $totalBuses) * 100 : 0;
    $inactivePct = $totalBuses > 0 ? ($inactiveBuses / $totalBuses) * 100 : 0;
    $maintenanceEndPct = $activePct + $maintenancePct;

    $fuelSummaries = collect($fuel['busSummaries'] ?? [])->values();
    $fuelRecords = collect($fuel['records'] ?? [])->values();
    $fuelUsageRows = $fuelSummaries->sortByDesc('fuel_liters')->take(10)->values();
    $fuelBusChartData = $fuelUsageRows->map(fn ($row) => [
        'label' => $row->bus_no,
        'fuel' => (float) $row->fuel_liters,
        'distance' => (float) $row->distance_km,
        'efficiency' => (float) $row->km_per_liter,
        'entries' => (int) $row->entries,
    ]);
    $mostEfficientBus = $fuelSummaries->filter(fn ($row) => $row->km_per_liter > 0)->sortByDesc('km_per_liter')->first();
    $leastEfficientBus = $fuelSummaries->filter(fn ($row) => $row->km_per_liter > 0)->sortBy('km_per_liter')->first();
    $averageFuelPerBus = $fuelSummaries->count() > 0 ? ((float) ($fuel['totalFuel'] ?? 0) / $fuelSummaries->count()) : 0;
    $averageDistancePerBus = $fuelSummaries->count() > 0 ? ((float) ($fuel['totalDistance'] ?? 0) / $fuelSummaries->count()) : 0;

    $validFuelRecords = $fuelRecords->filter(fn ($row) => (float) ($row->fuel_liters ?? 0) > 0 && (float) ($row->distance_km ?? 0) > 0)->count();
    $incompleteFuelRecords = max(0, $fuelRecords->count() - $validFuelRecords);
    $fuelQualityPct = $fuelRecords->count() > 0 ? ($validFuelRecords / $fuelRecords->count()) * 100 : 0;

    $fuelReviewUnits = collect($fuel['reviewUnits'] ?? [])->values();
    $highIdlingUnits = collect($fuel['highIdlingUnits'] ?? [])->values();
    $priorityFuelUnits = $fuelSummaries->where('status', 'Priority Review')->count();
    $reviewFuelUnits = $fuelSummaries->where('status', 'Review')->count();
    $efficientFuelUnits = $fuelSummaries->where('status', 'Efficient')->count();
    $normalFuelUnits = $fuelSummaries->where('status', 'Normal')->count();
    $fuelStatusTotal = max(1, $fuelSummaries->count());
    $efficientFuelPct = ($efficientFuelUnits / $fuelStatusTotal) * 100;
    $normalFuelPct = ($normalFuelUnits / $fuelStatusTotal) * 100;
    $reviewFuelPct = ($reviewFuelUnits / $fuelStatusTotal) * 100;
    $priorityFuelPct = ($priorityFuelUnits / $fuelStatusTotal) * 100;

    $deltaText = function (?float $value): string {
        return $value === null ? 'New' : (($value > 0 ? '+' : '') . number_format($value, 1) . '%');
    };

    /*
     * Descriptive keeps one legacy shared redesign layer because All/Fuel still
     * use its reusable chart/card primitives. Fleet-specific base/ranking CSS
     * is loaded only where those classes are actually used.
     */
    $pageAssets = [];

    if ($activeDomain === 'fleet-trip') {
        $pageAssets[] = 'resources/css/Admin/Analytics/fleet-trip.css';
    }

    $pageAssets[] = 'resources/css/Admin/Analytics/fleet-trip-redesign.css';

    if (in_array($activeDomain, ['all', 'fleet-trip'], true)) {
        $pageAssets[] = 'resources/css/Admin/Analytics/fleet-trip-rankings.css';
    }

    $pageAssets[] = 'resources/css/Admin/Analytics/analytics-stage-hub.css';
    $pageAssets[] = $domainStyles[$activeDomain];
@endphp

<x-layout.app title="FROMS - Descriptive Analytics" :assets="$pageAssets">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page descriptive-analytics-page descriptive-domain-{{ $activeDomain }}{{ $activeDomain === 'fleet-trip' ? ' fleet-trip-page' : '' }}">
            <x-layout.topbar title="Descriptive Analytics" subtitle="What happened based on recorded operational data." />

            <section class="analytics-domain-toolbar descriptive-toolbar">
                <nav class="analytics-domain-tabs" aria-label="Descriptive analytics domains">
                    @foreach($tabs as $key => $tab)
                        <a href="{{ url('/analytics/descriptive') }}?{{ http_build_query(['domain' => $key, 'period' => $period, 'bus' => $selectedBus !== 'ALL' ? $selectedBus : null]) }}" class="{{ $activeDomain === $key ? 'active' : '' }}">
                            <i class="fa-solid {{ $tab[1] }}"></i>{{ $tab[0] }}
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ url('/analytics/descriptive') }}" class="analytics-stage-filters">
                    <input type="hidden" name="domain" value="{{ $activeDomain }}">
                    <label>
                        <span>Period</span>
                        <select name="period">
                            <option value="this-month" @selected($period === 'this-month')>This Month</option>
                            <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                            <option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option>
                            <option value="this-year" @selected($period === 'this-year')>This Year</option>
                        </select>
                    </label>
                    <label>
                        <span>Bus</span>
                        <select name="bus">
                            <option value="all">All Buses</option>
                            @foreach($buses as $bus)
                                <option value="{{ $bus->bus_no }}" @selected($selectedBus === strtoupper($bus->bus_no))>{{ $bus->bus_no }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                </form>
            </section>

            @include($domainViews[$activeDomain])
        </main>
    </div>
</x-layout.app>
