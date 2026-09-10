@php
    $tabs = [
        'all' => ['All', 'fa-table-cells-large'],
        'fleet-trip' => ['Fleet & Trip', 'fa-route'],
        'fuel' => ['Fuel', 'fa-gas-pump'],
        'bus-health' => ['Bus Health', 'fa-heart-pulse'],
        'inventory' => ['Inventory', 'fa-boxes-stacked'],
    ];

    $domainViews = [
        'all' => 'Admin.Analytics.predictive.all',
        'fleet-trip' => 'Admin.Analytics.predictive.fleet-trip',
        'fuel' => 'Admin.Analytics.predictive.fuel',
        'bus-health' => 'Admin.Analytics.predictive.bus-health',
        'inventory' => 'Admin.Analytics.predictive.inventory',
    ];

    $domainStyles = [
        'all' => 'resources/css/Admin/Analytics/predictive/all.css',
        'fleet-trip' => 'resources/css/Admin/Analytics/predictive/fleet-trip.css',
        'fuel' => 'resources/css/Admin/Analytics/predictive/fuel.css',
        'bus-health' => 'resources/css/Admin/Analytics/predictive/bus-health.css',
        'inventory' => 'resources/css/Admin/Analytics/predictive/inventory.css',
    ];

    $activeDomain = array_key_exists($domain, $domainViews) ? $domain : 'all';
    $predictiveUrl = route('analytics.stage', ['stage' => 'predictive'], false);
    $normalizedSelectedBus = strtolower(trim((string) $selectedBus));
    $selectedRoute = strtolower(trim((string) ($fleet['selectedRoute'] ?? request('route', 'all'))));
    $routeOptions = collect($fleet['routeOptions'] ?? [])
        ->filter(fn ($label) => trim((string) $label) !== '')
        ->unique()
        ->values();

    $activeRoute = request('route');

    $pageAssets = [
        'resources/css/Admin/Analytics/overview/analytics-stage-hub.css',
        'resources/css/Admin/Analytics/predictive/all.css',
    ];

    if ($activeDomain !== 'all') {
        $pageAssets[] = $domainStyles[$activeDomain];
    }

    $pageAssets[] = 'resources/css/Admin/Analytics/design-system.css';

    $pageAssets[] = $activeDomain === 'fleet-trip'
        ? 'resources/js/Admin/Analytics/predictive/fleet.js'
        : 'resources/js/Admin/Analytics/predictive/charts.js';
@endphp

<x-layout.app title="FROMS - Predictive Analytics" :assets="$pageAssets">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page predictive-analytics-page predictive-domain-{{ $activeDomain }}">
            <x-layout.topbar title="Predictive Analytics" subtitle="AI-powered predictions and forecasts for proactive decision making." />

            <section class="analytics-domain-toolbar predictive-toolbar">
                <nav class="analytics-domain-tabs" aria-label="Predictive analytics domains">
                    @foreach($tabs as $key => $tab)
                        <a
                            href="{{ $predictiveUrl }}?{{ http_build_query(array_filter([
                                'domain' => $key,
                                'period' => $period,
                                'bus' => $normalizedSelectedBus !== 'all' ? $selectedBus : null,
                                'route' => $selectedRoute !== 'all' ? $activeRoute : null,
                            ])) }}"
                            class="{{ $activeDomain === $key ? 'active' : '' }}"
                        >
                            <i class="fa-solid {{ $tab[1] }}"></i>{{ $tab[0] }}
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ $predictiveUrl }}" class="analytics-stage-filters">
                    <input type="hidden" name="domain" value="{{ $activeDomain }}">

                    <label>
                        <span>Period</span>
                        <select name="period">
                            <option value="this-month" @selected($period === 'this-month')>This Month</option>
                            <option value="this-week" @selected($period === 'this-week')>This Week</option>
                            <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                            <option value="last-90-days" @selected($period === 'last-90-days')>Last 90 Days</option>
                            <option value="last-12-months" @selected($period === 'last-12-months')>Last 12 Months</option>
                        </select>
                    </label>

                    <label>
                        <span>Bus</span>
                        <select name="bus">
                            <option value="all" @selected($normalizedSelectedBus === 'all')>All Buses</option>
                            @foreach($busOptions as $busNo)
                                <option value="{{ $busNo }}" @selected(strtoupper((string) $selectedBus) === strtoupper((string) $busNo))>{{ $busNo }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if ($activeDomain === 'fleet-trip')
                        <label>
                            <span>Route</span>
                            <select name="route">
                                <option value="all" @selected($selectedRoute === 'all')>All Routes</option>
                                @foreach($routeOptions as $routeOption)
                                    <option value="{{ $routeOption }}" @selected($selectedRoute === strtolower((string) $routeOption))>{{ $routeOption }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                    <button type="button" id="refreshAnalytics" class="predictive-toolbar-refresh" aria-label="Refresh predictive analytics">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                </form>
            </section>

            @include($domainViews[$activeDomain])
        </main>
    </div>
</x-layout.app>