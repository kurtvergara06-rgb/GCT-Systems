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
        'resources/css/Admin/Analytics/overview/live-data.css',
        'resources/css/Admin/Analytics/model-status-stage.css',
        'resources/css/Admin/Analytics/prescriptive/all.css',
        'resources/css/Admin/Analytics/prescriptive/readiness.css',
    ];

    if ($activeDomain !== 'all') {
        $pageAssets[] = $domainStyles[$activeDomain];
    }

    $pageAssets[] = 'resources/css/Admin/Analytics/design-system.css';
    $pageAssets[] = 'resources/js/Admin/Analytics/prescriptive/charts.js';
@endphp

<x-layout.app title="FROMS - Scheduling Insights" :assets="$pageAssets" :partial-navigation="false">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page prescriptive-analytics-page prescriptive-domain-{{ $activeDomain }}">
            <x-layout.topbar title="Scheduling Insights" subtitle="Review scheduling recommendations, dispatch readiness, and resource assignment insights." />

            <x-analytics.insight-toast stage="prescriptive" :domain="$activeDomain" :insight="$analyticsAlert" />

            @if($activeDomain !== 'fleet-trip')
            <section class="analytics-domain-toolbar prescriptive-toolbar">
                <form method="GET" action="{{ $prescriptiveUrl }}" class="analytics-stage-filters">
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

            @php
                $activePrescriptive = $prescriptive->{$activeDomain} ?? null;
                $prescriptiveNote = $activePrescriptive->note ?? null;
            @endphp

            @if ($prescriptiveNote && $activeDomain !== 'fleet-trip')
                <div class="prescriptive-sample-note" role="note">
                    <i class="fa-solid fa-flask"></i>
                    <span>
                        <strong>Demonstration mode:</strong>
                        {{ $prescriptiveNote }}
                        Live model readiness is shown separately below so illustrative recommendations are never presented as production-model output.
                    </span>
                </div>
            @endif

            @if($activeDomain === 'all')
                <x-analytics.model-status />
            @endif

            @if($activeDomain === 'fleet-trip')
                <section class="analytics-domain-section analytics-domain-pending" aria-label="Scheduling insights availability">
                    <div class="analytics-card analytics-pending-card">
                        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                        <div>
                            <strong>Live scheduling recommendations are not yet available.</strong>
                            <p>The current scheduling scenario is illustrative. Operational recommendations will appear here when supported by verified dispatch records.</p>
                        </div>
                    </div>
                </section>
            @else
                @include($domainViews[$activeDomain])
            @endif
        </main>
    </div>
</x-layout.app>
