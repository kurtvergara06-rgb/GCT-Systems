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

    $pageAssets = [
        'resources/css/Admin/Analytics/overview/analytics-stage-hub.css',
        'resources/css/Admin/Analytics/overview/live-data.css',
        'resources/css/Admin/Analytics/model-status-stage.css',
        'resources/css/Admin/Analytics/predictive/all.css',
    ];

    if ($activeDomain !== 'all') {
        $pageAssets[] = $domainStyles[$activeDomain];
    }

    if ($activeDomain === 'fuel') {
        $pageAssets[] = 'resources/css/Admin/Analytics/predictive/fuel-model.css';
    }

    $pageAssets[] = 'resources/css/Admin/Analytics/design-system.css';
    $pageAssets[] = $activeDomain === 'fleet-trip'
        ? 'resources/js/Admin/Analytics/predictive/fleet.js'
        : 'resources/js/Admin/Analytics/predictive/charts.js';

    if ($activeDomain === 'fuel') {
        $pageAssets[] = 'resources/js/Admin/Analytics/predictive/fuel-model.js';
    }
@endphp

<x-layout.app title="FROMS - Fleet & Trip Analytics" :assets="$pageAssets" :partial-navigation="false">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page predictive-analytics-page predictive-domain-{{ $activeDomain }}">
            <x-layout.topbar title="Fleet & Trip Analytics" subtitle="Analyze trip performance, delays, estimated arrival times, GPS records, and fleet utilization." />

            <x-analytics.insight-toast stage="predictive" :domain="$activeDomain" :insight="$analyticsAlert" />

            @if($activeDomain !== 'fleet-trip')
            <section class="analytics-domain-toolbar predictive-toolbar">
                <form method="GET" action="{{ $predictiveUrl }}" class="analytics-stage-filters">
                    <input type="hidden" name="domain" value="{{ $activeDomain }}">

                    <label>
                        <span>Period</span>
                        <select name="period" onchange="this.form.requestSubmit()">
                            <option value="this-month" @selected($period === 'this-month')>This Month</option>
                            <option value="this-week" @selected($period === 'this-week')>This Week</option>
                            <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                            <option value="last-90-days" @selected($period === 'last-90-days')>Last 90 Days</option>
                            <option value="last-12-months" @selected($period === 'last-12-months')>Last 12 Months</option>
                        </select>
                    </label>

                    <label>
                        <span>Bus</span>
                        <select name="bus" onchange="this.form.requestSubmit()">
                            <option value="all" @selected($normalizedSelectedBus === 'all')>All Buses</option>
                            @foreach($busOptions as $busNo)
                                <option value="{{ $busNo }}" @selected(strtoupper((string) $selectedBus) === strtoupper((string) $busNo))>{{ $busNo }}</option>
                            @endforeach
                        </select>
                    </label>

                </form>
            </section>
            @endif

            @if($activeDomain === 'all')
                <x-analytics.model-status />
            @endif

            @include($domainViews[$activeDomain])

            @if($activeDomain === 'inventory')
                @include('Admin.Analytics.predictive.inventory-ml')
            @endif
        </main>
    </div>
</x-layout.app>
