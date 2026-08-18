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
                        <div class="fuel-usage-tooltip" aria-hidden="true"><strong></strong><span><i class="fa-solid fa-gas-pump"></i> Fuel <b data-fuel-value></b></span><span><i class="fa-solid fa-road"></i> Distance <b data-distance-value></b></span><span><i class="fa-solid fa-gauge-high"></i> Efficiency <b data-efficiency-value></b></span></div>
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
                    <div class="fuel-table-tools"><label class="fuel-table-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search bus..." data-fuel-table-search></label><span>{{ $fuelSummaries->count() }} recorded unit{{ $fuelSummaries->count() === 1 ? '' : 's' }}</span></div>
                    <div class="table-wrap analytics-fuel-table-wrap fuel-details-table-wrap" tabindex="0" aria-label="Scrollable fuel usage details table">
                        <table class="analytics-fuel-table" data-fuel-details-table>
                            <thead><tr><th>Bus</th><th>Reports</th><th>Fuel Used</th><th>Distance</th><th>Efficiency</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($fuelSummaries as $row)
                                    @php
                                        $fuelStatusClass = \Illuminate\Support\Str::slug((string) $row->status);
                                    @endphp
                                    <tr data-fuel-bus="{{ strtolower($row->bus_no) }}"><td><strong>{{ $row->bus_no }}</strong></td><td>{{ $row->entries }}</td><td>{{ number_format($row->fuel_liters, 1) }} L</td><td>{{ number_format($row->distance_km, 1) }} km</td><td><span class="fuel-efficiency-value">{{ number_format($row->km_per_liter, 2) }} km/L</span></td><td><span class="fuel-status-pill fuel-status-{{ $fuelStatusClass }}">{{ $row->status }}</span></td></tr>
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
                    <div class="availability-score"><div class="fleet-css-donut fuel-fleet-donut" data-default-value="{{ number_format($fleetAvailability, 1) }}%" data-default-label="Active" data-active="{{ number_format($activePct, 2, '.', '') }}" data-maintenance="{{ number_format($maintenancePct, 2, '.', '') }}" data-inactive="{{ number_format($inactivePct, 2, '.', '') }}" style="--fleet-active: {{ number_format($activePct, 2, '.', '') }}%; --fleet-maintenance-end: {{ number_format($maintenanceEndPct, 2, '.', '') }}%;"><div class="fleet-css-donut-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div><div class="fleet-css-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div></div></div>
                    <div class="availability-breakdown"><div class="availability-row" data-donut-index="0" data-label="Active" data-value="{{ $activeBuses }}" data-percentage="{{ number_format($activePct, 1, '.', '') }}"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div><div class="availability-row" data-donut-index="1" data-label="Under Maintenance" data-value="{{ $underMaintenance }}" data-percentage="{{ number_format($maintenancePct, 1, '.', '') }}"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div><div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div><div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div></div>
                </div>
            </article>

            <article class="analytics-card fuel-side-card"><x-analytics.card-header title="Fuel Data Quality" :description="$periodLabel . ' · completeness of recorded fuel entries'" /><div class="fuel-quality-body"><span class="fuel-quality-icon"><i class="fa-solid fa-shield-halved"></i></span><div class="fuel-quality-score"><strong>{{ number_format($fuelQualityPct, 1) }}%</strong><span>Complete records</span></div><div class="fuel-quality-counts"><div><span class="availability-dot operational"></span><span>Complete</span><strong>{{ $validFuelRecords }}</strong></div><div><span class="availability-dot inactive"></span><span>Incomplete</span><strong>{{ $incompleteFuelRecords }}</strong></div></div></div></article>
            <article class="analytics-card fuel-side-card fuel-review-card"><x-analytics.card-header title="Review Signals" :description="$periodLabel . ' · units flagged by recorded efficiency or idling signals'" /><div class="fuel-review-summary"><span class="fuel-review-alert"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $fuelReviewUnits->count() }}</strong><span>Units needing review</span></div></div><div class="fuel-review-lines"><div><span>Priority efficiency</span><strong>{{ $priorityFuelUnits }}</strong></div><div><span>Review efficiency</span><strong>{{ $reviewFuelUnits }}</strong></div><div><span>High idling</span><strong>{{ $highIdlingUnits->count() }}</strong></div></div></article>
            <article class="analytics-card fuel-side-card"><x-analytics.card-header title="Efficiency Distribution" description="Bus-level status from the selected fuel records" /><div class="fuel-efficiency-distribution"><div class="fuel-distribution-bar"><span class="efficient" style="width: {{ $efficientFuelPct }}%"></span><span class="normal" style="width: {{ $normalFuelPct }}%"></span><span class="review" style="width: {{ $reviewFuelPct }}%"></span><span class="priority" style="width: {{ $priorityFuelPct }}%"></span></div><div class="fuel-distribution-legend"><div><strong>{{ $efficientFuelUnits }}</strong><span>Efficient</span></div><div><strong>{{ $normalFuelUnits }}</strong><span>Normal</span></div><div><strong>{{ $reviewFuelUnits }}</strong><span>Review</span></div><div><strong>{{ $priorityFuelUnits }}</strong><span>Priority</span></div></div></div></article>
            <article class="analytics-card fuel-side-card fuel-trend-card"><x-analytics.card-header title="Fuel Consumption Trend" :description="$periodLabel . ' · recorded fuel volume by period'" /><x-analytics.line-chart :items="$fuel['trend'] ?? collect()" value-key="fuel_liters" label-key="label" suffix=" L" empty-text="No fuel trend is available for the selected filters." /></article>
        </aside>
    </div>
</section>
