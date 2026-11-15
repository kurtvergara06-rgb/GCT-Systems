<section class="analytics-kpi-strip analytics-kpi-strip-six">
    @php
        $overviewKpis = [
            ['Distance Traveled', number_format($totalDistance, 1) . ' km', 'Total distance', 'fa-location-dot', 'blue', $comparison['distance']],
            ['Average Speed', number_format($averageSpeed, 1) . ' km/h', 'Average while in motion', 'fa-gauge-high', 'green', $comparison['speed']],
            ['Idle Time', number_format($totalIdleMinutes / 60, 1) . ' hrs', 'Total recorded idle time', 'fa-hourglass-half', 'yellow', $comparison['idle']],
            ['Avg. Trip Duration', number_format($averageTripDuration, 1) . ' min', 'Average per trip', 'fa-clock', 'purple', $comparison['duration']],
            ['Trips Processed', number_format($tripCount), 'Total trips recorded', 'fa-route', 'blue', $comparison['trips']],
            ['Buses Active', number_format($activeBuses), 'Out of ' . number_format($totalBuses) . ' buses', 'fa-bus', 'green', null],
        ];
    @endphp

    @foreach($overviewKpis as [$label, $value, $description, $icon, $tone, $delta])
        @php
            $isBusAvailability = $label === 'Buses Active';
            $status = $isBusAvailability
                ? number_format($fleetAvailability, 1) . '% utilization'
                : ($delta === null ? 'No prior data' : $deltaText($delta) . ' ' . $comparison['label']);
            $statusTone = $isBusAvailability
                ? 'positive'
                : ($delta === null ? 'neutral' : ($delta < 0 ? 'negative' : 'positive'));
        @endphp
        <x-analytics.kpi
            :label="$label"
            :value="$value"
            :description="$description"
            :status="$status"
            :status-tone="$statusTone"
            :icon="$icon"
            :tone="$tone"
        />
    @endforeach
</section>

<section class="descriptive-overview-main-grid">
    <article class="analytics-card analytics-reference-chart-card descriptive-trip-card descriptive-overview-trip-card">
        <div class="descriptive-overview-card-heading"><div><h3>Processed Trip Activity <i class="fa-regular fa-circle-info"></i></h3><p>Trip-record volume across the selected period.</p></div><span>{{ $periodLabel }}</span></div>
        <div class="trip-canvas-chart" data-trip-points='@json($tripChartData)'><canvas class="trip-canvas" role="img" aria-label="Processed trip activity chart"></canvas><div class="trip-canvas-tooltip" aria-hidden="true"><strong></strong><span><i></i> Trips Processed <b></b></span></div></div>
        <div class="trip-canvas-legend"><span><i></i> Trips Processed</span>@if($hasPartialBucket)<span class="trip-canvas-partial-note"><i class="fa-regular fa-clock"></i> Current bucket is partial</span>@endif</div>
    </article>

    <article class="analytics-card descriptive-availability-card descriptive-overview-fleet-card">
        <div class="descriptive-overview-card-heading"><div><h3>Fleet Availability <i class="fa-regular fa-circle-info"></i></h3><p>Current Bus Master List status.</p></div><span>{{ $totalBuses }} buses</span></div>
        <div class="analytics-availability-layout"><div class="availability-score"><div class="fleet-css-donut" data-default-value="{{ number_format($fleetAvailability, 1) }}%" data-default-label="Active" data-active="{{ number_format($activePct, 2, '.', '') }}" data-maintenance="{{ number_format($maintenancePct, 2, '.', '') }}" data-inactive="{{ number_format($inactivePct, 2, '.', '') }}" style="--fleet-active: {{ number_format($activePct, 2, '.', '') }}%; --fleet-maintenance-end: {{ number_format($maintenanceEndPct, 2, '.', '') }}%;"><div class="fleet-css-donut-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div><div class="fleet-css-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div></div></div><div class="availability-breakdown"><div class="availability-row" data-donut-index="0" data-label="Active" data-value="{{ $activeBuses }}" data-percentage="{{ number_format($activePct, 1, '.', '') }}"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }} <small>{{ number_format($activePct, 1) }}%</small></strong></div><div class="availability-row" data-donut-index="1" data-label="Under Maintenance" data-value="{{ $underMaintenance }}" data-percentage="{{ number_format($maintenancePct, 1, '.', '') }}"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }} <small>{{ number_format($maintenancePct, 1) }}%</small></strong></div><div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }} <small>{{ number_format($inactivePct, 1) }}%</small></strong></div><div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div></div></div>
    </article>
</section>

<section class="descriptive-overview-lower-grid">
    <article class="analytics-card ranking-card descriptive-ranking-card"><div class="descriptive-overview-card-heading"><div><h3>Top Routes by Trips <i class="fa-regular fa-circle-info"></i></h3><p>{{ $periodLabel }} · highest-volume routes</p></div></div><div class="ranking-list refined-ranking-list">@forelse($routes as $route)<div class="refined-ranking-row"><span class="refined-rank-number">{{ $loop->iteration }}</span><div class="refined-ranking-main"><div class="refined-ranking-title-row"><strong>{{ $route->label }}</strong><span>{{ $route->trips }} trips</span></div><div class="refined-ranking-meta"><span><i class="fa-regular fa-clock"></i>{{ number_format($route->average_duration, 1) }} min avg.</span><span><i class="fa-solid fa-chart-pie"></i>{{ number_format($route->share, 1) }}% of trips</span></div><div class="metric-bar refined-metric-bar"><span style="width: {{ $route->progress }}%"></span></div></div></div>@empty<p class="ranking-empty">No route records match the selected filters.</p>@endforelse</div></article>
    <article class="analytics-card ranking-card descriptive-ranking-card"><div class="descriptive-overview-card-heading"><div><h3>Busiest Buses <i class="fa-regular fa-circle-info"></i></h3><p>{{ $periodLabel }} · highest recorded trip activity</p></div></div><div class="ranking-list refined-ranking-list">@forelse($busActivity as $bus)<div class="refined-ranking-row"><span class="refined-rank-number">{{ $loop->iteration }}</span><div class="refined-ranking-main"><div class="refined-ranking-title-row"><strong>{{ $bus->bus }}</strong><span>{{ $bus->trips }} trips</span></div><div class="refined-ranking-meta"><span><i class="fa-solid fa-road"></i>{{ number_format($bus->distance, 1) }} km</span><span><i class="fa-solid fa-chart-pie"></i>{{ number_format($bus->share, 1) }}% trip share</span></div><div class="metric-bar refined-metric-bar"><span style="width: {{ $bus->progress }}%"></span></div></div></div>@empty<p class="ranking-empty">No bus activity matches the selected filters.</p>@endforelse</div></article>
    <div class="descriptive-overview-side-stack">
        <article class="analytics-card descriptive-summary-card"><div class="descriptive-overview-card-heading"><div><h3>Fleet Status <i class="fa-regular fa-circle-info"></i></h3><p>Current Bus Master List operational status</p></div><span>{{ $totalBuses }} buses</span></div><div class="availability-breakdown"><div class="availability-row"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }} <small>{{ number_format($activePct, 1) }}%</small></strong></div><div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }} <small>{{ number_format($maintenancePct, 1) }}%</small></strong></div><div class="availability-row"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }} <small>{{ number_format($inactivePct, 1) }}%</small></strong></div></div></article>
        <article class="analytics-card descriptive-summary-card"><div class="descriptive-overview-card-heading"><div><h3>Inventory Overview <i class="fa-regular fa-circle-info"></i></h3><p>Current stock-level summary</p></div><span>{{ $inventoryTotal }} items</span></div><div class="availability-breakdown"><div class="availability-row"><div><span class="availability-dot operational"></span><span>Well Stocked</span></div><strong>{{ $inventoryHealthy }} <small>({{ number_format($healthyPct) }}%)</small></strong></div><div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Low Stock</span></div><strong>{{ $inventoryLow }} <small>({{ number_format($lowPct) }}%)</small></strong></div><div class="availability-row"><div><span class="availability-dot inactive"></span><span>Out of Stock</span></div><strong>{{ $inventoryCritical }} <small>({{ number_format($criticalPct) }}%)</small></strong></div></div></article>
    </div>
</section>

<section class="descriptive-overview-footer-grid">
    <article class="analytics-card descriptive-recent-alerts-card">
        <div class="descriptive-overview-card-heading"><div><h3>Recent Alerts <i class="fa-regular fa-circle-info"></i></h3></div><a href="{{ route('admin.notifications') }}">View all alerts <i class="fa-solid fa-arrow-right"></i></a></div>
        @if($recentAlerts->isNotEmpty())
            <div class="descriptive-alerts-table-wrap"><table class="descriptive-alerts-table"><thead><tr><th>Time</th><th>Type</th><th>Entity</th><th>Status</th></tr></thead><tbody>@foreach($recentAlerts as $alert)<tr><td>{{ $alert['date'] }}<br><small>{{ $alert['time'] }}</small></td><td><span class="descriptive-alert-type {{ strtolower($alert['type']) }}"><i></i>{{ $alert['type'] }}</span></td><td>{{ $alert['reference'] !== '—' ? $alert['reference'] : $alert['module'] }}</td><td><span class="descriptive-alert-state {{ $alert['unread'] ? 'open' : 'resolved' }}">{{ $alert['unread'] ? 'Open' : 'Read' }}</span></td></tr>@endforeach</tbody></table></div>
        @else
            <div class="analytics-compact-empty"><i class="fa-regular fa-bell-slash"></i><span>No recorded notifications are available.</span></div>
        @endif
    </article>

    <article class="analytics-card descriptive-quick-insights-card">
        <div class="descriptive-overview-card-heading"><div><h3>Quick Insights <i class="fa-regular fa-circle-info"></i></h3></div><span>{{ $comparison['label'] }}</span></div>
        <div class="descriptive-insight-grid">
            @php
                $insights = [
                    ['trips', 'More trips processed', number_format($tripCount) . ' vs ' . number_format($comparison['previousTrips']), 'fa-arrow-trend-up', $comparison['trips']],
                    ['idle', 'Idle time change', number_format($totalIdleMinutes / 60, 1) . ' hrs vs ' . number_format($comparison['previousIdleMinutes'] / 60, 1) . ' hrs', 'fa-hourglass-half', $comparison['idle']],
                    ['distance', 'Distance traveled', number_format($totalDistance, 1) . ' km vs ' . number_format($comparison['previousDistance'], 1) . ' km', 'fa-road', $comparison['distance']],
                ];
            @endphp
            @foreach($insights as [$key, $label, $detail, $icon, $delta])
                <div class="descriptive-insight-card {{ $delta !== null && $delta < 0 ? 'negative' : 'positive' }}"><span><i class="fa-solid {{ $icon }}"></i></span><div><strong>{{ $deltaText($delta) }}</strong><b>{{ $label }}</b><small>{{ $detail }}</small></div></div>
            @endforeach
        </div>
    </article>
</section>
