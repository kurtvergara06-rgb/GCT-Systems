<section class="analytics-kpi-strip">
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
            $change = $isBusAvailability
                ? number_format($fleetAvailability, 1) . '% utilization'
                : ($delta === null ? 'No prior data' : $deltaText($delta) . ' ' . $comparison['label']);
            $changeType = $isBusAvailability
                ? 'positive'
                : ($delta === null ? 'neutral' : ($delta < 0 ? 'negative' : 'positive'));
            $iconVariant = $tone;
        @endphp
        <x-analytics.kpi
            :label="$label"
            :value="$value"
            :description="$description"
            :icon="$icon"
            :icon-variant="$iconVariant"
            :change="$change"
            :change-type="$changeType"
        />
    @endforeach
</section>

<section class="descriptive-overview-main-grid">
    <x-analytics.card
        title="Processed Trip Activity"
        description="Trip-record volume across the selected period."
        :badge="$periodLabel"
    >
        <div class="trip-canvas-chart" data-trip-points='@json($tripChartData)'>
            <canvas class="trip-canvas" role="img" aria-label="Processed trip activity chart"></canvas>
            <div class="trip-canvas-tooltip" aria-hidden="true">
                <strong></strong>
                <span><i></i> Trips Processed <b></b></span>
            </div>
        </div>

        <div class="trip-canvas-legend">
            <span><i></i> Trips Processed</span>
            @if($hasPartialBucket)
                <span class="trip-canvas-partial-note"><i class="fa-regular fa-clock"></i> Current bucket is partial</span>
            @endif
        </div>
    </x-analytics.card>

    <x-analytics.card
        title="Fleet Availability"
        description="Current Bus Master List status."
        :badge="$totalBuses . ' buses'"
    >
        <div class="analytics-availability-layout">
            <div class="availability-score">
                <div
                    class="fleet-css-donut"
                    data-default-value="{{ number_format($fleetAvailability, 1) }}%"
                    data-default-label="Active"
                    data-active="{{ number_format($activePct, 2, '.', '') }}"
                    data-maintenance="{{ number_format($maintenancePct, 2, '.', '') }}"
                    data-inactive="{{ number_format($inactivePct, 2, '.', '') }}"
                    style="--fleet-active: {{ number_format($activePct, 2, '.', '') }}%; --fleet-maintenance-end: {{ number_format($maintenanceEndPct, 2, '.', '') }}%;"
                >
                    <div class="fleet-css-donut-center">
                        <strong>{{ number_format($fleetAvailability, 1) }}%</strong>
                        <span>Active</span>
                    </div>
                    <div class="fleet-css-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div>
                </div>
            </div>

            <div class="availability-breakdown">
                <div class="availability-row" data-donut-index="0" data-label="Active" data-value="{{ $activeBuses }}" data-percentage="{{ number_format($activePct, 1, '.', '') }}">
                    <div><span class="availability-dot operational"></span><span>Active</span></div>
                    <strong>{{ $activeBuses }} <small>{{ number_format($activePct, 1) }}%</small></strong>
                </div>
                <div class="availability-row" data-donut-index="1" data-label="Under Maintenance" data-value="{{ $underMaintenance }}" data-percentage="{{ number_format($maintenancePct, 1, '.', '') }}">
                    <div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div>
                    <strong>{{ $underMaintenance }} <small>{{ number_format($maintenancePct, 1) }}%</small></strong>
                </div>
                <div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}">
                    <div><span class="availability-dot inactive"></span><span>Inactive</span></div>
                    <strong>{{ $inactiveBuses }} <small>{{ number_format($inactivePct, 1) }}%</small></strong>
                </div>
                <div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div>
            </div>
        </div>
    </x-analytics.card>
</section>

<section class="descriptive-overview-lower-grid">
    <x-analytics.card
        title="Top Routes by Trips"
        description="{{ $periodLabel }} &middot; highest-volume routes"
    >
        <div class="ranking-list refined-ranking-list">
            @forelse($routes as $route)
                <div class="refined-ranking-row">
                    <span class="refined-rank-number">{{ $loop->iteration }}</span>
                    <div class="refined-ranking-main">
                        <div class="refined-ranking-title-row">
                            <strong>{{ $route->label }}</strong>
                            <span>{{ $route->trips }} {{ \Illuminate\Support\Str::plural('trip', $route->trips) }}</span>
                        </div>
                        <div class="refined-ranking-meta">
                            <span><i class="fa-regular fa-clock"></i> {{ number_format($route->average_duration, 1) }} min avg.</span>
                            <span><i class="fa-solid fa-chart-pie"></i> {{ number_format($route->share, 1) }}% of trips</span>
                        </div>
                        <div class="metric-bar refined-metric-bar"><span style="width: {{ $route->progress }}%"></span></div>
                    </div>
                </div>
            @empty
                <p class="ranking-empty">No route records match the selected filters.</p>
            @endforelse
        </div>
    </x-analytics.card>

    <x-analytics.card
        title="Busiest Buses"
        description="{{ $periodLabel }} &middot; highest recorded trip activity"
    >
        <div class="ranking-list refined-ranking-list">
            @forelse($busActivity as $bus)
                <div class="refined-ranking-row">
                    <span class="refined-rank-number">{{ $loop->iteration }}</span>
                    <div class="refined-ranking-main">
                        <div class="refined-ranking-title-row">
                            <strong>{{ $bus->bus }}</strong>
                            <span>{{ $bus->trips }} {{ \Illuminate\Support\Str::plural('trip', $bus->trips) }}</span>
                        </div>
                        <div class="refined-ranking-meta">
                            <span><i class="fa-solid fa-road"></i> {{ number_format($bus->distance, 1) }} km</span>
                            <span><i class="fa-solid fa-chart-pie"></i> {{ number_format($bus->share, 1) }}% trip share</span>
                        </div>
                        <div class="metric-bar refined-metric-bar"><span style="width: {{ $bus->progress }}%"></span></div>
                    </div>
                </div>
            @empty
                <p class="ranking-empty">No bus activity matches the selected filters.</p>
            @endforelse
        </div>
    </x-analytics.card>

    <div class="descriptive-overview-side-stack">
        <x-analytics.card
            title="Fuel & Efficiency"
            description="Fleet consumption & efficiency snapshot"
            :badge="number_format($fuel['fleetAverage'] ?? 0, 2) . ' km/L'"
        >
            <div class="availability-breakdown">
                <div class="availability-row">
                    <div><span class="availability-dot operational"></span><span>Average Efficiency</span></div>
                    <strong>{{ number_format($fuel['fleetAverage'] ?? 0, 2) }} <small>km/L</small></strong>
                </div>
                <div class="availability-row">
                    <div><span class="availability-dot maintenance"></span><span>Total Fuel Burn</span></div>
                    <strong>{{ number_format($fuel['totalFuel'] ?? 0, 1) }} <small>Liters</small></strong>
                </div>
                <div class="availability-row">
                    <div><span class="availability-dot inactive"></span><span>Idling Exposure</span></div>
                    <strong>{{ number_format($totalIdleMinutes / 60, 1) }} <small>Hours</small></strong>
                </div>
            </div>
        </x-analytics.card>

        <x-analytics.card
            title="Inventory Overview"
            description="Current stock-level summary"
            :badge="$inventoryTotal . ' items'"
        >
            <div class="availability-breakdown">
                <div class="availability-row"><div><span class="availability-dot operational"></span><span>Well Stocked</span></div><strong>{{ $inventoryHealthy }} <small>({{ number_format($healthyPct) }}%)</small></strong></div>
                <div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Low Stock</span></div><strong>{{ $inventoryLow }} <small>({{ number_format($lowPct) }}%)</small></strong></div>
                <div class="availability-row descriptive-inventory-critical"><div><span class="availability-dot critical"></span><span>Out of Stock</span></div><strong>{{ $inventoryCritical }} <small>({{ number_format($criticalPct) }}%)</small></strong></div>
            </div>
        </x-analytics.card>
    </div>
</section>

<section class="descriptive-overview-footer-grid">
    @php
        $recentAlertsHeader = '<a href="' . e(route('admin.notifications')) . '">View all alerts <i class="fa-solid fa-arrow-right"></i></a>';
    @endphp
    <x-analytics.card
        title="Recent Alerts"
        :header-actions="$recentAlertsHeader"
    >
        @if($recentAlerts->isNotEmpty())
            <div class="descriptive-alerts-table-wrap">
                <table class="descriptive-alerts-table">
                    <thead><tr><th>Time</th><th>Type</th><th>Entity</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($recentAlerts as $alert)
                            <tr>
                                <td>{{ $alert['date'] }}<br><small>{{ $alert['time'] }}</small></td>
                                <td><span class="descriptive-alert-type {{ strtolower($alert['type']) }}"><i></i>{{ $alert['type'] }}</span></td>
                                <td>
                                    <strong>{{ $alert['module'] ?? 'Alert' }}</strong>
                                    <small style="color: #64748b; margin-left: 4px;">#{{ $alert['reference'] !== '—' && $alert['reference'] !== '\u2014' ? $alert['reference'] : 'General' }}</small>
                                </td>
                                <td><span class="descriptive-alert-state {{ $alert['unread'] ? 'open' : 'resolved' }}">{{ $alert['unread'] ? 'Open' : 'Read' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="analytics-compact-empty"><i class="fa-regular fa-bell-slash"></i><span>No recorded notifications are available.</span></div>
        @endif
    </x-analytics.card>

    <x-analytics.card
        title="Action Summary"
        description="Items that may need review or follow-up"
    >
        @php
            $attentionItems = [
                ['Under Maintenance', $underMaintenance, 'Buses in shop', 'fa-screwdriver-wrench', 'orange'],
                ['Inactive Buses', $inactiveBuses, 'Currently idle', 'fa-bus-simple', 'gray'],
                ['Low Stock Items', $inventoryLow, 'Reorder soon', 'fa-box-open', 'orange'],
                ['Out of Stock', $inventoryCritical, 'Zero on-hand', 'fa-triangle-exclamation', 'red'],
            ];
        @endphp

        <div class="descriptive-attention-grid">
            @foreach($attentionItems as [$label, $value, $detail, $icon, $tone])
                <x-analytics.kpi
                    :label="$label"
                    :value="number_format($value)"
                    :description="$detail"
                    :icon="$icon"
                    :tone="$tone === 'gray' ? 'blue' : ($tone === 'orange' ? 'yellow' : $tone)"
                />
            @endforeach
        </div>
    </x-analytics.card>

    <x-analytics.card
        class="descriptive-quick-insights-card"
        title="Quick Insights"
        :badge="$comparison['label']"
    >
        @php
            $tripsLabel = $comparison['trips'] === null ? 'Trip Volume' : ($comparison['trips'] >= 0 ? 'Trip Volume Growth' : 'Trip Volume Reduction');
            $idleLabel = $comparison['idle'] === null ? 'Idle Time' : ($comparison['idle'] <= 0 ? 'Idling Improvement' : 'Idling Increase');
            $distLabel = $comparison['distance'] === null ? 'Distance Covered' : ($comparison['distance'] >= 0 ? 'Distance Increase' : 'Distance Reduction');

            $insights = [
                ['trips', $tripsLabel, number_format($tripCount) . ' vs ' . number_format($comparison['previousTrips']), 'fa-route', $comparison['trips']],
                ['idle', $idleLabel, number_format($totalIdleMinutes / 60, 1) . ' hrs vs ' . number_format($comparison['previousIdleMinutes'] / 60, 1) . ' hrs', 'fa-hourglass-half', $comparison['idle']],
                ['distance', $distLabel, number_format($totalDistance, 1) . ' km vs ' . number_format($comparison['previousDistance'], 1) . ' km', 'fa-road', $comparison['distance']],
            ];
        @endphp
        <div class="descriptive-insight-grid">
            @foreach($insights as [$key, $label, $detail, $icon, $delta])
                <div class="descriptive-insight-card {{ $delta !== null && $delta < 0 ? 'negative' : 'positive' }}">
                    <span><i class="fa-solid {{ $icon }}"></i></span>
                    <div>
                        <strong>{{ $deltaText($delta) }}</strong>
                        <b>{{ $label }}</b>
                        <small>{{ $detail }}</small>
                    </div>
                </div>
            @endforeach
        </div>
    </x-analytics.card>
</section>