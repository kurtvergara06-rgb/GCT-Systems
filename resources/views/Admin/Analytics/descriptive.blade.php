@php
    $tabs = [
        'all' => ['All', 'fa-table-cells-large'],
        'fleet-trip' => ['Fleet & Trip', 'fa-route'],
        'fuel' => ['Fuel', 'fa-gas-pump'],
        'bus-health' => ['Bus Health', 'fa-heart-pulse'],
        'inventory' => ['Inventory', 'fa-boxes-stacked'],
    ];

    $trendCount = max(1, $trend->count());
    $tripChartData = $trend->values()->map(function ($bucket, $index) use ($trendCount) {
        return [
            'label' => $bucket->label,
            'value' => (int) $bucket->count,
            'partial' => $index === $trendCount - 1,
        ];
    });
    $hasPartialBucket = $tripChartData->contains(fn ($point) => $point['partial']);

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

    $activePct = $totalBuses > 0 ? ($activeBuses / $totalBuses) * 100 : 0;
    $maintenancePct = $totalBuses > 0 ? ($underMaintenance / $totalBuses) * 100 : 0;
    $inactivePct = $totalBuses > 0 ? ($inactiveBuses / $totalBuses) * 100 : 0;
    $maintenanceEndPct = $activePct + $maintenancePct;

    $fuelSummaries = collect($fuel['busSummaries'] ?? [])->values();
    $fuelRecords = collect($fuel['records'] ?? [])->values();
    $fuelUsageRows = $fuelSummaries->sortByDesc('fuel_liters')->take(10)->values();
    $fuelBusChartData = $fuelUsageRows->map(fn ($row) => [
        'label' => $row->bus_no,
        'fuel' => (float) $row->fuel_liters,
        'distance' => (float) $row->distance_km,
        'efficiency' => (float) $row->km_per_liter,
        'entries' => (int) $row->entries,
    ]);
    $highestFuelBus = $fuelSummaries->sortByDesc('fuel_liters')->first();
    $lowestFuelBus = $fuelSummaries->filter(fn ($row) => $row->fuel_liters > 0)->sortBy('fuel_liters')->first();
    $mostEfficientBus = $fuelSummaries->filter(fn ($row) => $row->km_per_liter > 0)->sortByDesc('km_per_liter')->first();
    $leastEfficientBus = $fuelSummaries->filter(fn ($row) => $row->km_per_liter > 0)->sortBy('km_per_liter')->first();
    $averageFuelPerBus = $fuelSummaries->count() > 0 ? ((float) ($fuel['totalFuel'] ?? 0) / $fuelSummaries->count()) : 0;
    $averageDistancePerBus = $fuelSummaries->count() > 0 ? ((float) ($fuel['totalDistance'] ?? 0) / $fuelSummaries->count()) : 0;

    $validFuelRecords = $fuelRecords->filter(fn ($row) => (float) ($row->fuel_liters ?? 0) > 0 && (float) ($row->distance_km ?? 0) > 0)->count();
    $incompleteFuelRecords = max(0, $fuelRecords->count() - $validFuelRecords);
    $fuelQualityPct = $fuelRecords->count() > 0 ? ($validFuelRecords / $fuelRecords->count()) * 100 : 0;

    $fuelReviewUnits = collect($fuel['reviewUnits'] ?? [])->values();
    $highIdlingUnits = collect($fuel['highIdlingUnits'] ?? [])->values();
    $priorityFuelUnits = $fuelSummaries->where('status', 'Priority Review')->count();
    $reviewFuelUnits = $fuelSummaries->where('status', 'Review')->count();
    $efficientFuelUnits = $fuelSummaries->where('status', 'Efficient')->count();
    $normalFuelUnits = $fuelSummaries->where('status', 'Normal')->count();
    $fuelStatusTotal = max(1, $fuelSummaries->count());
    $efficientFuelPct = ($efficientFuelUnits / $fuelStatusTotal) * 100;
    $normalFuelPct = ($normalFuelUnits / $fuelStatusTotal) * 100;
    $reviewFuelPct = ($reviewFuelUnits / $fuelStatusTotal) * 100;
    $priorityFuelPct = ($priorityFuelUnits / $fuelStatusTotal) * 100;
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
                    <x-analytics.kpi label="Recorded Units" :value="$fuelSummaries->count()" small="Buses represented in reports" icon="fa-bus" tone="yellow" />
                </section>

                <section class="analytics-domain-content fuel-dashboard-content">
                    <div class="fuel-dashboard-layout">
                        <div class="fuel-dashboard-main-column">
                            <x-analytics.panel title="Fuel Usage by Bus" :description="$periodLabel . ' · highest recorded fuel volume by unit'">
                                @if($fuelBusChartData->isNotEmpty())
                                    <div class="fuel-usage-chart fuel-usage-chart-large" data-fuel-points='@json($fuelBusChartData)'>
                                        <canvas class="fuel-usage-canvas" role="img" aria-label="Fuel usage by bus chart"></canvas>
                                        <div class="fuel-usage-tooltip" aria-hidden="true">
                                            <strong></strong>
                                            <span><i class="fa-solid fa-gas-pump"></i> Fuel <b data-fuel-value></b></span>
                                            <span><i class="fa-solid fa-road"></i> Distance <b data-distance-value></b></span>
                                            <span><i class="fa-solid fa-gauge-high"></i> Efficiency <b data-efficiency-value></b></span>
                                        </div>
                                    </div>
                                    <div class="fuel-usage-caption">Top {{ $fuelUsageRows->count() }} unit{{ $fuelUsageRows->count() === 1 ? '' : 's' }} by recorded fuel volume</div>
                                @else
                                    <div class="analytics-compact-empty"><i class="fa-regular fa-folder-open"></i><span>No fuel reports match the selected filters.</span></div>
                                @endif
                            </x-analytics.panel>

                            <div class="fuel-summary-strip">
                                <div class="fuel-summary-cell"><span class="fuel-summary-icon blue"><i class="fa-solid fa-gas-pump"></i></span><div><span>Avg. Fuel per Bus</span><strong>{{ number_format($averageFuelPerBus, 1) }} L</strong><small>Per recorded unit</small></div></div>
                                <div class="fuel-summary-cell"><span class="fuel-summary-icon green"><i class="fa-solid fa-road"></i></span><div><span>Avg. Distance per Bus</span><strong>{{ number_format($averageDistancePerBus, 1) }} km</strong><small>Per recorded unit</small></div></div>
                                <div class="fuel-summary-cell"><span class="fuel-summary-icon purple"><i class="fa-solid fa-gauge-high"></i></span><div><span>Best Efficiency</span><strong>{{ $mostEfficientBus ? number_format($mostEfficientBus->km_per_liter, 2) . ' km/L' : '—' }}</strong><small>{{ $mostEfficientBus?->bus_no ?? 'No data' }}</small></div></div>
                                <div class="fuel-summary-cell"><span class="fuel-summary-icon orange"><i class="fa-solid fa-arrow-trend-down"></i></span><div><span>Lowest Efficiency</span><strong>{{ $leastEfficientBus ? number_format($leastEfficientBus->km_per_liter, 2) . ' km/L' : '—' }}</strong><small>{{ $leastEfficientBus?->bus_no ?? 'No data' }}</small></div></div>
                            </div>

                            <x-analytics.panel title="Fuel Usage Details" description="Recorded distance, fuel usage, efficiency, and review status by bus">
                                @if($fuelSummaries->isNotEmpty())
                                    <div class="fuel-table-tools">
                                        <label class="fuel-table-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search bus..." data-fuel-table-search></label>
                                        <span>{{ $fuelSummaries->count() }} recorded unit{{ $fuelSummaries->count() === 1 ? '' : 's' }}</span>
                                    </div>
                                    <div class="table-wrap analytics-fuel-table-wrap fuel-details-table-wrap" tabindex="0" aria-label="Scrollable fuel usage details table">
                                        <table class="analytics-fuel-table" data-fuel-details-table>
                                            <thead><tr><th>Bus</th><th>Reports</th><th>Fuel Used</th><th>Distance</th><th>Efficiency</th><th>Status</th></tr></thead>
                                            <tbody>
                                                @foreach($fuelSummaries as $row)
                                                    @php
                                                        $fuelStatusClass = \Illuminate\Support\Str::slug((string) $row->status);
                                                    @endphp
                                                    <tr data-fuel-bus="{{ strtolower($row->bus_no) }}">
                                                        <td><strong>{{ $row->bus_no }}</strong></td>
                                                        <td>{{ $row->entries }}</td>
                                                        <td>{{ number_format($row->fuel_liters, 1) }} L</td>
                                                        <td>{{ number_format($row->distance_km, 1) }} km</td>
                                                        <td><span class="fuel-efficiency-value">{{ number_format($row->km_per_liter, 2) }} km/L</span></td>
                                                        <td><span class="fuel-status-pill fuel-status-{{ $fuelStatusClass }}">{{ $row->status }}</span></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="analytics-compact-empty"><i class="fa-regular fa-folder-open"></i><span>No bus-level fuel records are available.</span></div>
                                @endif
                            </x-analytics.panel>
                        </div>

                        <aside class="fuel-dashboard-side-column">
                            <article class="analytics-card descriptive-availability-card fuel-side-card fuel-fleet-card">
                                <x-analytics.card-header title="Fleet Availability" description="Current Bus Master List status." :badge="$totalBuses . ' buses'" />
                                <div class="analytics-availability-layout fuel-availability-layout">
                                    <div class="availability-score">
                                        <div class="fleet-css-donut fuel-fleet-donut" data-default-value="{{ number_format($fleetAvailability, 1) }}%" data-default-label="Active" data-active="{{ number_format($activePct, 2, '.', '') }}" data-maintenance="{{ number_format($maintenancePct, 2, '.', '') }}" data-inactive="{{ number_format($inactivePct, 2, '.', '') }}" style="--fleet-active: {{ number_format($activePct, 2, '.', '') }}%; --fleet-maintenance-end: {{ number_format($maintenanceEndPct, 2, '.', '') }}%;">
                                            <div class="fleet-css-donut-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div>
                                            <div class="fleet-css-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div>
                                        </div>
                                    </div>
                                    <div class="availability-breakdown">
                                        <div class="availability-row" data-donut-index="0" data-label="Active" data-value="{{ $activeBuses }}" data-percentage="{{ number_format($activePct, 1, '.', '') }}"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div>
                                        <div class="availability-row" data-donut-index="1" data-label="Under Maintenance" data-value="{{ $underMaintenance }}" data-percentage="{{ number_format($maintenancePct, 1, '.', '') }}"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div>
                                        <div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div>
                                        <div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div>
                                    </div>
                                </div>
                            </article>

                            <article class="analytics-card fuel-side-card">
                                <x-analytics.card-header title="Fuel Data Quality" :description="$periodLabel . ' · completeness of recorded fuel entries'" />
                                <div class="fuel-quality-body">
                                    <span class="fuel-quality-icon"><i class="fa-solid fa-shield-halved"></i></span>
                                    <div class="fuel-quality-score"><strong>{{ number_format($fuelQualityPct, 1) }}%</strong><span>Complete records</span></div>
                                    <div class="fuel-quality-counts"><div><span class="availability-dot operational"></span><span>Complete</span><strong>{{ $validFuelRecords }}</strong></div><div><span class="availability-dot inactive"></span><span>Incomplete</span><strong>{{ $incompleteFuelRecords }}</strong></div></div>
                                </div>
                            </article>

                            <article class="analytics-card fuel-side-card fuel-review-card">
                                <x-analytics.card-header title="Review Signals" :description="$periodLabel . ' · units flagged by recorded efficiency or idling signals'" />
                                <div class="fuel-review-summary"><span class="fuel-review-alert"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $fuelReviewUnits->count() }}</strong><span>Units needing review</span></div></div>
                                <div class="fuel-review-lines"><div><span>Priority efficiency</span><strong>{{ $priorityFuelUnits }}</strong></div><div><span>Review efficiency</span><strong>{{ $reviewFuelUnits }}</strong></div><div><span>High idling</span><strong>{{ $highIdlingUnits->count() }}</strong></div></div>
                            </article>

                            <article class="analytics-card fuel-side-card">
                                <x-analytics.card-header title="Efficiency Distribution" description="Bus-level status from the selected fuel records" />
                                <div class="fuel-efficiency-distribution">
                                    <div class="fuel-distribution-bar"><span class="efficient" style="width: {{ $efficientFuelPct }}%"></span><span class="normal" style="width: {{ $normalFuelPct }}%"></span><span class="review" style="width: {{ $reviewFuelPct }}%"></span><span class="priority" style="width: {{ $priorityFuelPct }}%"></span></div>
                                    <div class="fuel-distribution-legend"><div><strong>{{ $efficientFuelUnits }}</strong><span>Efficient</span></div><div><strong>{{ $normalFuelUnits }}</strong><span>Normal</span></div><div><strong>{{ $reviewFuelUnits }}</strong><span>Review</span></div><div><strong>{{ $priorityFuelUnits }}</strong><span>Priority</span></div></div>
                                </div>
                            </article>

                            <article class="analytics-card fuel-side-card fuel-trend-card">
                                <x-analytics.card-header title="Fuel Consumption Trend" :description="$periodLabel . ' · recorded fuel volume by period'" />
                                <x-analytics.line-chart :items="$fuel['trend'] ?? collect()" value-key="fuel_liters" label-key="label" suffix=" L" empty-text="No fuel trend is available for the selected filters." />
                            </article>
                        </aside>
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
                        <div class="trip-canvas-chart" data-trip-points='@json($tripChartData)'>
                            <canvas class="trip-canvas" role="img" aria-label="Processed trip activity chart"></canvas>
                            <div class="trip-canvas-tooltip" aria-hidden="true"><strong></strong><span><i></i> Trips Processed <b></b></span></div>
                        </div>
                        <div class="trip-canvas-legend"><span><i></i> Trips Processed</span>@if($hasPartialBucket)<span class="trip-canvas-partial-note"><i class="fa-regular fa-clock"></i> Current bucket is partial</span>@endif</div>
                    </article>

                    <article class="analytics-card descriptive-availability-card">
                        <x-analytics.card-header title="Fleet Availability" description="Current Bus Master List status." :badge="$totalBuses . ' buses'" />
                        <div class="analytics-availability-layout">
                            <div class="availability-score">
                                <div class="fleet-css-donut" data-default-value="{{ number_format($fleetAvailability, 1) }}%" data-default-label="Active" data-active="{{ number_format($activePct, 2, '.', '') }}" data-maintenance="{{ number_format($maintenancePct, 2, '.', '') }}" data-inactive="{{ number_format($inactivePct, 2, '.', '') }}" style="--fleet-active: {{ number_format($activePct, 2, '.', '') }}%; --fleet-maintenance-end: {{ number_format($maintenanceEndPct, 2, '.', '') }}%;">
                                    <div class="fleet-css-donut-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div>
                                    <div class="fleet-css-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div>
                                </div>
                            </div>
                            <div class="availability-breakdown">
                                <div class="availability-row" data-donut-index="0" data-label="Active" data-value="{{ $activeBuses }}" data-percentage="{{ number_format($activePct, 1, '.', '') }}"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div>
                                <div class="availability-row" data-donut-index="1" data-label="Under Maintenance" data-value="{{ $underMaintenance }}" data-percentage="{{ number_format($maintenancePct, 1, '.', '') }}"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div>
                                <div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div>
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
