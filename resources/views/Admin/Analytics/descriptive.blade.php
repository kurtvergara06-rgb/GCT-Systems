@php
    $tabs = [
        'all' => ['All', 'fa-table-cells-large'],
        'fleet-trip' => ['Fleet & Trip', 'fa-route'],
        'fuel' => ['Fuel', 'fa-gas-pump'],
        'bus-health' => ['Bus Health', 'fa-heart-pulse'],
        'inventory' => ['Inventory', 'fa-boxes-stacked'],
    ];

    $max = max(1, (int) ($trend->max('count') ?? 0));
    $n = max(1, $trend->count());
    $points = $trend->map(function ($bucket, $index) use ($max, $n) {
        $x = $n > 1 ? 42 + (($index / ($n - 1)) * 636) : 360;
        $y = 194 - (($bucket->count / $max) * 150);

        return [
            'x' => round($x, 1),
            'y' => round($y, 1),
            'label' => $bucket->label,
            'count' => $bucket->count,
            'partial' => $index === $n - 1,
        ];
    });

    $completedPoints = $points->reject(fn ($point) => $point['partial'])->values();
    $poly = $completedPoints->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ');
    $area = $completedPoints->count() > 1
        ? $poly . ' ' . $completedPoints->last()['x'] . ',194 ' . $completedPoints->first()['x'] . ',194'
        : '';
    $hasPartialBucket = $points->contains(fn ($point) => $point['partial']);

    $healthyPct = $inventoryTotal > 0 ? ($inventoryHealthy / $inventoryTotal) * 100 : 0;
    $lowPct = $inventoryTotal > 0 ? ($inventoryLow / $inventoryTotal) * 100 : 0;
    $criticalPct = $inventoryTotal > 0 ? ($inventoryCritical / $inventoryTotal) * 100 : 0;
@endphp

<x-layout.app
    title="FROMS - Descriptive Analytics"
    :assets="[
        'resources/css/Admin/Analytics/fleet-trip.css',
        'resources/css/Admin/Analytics/fleet-trip-redesign.css',
        'resources/css/Admin/Analytics/fleet-trip-rankings.css',
        'resources/css/Admin/Analytics/analytics-stage-hub.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main analytics-stage-page fleet-trip-page descriptive-analytics-page">
            <x-layout.topbar title="Descriptive Analytics" subtitle="What happened based on recorded operational data." />

            <section class="analytics-domain-toolbar descriptive-toolbar">
                <nav class="analytics-domain-tabs" aria-label="Descriptive analytics domains">
                    @foreach($tabs as $key => $tab)
                        <a href="{{ url('/analytics/descriptive') }}?{{ http_build_query(['domain' => $key, 'period' => $period, 'bus' => $selectedBus !== 'ALL' ? $selectedBus : null]) }}" class="{{ $domain === $key ? 'active' : '' }}">
                            <i class="fa-solid {{ $tab[1] }}"></i>{{ $tab[0] }}
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ url('/analytics/descriptive') }}" class="analytics-stage-filters">
                    <input type="hidden" name="domain" value="{{ $domain }}">
                    <label><span>Period</span><select name="period"><option value="this-month" @selected($period === 'this-month')>This Month</option><option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option><option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option><option value="this-year" @selected($period === 'this-year')>This Year</option></select></label>
                    <label><span>Bus</span><select name="bus"><option value="all">All Buses</option>@foreach($buses as $bus)<option value="{{ $bus->bus_no }}" @selected($selectedBus === strtoupper($bus->bus_no))>{{ $bus->bus_no }}</option>@endforeach</select></label>
                    <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                </form>
            </section>

            @if($domain === 'fuel')
                <section class="analytics-kpi-strip analytics-kpi-strip-three"><x-analytics.kpi label="Fuel Used" :value="number_format($fuel['totalFuel'] ?? 0, 1) . ' L'" small="Recorded fuel volume" icon="fa-gas-pump" /><x-analytics.kpi label="Linked Distance" :value="number_format($fuel['totalDistance'] ?? 0, 1) . ' km'" small="Distance attached to fuel reports" icon="fa-road" tone="green" /><x-analytics.kpi label="Weighted Efficiency" :value="number_format($fuel['fleetAverage'] ?? 0, 2) . ' km/L'" small="Distance divided by recorded fuel" icon="fa-gauge-high" tone="purple" /></section>
            @elseif($domain === 'bus-health')
                <section class="analytics-kpi-strip analytics-kpi-strip-three"><x-analytics.kpi label="Active" :value="$activeBuses" small="Available buses" icon="fa-bus" tone="green" /><x-analytics.kpi label="Under Maintenance" :value="$underMaintenance" small="Needs attention" icon="fa-screwdriver-wrench" tone="yellow" /><x-analytics.kpi label="Inactive" :value="$inactiveBuses" small="Unavailable buses" icon="fa-circle-pause" tone="red" /></section>
            @elseif($domain === 'inventory')
                <section class="analytics-kpi-strip analytics-kpi-strip-three"><x-analytics.kpi label="Well Stocked" :value="$inventoryHealthy" small="Above reorder threshold" icon="fa-boxes-stacked" tone="green" /><x-analytics.kpi label="Low Stock" :value="$inventoryLow" small="At or below reorder level" icon="fa-triangle-exclamation" tone="yellow" /><x-analytics.kpi label="Out of Stock" :value="$inventoryCritical" small="No on-hand stock" icon="fa-circle-exclamation" tone="red" /></section>
            @else
                <section class="analytics-kpi-strip descriptive-kpi-row"><x-analytics.kpi label="Distance Traveled" :value="number_format($totalDistance, 1) . ' km'" small="Total distance" icon="fa-location-dot" /><x-analytics.kpi label="Average Speed" :value="number_format($averageSpeed, 1) . ' km/h'" small="Average while in motion" icon="fa-gauge-high" tone="green" /><x-analytics.kpi label="Idle Time" :value="number_format($totalIdleMinutes / 60, 1) . ' hrs'" small="Total recorded idle time" icon="fa-hourglass-half" tone="yellow" /><x-analytics.kpi label="Avg. Trip Duration" :value="number_format($averageTripDuration, 1) . ' min'" small="Average per trip" icon="fa-clock" tone="purple" /><x-analytics.kpi label="Trips Processed" :value="$tripCount" small="Total trips recorded" icon="fa-route" /><x-analytics.kpi label="Buses Active" :value="$activeBuses" :small="'Out of ' . $totalBuses . ' buses'" icon="fa-bus" tone="green" /></section>

                <section class="analytics-main-grid analytics-main-grid-balanced descriptive-main-grid">
                    <article class="analytics-card analytics-reference-chart-card descriptive-trip-card">
                        <x-analytics.card-header title="Processed Trip Activity" description="Trip-record volume across the selected period." />
                        <div class="reference-line-chart"><svg viewBox="0 0 720 230" preserveAspectRatio="none" role="img" aria-label="Processed trip activity chart"><defs><linearGradient id="tripFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2f6ee5" stop-opacity=".22" /><stop offset="100%" stop-color="#2f6ee5" stop-opacity=".02" /></linearGradient></defs>@foreach([44, 81.5, 119, 156.5, 194] as $y)<line x1="42" y1="{{ $y }}" x2="678" y2="{{ $y }}" class="reference-chart-grid" />@endforeach @if($area !== '')<polygon points="{{ $area }}" fill="url(#tripFill)" class="reference-chart-area" />@endif @if($completedPoints->count() > 1)<polyline points="{{ $poly }}" class="reference-chart-line" />@endif @foreach($points as $point)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" class="reference-chart-dot{{ $point['partial'] ? ' is-partial' : '' }}" /><text x="{{ $point['x'] }}" y="{{ max(18, $point['y'] - 12) }}" text-anchor="middle" class="reference-chart-value{{ $point['partial'] ? ' is-partial' : '' }}">{{ $point['count'] }}</text><text x="{{ $point['x'] }}" y="218" text-anchor="middle" class="reference-chart-label{{ $point['partial'] ? ' is-partial' : '' }}">{{ $point['label'] }}{{ $point['partial'] ? '*' : '' }}</text>@endforeach</svg></div>
                        <div class="reference-chart-legend"><span><i></i> Trips Processed</span>@if($hasPartialBucket)<span class="reference-chart-partial-note"><i class="fa-regular fa-clock"></i> Current bucket is partial</span>@endif</div>
                    </article>

                    <article class="analytics-card descriptive-availability-card"><x-analytics.card-header title="Fleet Availability" description="Current Bus Master List status." :badge="$totalBuses . ' buses'" /><div class="analytics-availability-layout"><div class="availability-score"><div class="availability-ring" style="--availability-angle: {{ min(360, max(0, $fleetAvailability * 3.6)) }}deg"><div class="availability-ring-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div></div></div><div class="availability-breakdown"><div class="availability-row"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div><div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div><div class="availability-row"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div><div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div></div></div></article>
                </section>

                @if($domain === 'all')
                    <section class="descriptive-bottom-grid">
                        <article class="analytics-card ranking-card descriptive-ranking-card"><x-analytics.card-header title="Top Routes by Trips" :description="$periodLabel . ' · highest-volume routes'" /><div class="ranking-list refined-ranking-list">@forelse($routes as $route)<div class="refined-ranking-row"><span class="refined-rank-number">{{ $loop->iteration }}</span><div class="refined-ranking-main"><div class="refined-ranking-title-row"><strong>{{ $route->label }}</strong><span>{{ $route->trips }} trips</span></div><div class="refined-ranking-meta"><span><i class="fa-regular fa-clock"></i>{{ number_format($route->average_duration, 1) }} min avg.</span><span><i class="fa-solid fa-chart-pie"></i>{{ number_format($route->share, 1) }}% of trips</span></div><div class="metric-bar refined-metric-bar"><span style="width: {{ $route->progress }}%"></span></div></div></div>@empty<p class="ranking-empty">No route records match the selected filters.</p>@endforelse</div></article>
                        <article class="analytics-card ranking-card descriptive-ranking-card"><x-analytics.card-header title="Busiest Buses" :description="$periodLabel . ' · highest recorded trip activity'" /><div class="ranking-list refined-ranking-list">@forelse($busActivity as $bus)<div class="refined-ranking-row"><span class="refined-rank-number">{{ $loop->iteration }}</span><div class="refined-ranking-main"><div class="refined-ranking-title-row"><strong>{{ $bus->bus }}</strong><span>{{ $bus->trips }} trips</span></div><div class="refined-ranking-meta"><span><i class="fa-solid fa-road"></i>{{ number_format($bus->distance, 1) }} km</span><span><i class="fa-solid fa-chart-pie"></i>{{ number_format($bus->share, 1) }}% trip share</span></div><div class="metric-bar refined-metric-bar"><span style="width: {{ $bus->progress }}%"></span></div></div></div>@empty<p class="ranking-empty">No bus activity matches the selected filters.</p>@endforelse</div></article>
                        <div class="descriptive-summary-stack"><article class="analytics-card descriptive-summary-card"><x-analytics.card-header title="Fleet Status" description="Current Bus Master List operational status" :badge="$totalBuses . ' buses'" /><div class="availability-breakdown"><div class="availability-row"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div><div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div><div class="availability-row"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div></div></article><article class="analytics-card descriptive-summary-card"><x-analytics.card-header title="Inventory Overview" description="Current stock-level summary" :badge="$inventoryTotal . ' items'" /><div class="availability-breakdown"><div class="availability-row"><div><span class="availability-dot operational"></span><span>Well Stocked</span></div><strong>{{ $inventoryHealthy }} ({{ number_format($healthyPct) }}%)</strong></div><div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Low Stock</span></div><strong>{{ $inventoryLow }} ({{ number_format($lowPct) }}%)</strong></div><div class="availability-row"><div><span class="availability-dot inactive"></span><span>Out of Stock</span></div><strong>{{ $inventoryCritical }} ({{ number_format($criticalPct) }}%)</strong></div></div></article></div>
                    </section>
                @else
                    <section class="analytics-list-grid descriptive-ranking-only-grid"><article class="analytics-card ranking-card"><x-analytics.card-header title="Top Routes by Trips" :description="$periodLabel . ' · highest-volume routes'" /><div class="ranking-list refined-ranking-list">@forelse($routes as $route)<div class="refined-ranking-row"><span class="refined-rank-number">{{ $loop->iteration }}</span><div class="refined-ranking-main"><div class="refined-ranking-title-row"><strong>{{ $route->label }}</strong><span>{{ $route->trips }} trips</span></div><div class="refined-ranking-meta"><span><i class="fa-regular fa-clock"></i>{{ number_format($route->average_duration, 1) }} min avg.</span><span><i class="fa-solid fa-chart-pie"></i>{{ number_format($route->share, 1) }}%</span></div><div class="metric-bar refined-metric-bar"><span style="width: {{ $route->progress }}%"></span></div></div></div>@empty<p class="ranking-empty">No route records match the selected filters.</p>@endforelse</div></article><article class="analytics-card ranking-card"><x-analytics.card-header title="Busiest Buses" :description="$periodLabel . ' · highest recorded trip activity'" /><div class="ranking-list refined-ranking-list">@forelse($busActivity as $bus)<div class="refined-ranking-row"><span class="refined-rank-number">{{ $loop->iteration }}</span><div class="refined-ranking-main"><div class="refined-ranking-title-row"><strong>{{ $bus->bus }}</strong><span>{{ $bus->trips }} trips</span></div><div class="refined-ranking-meta"><span><i class="fa-solid fa-road"></i>{{ number_format($bus->distance, 1) }} km</span><span><i class="fa-solid fa-chart-pie"></i>{{ number_format($bus->share, 1) }}%</span></div><div class="metric-bar refined-metric-bar"><span style="width: {{ $bus->progress }}%"></span></div></div></div>@empty<p class="ranking-empty">No bus activity matches the selected filters.</p>@endforelse</div></article></section>
                @endif
            @endif
        </main>
    </div>
</x-layout.app>