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
        return ['x' => round($x, 1), 'y' => round($y, 1), 'label' => $bucket->label, 'count' => $bucket->count, 'partial' => $index === $n - 1];
    });
    $completedPoints = $points->reject(fn ($point) => $point['partial'])->values();
    $hasPartialBucket = $points->contains(fn ($point) => $point['partial']);

    $smoothPath = '';
    if ($completedPoints->count() > 1) {
        $tension = 0.16;
        $smoothPath = 'M ' . $completedPoints[0]['x'] . ' ' . $completedPoints[0]['y'];

        for ($index = 0; $index < $completedPoints->count() - 1; $index++) {
            $previous = $completedPoints[$index - 1] ?? $completedPoints[$index];
            $current = $completedPoints[$index];
            $next = $completedPoints[$index + 1];
            $following = $completedPoints[$index + 2] ?? $next;
            $minY = min($current['y'], $next['y']);
            $maxY = max($current['y'], $next['y']);

            $control1X = $current['x'] + (($next['x'] - $previous['x']) * $tension);
            $control1Y = max($minY, min($maxY, $current['y'] + (($next['y'] - $previous['y']) * $tension)));
            $control2X = $next['x'] - (($following['x'] - $current['x']) * $tension);
            $control2Y = max($minY, min($maxY, $next['y'] - (($following['y'] - $current['y']) * $tension)));

            $smoothPath .= ' C '
                . number_format($control1X, 2, '.', '') . ' '
                . number_format($control1Y, 2, '.', '') . ', '
                . number_format($control2X, 2, '.', '') . ' '
                . number_format($control2Y, 2, '.', '') . ', '
                . $next['x'] . ' ' . $next['y'];
        }
    }

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

    $fleetSegmentOffset = 0;
    $fleetStatusSegments = collect([
        ['label' => 'Active', 'value' => $activeBuses, 'color' => '#16a34a'],
        ['label' => 'Under Maintenance', 'value' => $underMaintenance, 'color' => '#f59e0b'],
        ['label' => 'Inactive', 'value' => $inactiveBuses, 'color' => '#94a3b8'],
    ])->map(function ($segment) use ($totalBuses, &$fleetSegmentOffset) {
        $percentage = $totalBuses > 0 ? ($segment['value'] / $totalBuses) * 100 : 0;
        $segment['percentage'] = $percentage;
        $segment['offset'] = $fleetSegmentOffset;
        $fleetSegmentOffset += $percentage;
        return $segment;
    });
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
                <section class="analytics-kpi-strip analytics-domain-kpi-four">
                    <x-analytics.kpi label="Fuel Used" :value="number_format($fuel['totalFuel'] ?? 0, 1) . ' L'" small="Recorded fuel volume" icon="fa-gas-pump" />
                    <x-analytics.kpi label="Linked Distance" :value="number_format($fuel['totalDistance'] ?? 0, 1) . ' km'" small="Distance attached to fuel reports" icon="fa-road" tone="green" />
                    <x-analytics.kpi label="Weighted Efficiency" :value="number_format($fuel['fleetAverage'] ?? 0, 2) . ' km/L'" small="Distance divided by recorded fuel" icon="fa-gauge-high" tone="purple" />
                    <x-analytics.kpi label="Recorded Units" :value="collect($fuel['busSummaries'] ?? [])->count()" small="Buses represented in reports" icon="fa-bus" tone="yellow" />
                </section>

                <section class="analytics-domain-content">
                    <div class="analytics-domain-grid">
                        <x-analytics.panel title="Fuel Consumption Trend" :description="$periodLabel . ' · recorded fuel volume by period'">
                            <x-analytics.line-chart :items="$fuel['trend'] ?? collect()" value-key="fuel_liters" label-key="label" suffix=" L" empty-text="No fuel reports match the selected filters." />
                        </x-analytics.panel>

                        <x-analytics.panel title="Bus Efficiency Comparison" description="Highest recorded distance per liter for the selected period">
                            <x-analytics.horizontal-bars :items="$fuel['busSummaries'] ?? collect()" value-key="km_per_liter" label-key="bus_no" empty-text="No bus-level fuel efficiency records are available." />
                        </x-analytics.panel>
                    </div>

                    <div class="analytics-domain-grid equal">
                        <x-analytics.panel title="Fuel Reporting Coverage" description="Recorded evidence behind the descriptive totals">
                            <div class="analytics-record-list">
                                <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-file-lines"></i></span><div class="analytics-record-copy"><strong>Fuel reports</strong><span>Records included in the selected period</span></div><span class="analytics-record-value">{{ collect($fuel['records'] ?? [])->count() }}</span></div>
                                <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-bus"></i></span><div class="analytics-record-copy"><strong>Units represented</strong><span>Buses with recorded fuel activity</span></div><span class="analytics-record-value">{{ collect($fuel['busSummaries'] ?? [])->count() }}</span></div>
                                <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-road"></i></span><div class="analytics-record-copy"><strong>Linked distance</strong><span>Distance stored on fuel reports</span></div><span class="analytics-record-value">{{ number_format($fuel['totalDistance'] ?? 0, 1) }} km</span></div>
                            </div>
                        </x-analytics.panel>

                        <x-analytics.panel title="Efficiency Context" description="Descriptive comparison only; no causal claim is made">
                            <div class="analytics-record-list">
                                @forelse(collect($fuel['busSummaries'] ?? [])->take(4) as $row)
                                    <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-gauge-high"></i></span><div class="analytics-record-copy"><strong>{{ $row->bus_no }}</strong><span>{{ $row->entries }} fuel report{{ $row->entries === 1 ? '' : 's' }} · {{ number_format($row->fuel_liters, 1) }} L</span></div><span class="analytics-record-value">{{ number_format($row->km_per_liter, 2) }} km/L</span></div>
                                @empty
                                    <div class="analytics-compact-empty"><i class="fa-regular fa-folder-open"></i><span>No bus efficiency comparison is available.</span></div>
                                @endforelse
                            </div>
                        </x-analytics.panel>
                    </div>
                </section>

            @elseif($domain === 'bus-health')
                <section class="analytics-kpi-strip analytics-domain-kpi-four">
                    <x-analytics.kpi label="Active" :value="$activeBuses" small="Available buses" icon="fa-bus" tone="green" />
                    <x-analytics.kpi label="Under Maintenance" :value="$underMaintenance" small="Current Bus Master List status" icon="fa-screwdriver-wrench" tone="yellow" />
                    <x-analytics.kpi label="Inactive" :value="$inactiveBuses" small="Unavailable buses" icon="fa-circle-pause" tone="red" />
                    <x-analytics.kpi label="Fleet Availability" :value="number_format($fleetAvailability, 1) . '%'" small="Active share of Bus Master List" icon="fa-chart-pie" />
                </section>

                <section class="analytics-domain-content">
                    <div class="analytics-domain-grid">
                        <x-analytics.panel title="Fleet Status Distribution" description="Current operational status from the Bus Master List" :badge="$totalBuses . ' buses'">
                            <div class="analytics-status-overview">
                                <div class="analytics-status-ring" style="--ring-active: {{ $activeAngle }}deg; --ring-maintenance: {{ $maintenanceAngle }}deg;"><div><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>currently active</span></div></div>
                                <div class="analytics-status-legend">
                                    <div class="analytics-status-legend-row"><span><i class="analytics-status-dot green"></i>Active</span><strong>{{ $activeBuses }}</strong></div>
                                    <div class="analytics-status-legend-row"><span><i class="analytics-status-dot yellow"></i>Under Maintenance</span><strong>{{ $underMaintenance }}</strong></div>
                                    <div class="analytics-status-legend-row"><span><i class="analytics-status-dot red"></i>Inactive</span><strong>{{ $inactiveBuses }}</strong></div>
                                </div>
                            </div>
                        </x-analytics.panel>

                        <x-analytics.panel title="Current Fleet Units" description="Status snapshot; this is not a mechanical diagnosis">
                            <div class="analytics-record-list">
                                @forelse($buses->take(8) as $bus)
                                    <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-bus"></i></span><div class="analytics-record-copy"><strong>{{ $bus->bus_no }}</strong><span>{{ $bus->bus_model ?: 'Model not recorded' }}{{ $bus->plate_no ? ' · ' . $bus->plate_no : '' }}</span></div><span class="analytics-record-value">{{ $bus->status ?: 'Unspecified' }}</span></div>
                                @empty
                                    <div class="analytics-compact-empty"><i class="fa-solid fa-bus"></i><span>No buses are recorded in the Bus Master List.</span></div>
                                @endforelse
                            </div>
                        </x-analytics.panel>
                    </div>

                    <x-analytics.panel title="Bus Health Data Boundary" description="What this descriptive view can verify from current records">
                        <div class="analytics-record-list">
                            <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-circle-check"></i></span><div class="analytics-record-copy"><strong>Operational status is available</strong><span>Active, Under Maintenance, and Inactive come directly from the Bus Master List.</span></div><span class="analytics-record-value">Verified</span></div>
                            <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-shield-heart"></i></span><div class="analytics-record-copy"><strong>Mechanical condition is not inferred</strong><span>This page does not label a bus mechanically healthy without PMS or maintenance-condition evidence.</span></div><span class="analytics-record-value">No assumption</span></div>
                        </div>
                    </x-analytics.panel>
                </section>

            @elseif($domain === 'inventory')
                <section class="analytics-kpi-strip analytics-domain-kpi-four">
                    <x-analytics.kpi label="Total Items" :value="$inventoryTotal" small="Inventory records" icon="fa-boxes-stacked" />
                    <x-analytics.kpi label="Well Stocked" :value="$inventoryHealthy" small="Above reorder threshold" icon="fa-box-open" tone="green" />
                    <x-analytics.kpi label="Low Stock" :value="$inventoryLow" small="At or below reorder level" icon="fa-triangle-exclamation" tone="yellow" />
                    <x-analytics.kpi label="Out of Stock" :value="$inventoryCritical" small="No on-hand stock" icon="fa-circle-exclamation" tone="red" />
                </section>

                <section class="analytics-domain-content">
                    <div class="analytics-domain-grid">
                        <x-analytics.panel title="Stock-Level Distribution" description="Current stock status across inventory records" :badge="$inventoryTotal . ' items'">
                            <div class="analytics-status-overview">
                                <div class="analytics-status-ring" style="--ring-active: {{ $inventoryHealthyAngle }}deg; --ring-maintenance: {{ $inventoryLowAngle }}deg;"><div><strong>{{ number_format($healthyPct) }}%</strong><span>well stocked</span></div></div>
                                <div class="analytics-status-legend">
                                    <div class="analytics-status-legend-row"><span><i class="analytics-status-dot green"></i>Well Stocked</span><strong>{{ $inventoryHealthy }} ({{ number_format($healthyPct) }}%)</strong></div>
                                    <div class="analytics-status-legend-row"><span><i class="analytics-status-dot yellow"></i>Low Stock</span><strong>{{ $inventoryLow }} ({{ number_format($lowPct) }}%)</strong></div>
                                    <div class="analytics-status-legend-row"><span><i class="analytics-status-dot red"></i>Out of Stock</span><strong>{{ $inventoryCritical }} ({{ number_format($criticalPct) }}%)</strong></div>
                                </div>
                            </div>
                        </x-analytics.panel>

                        <x-analytics.panel title="Stock Status Comparison" description="Relative item counts by current stock state">
                            <x-analytics.horizontal-bars :items="$inventoryStatusBars" value-key="value" label-key="label" />
                        </x-analytics.panel>
                    </div>

                    <div class="analytics-domain-grid equal">
                        <x-analytics.panel title="Restock Exposure" description="Items currently at or below the reorder threshold">
                            <div class="analytics-record-list">
                                <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-triangle-exclamation"></i></span><div class="analytics-record-copy"><strong>Items requiring stock attention</strong><span>Low-stock plus out-of-stock inventory records</span></div><span class="analytics-record-value">{{ $inventoryLow + $inventoryCritical }}</span></div>
                                <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-ban"></i></span><div class="analytics-record-copy"><strong>Unavailable inventory records</strong><span>Items with no on-hand stock</span></div><span class="analytics-record-value">{{ $inventoryCritical }}</span></div>
                            </div>
                        </x-analytics.panel>

                        <x-analytics.panel title="Inventory Interpretation" description="Snapshot derived from current warehouse stock fields">
                            <div class="analytics-record-list">
                                <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-database"></i></span><div class="analytics-record-copy"><strong>Source</strong><span>Inventory items, on-hand quantity, and reorder level</span></div><span class="analytics-record-value">Current snapshot</span></div>
                                <div class="analytics-record-row"><span class="analytics-record-icon"><i class="fa-solid fa-scale-balanced"></i></span><div class="analytics-record-copy"><strong>Category totals reconcile</strong><span>Well Stocked + Low Stock + Out of Stock</span></div><span class="analytics-record-value">{{ $inventoryHealthy + $inventoryLow + $inventoryCritical }} / {{ $inventoryTotal }}</span></div>
                            </div>
                        </x-analytics.panel>
                    </div>
                </section>

            @else
                <section class="analytics-kpi-strip descriptive-kpi-row">
                    <x-analytics.kpi label="Distance Traveled" :value="number_format($totalDistance, 1) . ' km'" small="Total distance" icon="fa-location-dot" />
                    <x-analytics.kpi label="Average Speed" :value="number_format($averageSpeed, 1) . ' km/h'" small="Average while in motion" icon="fa-gauge-high" tone="green" />
                    <x-analytics.kpi label="Idle Time" :value="number_format($totalIdleMinutes / 60, 1) . ' hrs'" small="Total recorded idle time" icon="fa-hourglass-half" tone="yellow" />
                    <x-analytics.kpi label="Avg. Trip Duration" :value="number_format($averageTripDuration, 1) . ' min'" small="Average per trip" icon="fa-clock" tone="purple" />
                    <x-analytics.kpi label="Trips Processed" :value="$tripCount" small="Total trips recorded" icon="fa-route" />
                    <x-analytics.kpi label="Buses Active" :value="$activeBuses" :small="'Out of ' . $totalBuses . ' buses'" icon="fa-bus" tone="green" />
                </section>

                <section class="analytics-main-grid analytics-main-grid-balanced descriptive-main-grid">
                    <article class="analytics-card analytics-reference-chart-card descriptive-trip-card">
                        <x-analytics.card-header title="Processed Trip Activity" description="Trip-record volume across the selected period." />
                        <div class="reference-line-chart"><svg viewBox="0 0 720 230" preserveAspectRatio="none" role="img" aria-label="Processed trip activity chart">@foreach([44,81.5,119,156.5,194] as $y)<line x1="42" y1="{{ $y }}" x2="678" y2="{{ $y }}" class="reference-chart-grid" />@endforeach @if($smoothPath !== '')<path d="{{ $smoothPath }}" class="reference-chart-line" aria-hidden="true" />@endif @foreach($points as $point)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" class="reference-chart-dot{{ $point['partial'] ? ' is-partial' : '' }}" /><text x="{{ $point['x'] }}" y="{{ max(18, $point['y'] - 12) }}" text-anchor="middle" class="reference-chart-value{{ $point['partial'] ? ' is-partial' : '' }}">{{ $point['count'] }}</text><text x="{{ $point['x'] }}" y="218" text-anchor="middle" class="reference-chart-label{{ $point['partial'] ? ' is-partial' : '' }}">{{ $point['label'] }}{{ $point['partial'] ? '*' : '' }}</text>@endforeach</svg></div>
                        <div class="reference-chart-legend"><span><i></i> Trips Processed</span>@if($hasPartialBucket)<span class="reference-chart-partial-note"><i class="fa-regular fa-clock"></i> Current bucket is partial</span>@endif</div>
                    </article>

                    <article class="analytics-card descriptive-availability-card">
                        <x-analytics.card-header title="Fleet Availability" description="Current Bus Master List status." :badge="$totalBuses . ' buses'" />
                        <div class="analytics-availability-layout">
                            <div class="availability-score">
                                <div class="fleet-availability-donut fleet-donut" data-default-value="{{ number_format($fleetAvailability, 1) }}%" data-default-label="Active">
                                    <svg class="fleet-donut-svg" viewBox="0 0 180 180" role="img" aria-label="Fleet availability status distribution">
                                        <circle cx="90" cy="90" r="59" pathLength="100" fill="none" class="fleet-donut-track" />
                                        @foreach($fleetStatusSegments as $segment)
                                            <g class="fleet-donut-group" tabindex="0" role="button" data-donut-index="{{ $loop->index }}" data-label="{{ $segment['label'] }}" data-value="{{ $segment['value'] }}" data-percentage="{{ number_format($segment['percentage'], 1, '.', '') }}" aria-label="{{ $segment['label'] }}: {{ $segment['value'] }} buses, {{ number_format($segment['percentage'], 1) }} percent">
                                                <circle cx="90" cy="90" r="59" pathLength="100" fill="none" transform="rotate(-90 90 90)" class="fleet-donut-segment fleet-donut-segment-main" style="stroke: {{ $segment['color'] }}; stroke-dasharray: {{ number_format($segment['percentage'], 4, '.', '') }} {{ number_format(max(0, 100 - $segment['percentage']), 4, '.', '') }}; stroke-dashoffset: -{{ number_format($segment['offset'], 4, '.', '') }};" />
                                                <circle cx="90" cy="90" r="75" pathLength="100" fill="none" transform="rotate(-90 90 90)" class="fleet-donut-segment fleet-donut-segment-outer" style="stroke: {{ $segment['color'] }}; stroke-dasharray: {{ number_format($segment['percentage'], 4, '.', '') }} {{ number_format(max(0, 100 - $segment['percentage']), 4, '.', '') }}; stroke-dashoffset: -{{ number_format($segment['offset'], 4, '.', '') }};" />
                                            </g>
                                        @endforeach
                                    </svg>
                                    <div class="fleet-donut-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div>
                                    <div class="fleet-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div>
                                </div>
                            </div>
                            <div class="availability-breakdown">
                                <div class="availability-row" data-donut-index="0"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div>
                                <div class="availability-row" data-donut-index="1"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div>
                                <div class="availability-row" data-donut-index="2"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div>
                                <div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div>
                            </div>
                        </div>
                    </article>
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