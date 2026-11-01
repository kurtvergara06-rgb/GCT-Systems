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
        'resources/css/Admin/Analytics/design-system.css',
    ];
@endphp

<x-layout.app title="FROMS - {{ $activeDomain === 'fuel' ? 'Fuel Analytics' : 'Incident Analytics' }}" :assets="$pageAssets" :partial-navigation="false">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page diagnostic-analytics-page diagnostic-domain-{{ $activeDomain }}">
            <x-layout.topbar :title="$activeDomain === 'fuel' ? 'Fuel Analytics' : 'Incident Analytics'" :subtitle="$activeDomain === 'fuel' ? 'Monitor fuel consumption trends, efficiency, and unusual usage patterns.' : 'Review incident frequency, causes, severity, and bus replacement activity.'" />

            <x-analytics.insight-toast stage="diagnostic" :domain="$activeDomain" :insight="$analyticsAlert" />

            @if($activeDomain !== 'fleet-trip')
            <section class="analytics-domain-toolbar diagnostic-toolbar">
                <form method="GET" action="{{ $diagnosticUrl }}" class="analytics-stage-filters">
                    <input type="hidden" name="domain" value="{{ $activeDomain }}">

                    <label>
                        <span>Period</span>
                        <select name="period" onchange="this.form.requestSubmit()">
                            <option value="this-month" @selected($period === 'this-month')>This Month</option>
                            <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                            <option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option>
                            <option value="this-year" @selected($period === 'this-year')>This Year</option>
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

            @if($activeDomain === 'fleet-trip')
                <section class="analytics-domain-section analytics-domain-pending" aria-label="Incident analytics availability">
                    <div class="analytics-card analytics-pending-card">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <div>
                            <strong>Incident analytics is awaiting a verified incident data source.</strong>
                            <p>The existing diagnostic dataset describes trip delays and idling, not incident frequency or causes. Incident figures will appear here when a dedicated, authorized data source is connected.</p>
                        </div>
                    </div>
                </section>
            @else
                @include($domainViews[$activeDomain])
            @endif
        </main>
    </div>
</x-layout.app>
