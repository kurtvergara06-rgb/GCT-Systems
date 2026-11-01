@php
    $pageTitle = match (true) {
        $stage === 'predictive' && $domain === 'fleet-trip' => 'Fleet & Trip Analytics',
        $stage === 'prescriptive' && $domain === 'fleet-trip' => 'Scheduling Insights',
        $domain === 'fuel' => 'Fuel Analytics',
        $domain === 'inventory' => 'Inventory Analytics',
        $domain === 'fleet-trip' => 'Incident Analytics',
        default => 'Analytics Overview',
    };
    $stageDescription = match ($pageTitle) {
        'Fleet & Trip Analytics' => 'Analyze trip performance, delays, estimated arrival times, GPS records, and fleet utilization.',
        'Scheduling Insights' => 'Review scheduling recommendations, dispatch readiness, and resource assignment insights.',
        'Fuel Analytics' => 'Monitor fuel consumption trends, efficiency, and unusual usage patterns.',
        'Inventory Analytics' => 'Monitor inventory availability, stock health, and replenishment requirements using recorded warehouse data.',
        'Incident Analytics' => 'Review incident frequency, causes, severity, and bus replacement activity.',
        default => 'Monitor cross-module performance, operational summaries, and important system-wide analytics.',
    };

    $tabs = [
        'all' => ['All', 'fa-table-cells-large'],
        'fleet-trip' => ['Fleet & Trip', 'fa-route'],
        'fuel' => ['Fuel', 'fa-gas-pump'],
        'bus-health' => ['Bus Health', 'fa-heart-pulse'],
        'inventory' => ['Inventory', 'fa-boxes-stacked'],
    ];

    $activeDomain = array_key_exists($domain, $tabs) ? $domain : 'all';
    $stageUrl = route('analytics.stage', ['stage' => $stage], false);
    $normalizedSelectedBus = strtolower(trim((string) $selectedBus));
    $pageAssets = [
        'resources/css/Admin/Analytics/overview/analytics-stage-hub.css',
        'resources/css/Admin/Analytics/design-system.css',
    ];
@endphp

<x-layout.app title="FROMS - {{ $pageTitle }}" :assets="$pageAssets" :partial-navigation="false">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page {{ $stage }}-analytics-page {{ $stage }}-domain-{{ $activeDomain }}">
            <x-layout.topbar :title="$pageTitle" :subtitle="$stageDescription" />

            <section class="analytics-domain-toolbar">
                <form method="GET" action="{{ $stageUrl }}" class="analytics-stage-filters">
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

            <section class="analytics-domain-section analytics-domain-pending">
                <div class="analytics-card analytics-pending-card">
                    <i class="fa-solid {{ $activeDomain === 'all' ? 'fa-chart-line' : $tabs[$activeDomain][1] }}"></i>
                    <div>
                        <strong>{{ ucfirst($stageLabel) }} {{ $activeDomain === 'all' ? '' : ($tabs[$activeDomain][0] . ' ') }}analytics is not yet connected.</strong>
                        <p>No values are being invented. This stage will appear here once its supporting evidence is implemented.</p>
                    </div>
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
