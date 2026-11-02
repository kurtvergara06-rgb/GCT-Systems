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

    $activeDomain = array_key_exists($domain, $domainViews) ? $domain : 'fleet-trip';
    $predictiveUrl = route('analytics.stage', ['stage' => 'predictive'], false);
    $normalizedSelectedBus = strtolower(trim((string) $selectedBus));
    $pageTitle = match ($activeDomain) {
        'inventory' => 'Predictive Analytics — Spare Parts',
        'fuel' => 'Predictive Analytics — Fuel',
        'fleet-trip' => 'Predictive Analytics — Fleet & Trip',
        'bus-health' => 'Predictive Analytics — Bus Health',
        default => 'Predictive Analytics',
    };
    $pageSubtitle = match ($activeDomain) {
        'inventory' => 'Review historical spare parts issuance and supported demand forecasts.',
        'fuel' => 'Review recorded fuel patterns and available forecast evidence.',
        'fleet-trip' => 'Analyze trip delay risk, estimated arrival times, and historical fleet activity.',
        default => 'Review prediction readiness, historical evidence, and forecast availability.',
    };
    $periodOptions = [
        'this-month' => 'This Month', 'this-week' => 'This Week',
        'last-30-days' => 'Last 30 Days', 'last-90-days' => 'Last 90 Days',
        'last-12-months' => 'Last 12 Months',
    ];
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

<x-layout.app title="FROMS - {{ $pageTitle }}" :assets="$pageAssets" :partial-navigation="false">
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page predictive-analytics-page predictive-domain-{{ $activeDomain }}">
            <x-layout.topbar :title="$pageTitle" :subtitle="$pageSubtitle" />

            <x-analytics.insight-toast stage="predictive" :domain="$activeDomain" :insight="$analyticsAlert" />

            <div class="predictive-ref-controls">
                <nav class="predictive-ref-tabs" aria-label="Predictive analytics areas">
                    @foreach(['fleet-trip' => ['Fleet & Trip', 'fa-bus'], 'fuel' => ['Fuel Analysis', 'fa-gas-pump'], 'inventory' => ['Spare Parts Forecast', 'fa-boxes-stacked']] as $tabKey => [$tabLabel, $tabIcon])
                        <a class="{{ $activeDomain === $tabKey ? 'is-current' : '' }}"
                           href="{{ $predictiveUrl.'?'.http_build_query(array_merge(request()->only(['period', 'bus']), ['domain' => $tabKey])) }}"
                           @if($activeDomain === $tabKey) aria-current="page" @endif>
                           <i class="fa-solid {{ $tabIcon }}" aria-hidden="true"></i> {{ $tabLabel }}
                        </a>
                    @endforeach
                </nav>
                <form method="GET" action="{{ $predictiveUrl }}" class="predictive-ref-filters">
                    <input type="hidden" name="domain" value="{{ $activeDomain }}">
                    <label>Period
                        <select name="period" onchange="this.form.requestSubmit()">
                            @foreach($periodOptions as $periodValue => $periodLabel)
                                <option value="{{ $periodValue }}" @selected($period === $periodValue)>{{ $periodLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Bus
                        <select name="bus" onchange="this.form.requestSubmit()">
                            <option value="all" @selected($normalizedSelectedBus === 'all')>All Buses</option>
                            @foreach($busOptions as $busNo)
                                <option value="{{ $busNo }}" @selected(strtoupper((string) $selectedBus) === strtoupper((string) $busNo))>{{ $busNo }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
            </div>
            <details class="predictive-model-disclosure">
                <summary><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Model readiness &amp; data provenance <span>Check whether trained models are available</span></summary>
                <x-analytics.model-status />
                <p>Fallback calculations and historical trends are not trained-model predictions. Missing model services must never be presented as deployed AI.</p>
            </details>
            @include($domainViews[$activeDomain])

            @if($activeDomain === 'inventory')
                @include('Admin.Analytics.predictive.inventory-ml')
            @endif
            <style>
            .predictive-ref-controls{display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:end;margin:16px 0}
            .predictive-ref-tabs{display:flex;flex-wrap:wrap;gap:8px}
            .predictive-ref-tabs a{display:inline-flex;gap:8px;align-items:center;padding:11px 15px;border:1px solid #dce5f4;border-radius:10px;background:white;color:#173b65;text-decoration:none;font-size:12px;font-weight:700}
            .predictive-ref-tabs a.is-current{background:#1265e0;border-color:#1265e0;color:white}
            .predictive-ref-filters{display:flex;gap:9px;flex-wrap:wrap}
            .predictive-ref-filters label{display:grid;gap:4px;font-size:11px;font-weight:700;color:#55718f}
            .predictive-ref-filters select{min-width:145px;border:1px solid #d8e3f1;border-radius:9px;padding:10px;background:white;color:#223c60}
            .predictive-model-disclosure{background:#fff;border:1px solid #dce6f5;border-radius:12px;margin:10px 0 16px;padding:12px 14px}
            .predictive-model-disclosure summary{cursor:pointer;color:#173b67;font-size:12px;font-weight:750}
            .predictive-model-disclosure summary span{font-size:10px;font-weight:400;color:#7b8da2;margin-left:7px}
            .predictive-model-disclosure>p{font-size:11px;color:#5b718c;margin:12px 0 0}
            @media(max-width:560px){.predictive-ref-filters,.predictive-ref-filters label,.predictive-ref-filters select{width:100%}}
            </style>
        </main>
    </div>
</x-layout.app>
