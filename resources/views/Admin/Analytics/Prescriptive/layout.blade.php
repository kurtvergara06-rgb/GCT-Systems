@php
    $tabs = [
        'all' => ['All', 'fa-table-cells-large'],
        'fleet-trip' => ['Fleet & Trip', 'fa-route'],
        'fuel' => ['Fuel', 'fa-gas-pump'],
        'bus-health' => ['Bus Health', 'fa-heart-pulse'],
        'inventory' => ['Inventory', 'fa-boxes-stacked'],
    ];

    $domainViews = [
        'all' => 'Admin.Analytics.prescriptive.all',
        'fleet-trip' => 'Admin.Analytics.prescriptive.fleet-trip',
        'fuel' => 'Admin.Analytics.prescriptive.fuel',
        'bus-health' => 'Admin.Analytics.prescriptive.bus-health',
        'inventory' => 'Admin.Analytics.prescriptive.inventory',
    ];

    $domainStyles = [
        'all' => 'resources/css/Admin/Analytics/prescriptive/all.css',
        'fleet-trip' => 'resources/css/Admin/Analytics/prescriptive/fleet-trip.css',
        'fuel' => 'resources/css/Admin/Analytics/prescriptive/fuel.css',
        'bus-health' => 'resources/css/Admin/Analytics/prescriptive/bus-health.css',
        'inventory' => 'resources/css/Admin/Analytics/prescriptive/inventory.css',
    ];

    $activeDomain = array_key_exists($domain, $domainViews) ? $domain : 'all';
    $prescriptiveUrl = route('analytics.stage', ['stage' => 'prescriptive'], false);
    $normalizedSelectedBus = strtolower(trim((string) $selectedBus));

    $pageAssets = [
        'resources/css/Admin/Analytics/overview/analytics-stage-hub.css',
        'resources/css/Admin/Analytics/prescriptive/all.css',
    ];

    if ($activeDomain !== 'all') {
        $pageAssets[] = $domainStyles[$activeDomain];
    }

    $pageAssets[] = 'resources/css/Admin/Analytics/design-system.css';
    $pageAssets[] = 'resources/js/Admin/Analytics/prescriptive/charts.js';
@endphp

<x-layout.app title="FROMS - Prescriptive Analytics" :assets="$pageAssets">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page prescriptive-analytics-page prescriptive-domain-{{ $activeDomain }}">
            <x-layout.topbar title="Prescriptive Analytics" subtitle="AI-driven action playbooks and operational optimization to maximize transit performance." />

            <x-analytics.insight-toast stage="prescriptive" :domain="$activeDomain" />

            <section class="analytics-domain-toolbar prescriptive-toolbar">
                <nav class="analytics-domain-tabs" aria-label="Prescriptive analytics domains">
                    @foreach($tabs as $key => $tab)
                        <a
                            href="{{ $prescriptiveUrl }}?{{ http_build_query(array_filter([
                                'domain' => $key,
                                'period' => $period,
                                'bus' => $normalizedSelectedBus !== 'all' ? $selectedBus : null,
                            ])) }}"
                            class="{{ $activeDomain === $key ? 'active' : '' }}"
                        >
                            <i class="fa-solid {{ $tab[1] }}"></i>{{ $tab[0] }}
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ $prescriptiveUrl }}" class="analytics-stage-filters">
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

                    <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                </form>
            </section>

            @include($domainViews[$activeDomain])
        </main>
    </div>
</x-layout.app>
