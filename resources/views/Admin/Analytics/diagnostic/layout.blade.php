@php
    $tabs = [
        'all' => ['All', 'fa-table-cells-large'],
        'fleet-trip' => ['Fleet & Trip', 'fa-route'],
        'fuel' => ['Fuel', 'fa-gas-pump'],
        'bus-health' => ['Bus Health', 'fa-heart-pulse'],
        'inventory' => ['Inventory', 'fa-boxes-stacked'],
    ];

    $domainViews = [
        'all' => 'Admin.Analytics.diagnostic.all',
        'fleet-trip' => 'Admin.Analytics.diagnostic.fleet-trip',
        'fuel' => 'Admin.Analytics.diagnostic.fuel',
        'bus-health' => 'Admin.Analytics.diagnostic.bus-health',
        'inventory' => 'Admin.Analytics.diagnostic.inventory',
    ];

    $domainStyles = [
        'all' => 'resources/css/Admin/Analytics/diagnostic/all.css',
        'fleet-trip' => 'resources/css/Admin/Analytics/diagnostic/fleet-trip.css',
        'fuel' => 'resources/css/Admin/Analytics/diagnostic/fuel.css',
        'bus-health' => 'resources/css/Admin/Analytics/diagnostic/bus-health.css',
        'inventory' => 'resources/css/Admin/Analytics/diagnostic/inventory.css',
    ];

    $activeDomain = array_key_exists($domain, $domainViews) ? $domain : 'all';
    $diagnosticUrl = route('analytics.stage', ['stage' => 'diagnostic'], false);
    $normalizedSelectedBus = strtolower(trim((string) $selectedBus));
    $pageAssets = [
        'resources/css/Admin/Analytics/overview/analytics-stage-hub.css',
        $domainStyles[$activeDomain],
    ];
@endphp

<x-layout.app title="FROMS - Diagnostic Analytics" :assets="$pageAssets">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page diagnostic-analytics-page diagnostic-domain-{{ $activeDomain }}">
            <x-layout.topbar title="Diagnostic Analytics" subtitle="Why operational outcomes happened based on recorded data." />

            <section class="analytics-domain-toolbar diagnostic-toolbar">
                <nav class="analytics-domain-tabs" aria-label="Diagnostic analytics domains">
                    @foreach($tabs as $key => $tab)
                        <a
                            href="{{ $diagnosticUrl }}?{{ http_build_query(['domain' => $key, 'period' => $period, 'bus' => $normalizedSelectedBus !== 'all' ? $selectedBus : null]) }}"
                            class="{{ $activeDomain === $key ? 'active' : '' }}"
                        >
                            <i class="fa-solid {{ $tab[1] }}"></i>{{ $tab[0] }}
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ $diagnosticUrl }}" class="analytics-stage-filters">
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
                            <option value="all" @selected($normalizedSelectedBus === 'all')>All Buses</option>
                            @foreach($busOptions as $busNo)
                                <option value="{{ $busNo }}" @selected(strtoupper((string) $selectedBus) === strtoupper((string) $busNo))>{{ $busNo }}</option>
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
