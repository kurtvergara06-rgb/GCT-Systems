<section class="analytics-kpi-strip analytics-domain-kpi-four fuel-reference-kpis">
    <x-analytics.kpi label="Fuel Used" :value="number_format($fuel['totalFuel'] ?? 0, 1) . ' L'" small="Recorded fuel volume" icon="fa-gas-pump" />
    <x-analytics.kpi label="Linked Distance" :value="number_format($fuel['totalDistance'] ?? 0, 1) . ' km'" small="Distance attached to fuel reports" icon="fa-road" tone="green" />
    <x-analytics.kpi label="Weighted Efficiency" :value="number_format($fuel['fleetAverage'] ?? 0, 2) . ' km/L'" small="Distance divided by recorded fuel" icon="fa-gauge-high" tone="purple" />
    <x-analytics.kpi label="Recorded Units" :value="$fuelSummaries->count()" small="Buses represented in reports" icon="fa-bus" tone="yellow" />
</section>

<section class="analytics-domain-content fuel-dashboard-content fuel-reference-dashboard">
    <div class="fuel-dashboard-layout">
        <div class="fuel-dashboard-main-column">
            <article class="analytics-card analytics-domain-card fuel-usage-main-card">
                <div class="analytics-card-header fuel-card-header-with-action">
                    <div>
                        <h3>Fuel Usage by Bus</h3>
                        <p>{{ $periodLabel }} · highest recorded fuel volume by unit</p>
                    </div>
                    <select class="fuel-reference-select" aria-label="Fuel usage ranking">
                        <option>Top 10 by Volume</option>
                    </select>
                </div>

                @if($fuelBusChartData->isNotEmpty())
                    <div class="fuel-usage-chart fuel-usage-chart-large" data-fuel-points='@json($fuelBusChartData)'>
                        <canvas class="fuel-usage-canvas" role="img" aria-label="Fuel usage by bus chart"></canvas>
                        <div class="fuel-usage-tooltip" aria-hidden="true"><strong></strong><span><i class="fa-solid fa-gas-pump"></i> Fuel <b data-fuel-value></b></span><span><i class="fa-solid fa-road"></i> Distance <b data-distance-value></b></span><span><i class="fa-solid fa-gauge-high"></i> Efficiency <b data-efficiency-value></b></span></div>
                    </div>
                    <div class="fuel-usage-caption">Top {{ $fuelUsageRows->count() }} unit{{ $fuelUsageRows->count() === 1 ? '' : 's' }} by recorded fuel volume</div>
                @else
                    <div class="analytics-compact-empty"><i class="fa-regular fa-folder-open"></i><span>No fuel reports match the selected filters.</span></div>
                @endif
            </article>

            <div class="fuel-summary-strip">
                <div class="fuel-summary-cell"><span class="fuel-summary-icon blue"><i class="fa-solid fa-gas-pump"></i></span><div><span>Avg. Fuel per Bus</span><strong>{{ number_format($averageFuelPerBus, 1) }} L</strong><small>Per bus</small></div></div>
                <div class="fuel-summary-cell"><span class="fuel-summary-icon green"><i class="fa-solid fa-road"></i></span><div><span>Avg. Distance per Bus</span><strong>{{ number_format($averageDistancePerBus, 1) }} km</strong><small>Per bus</small></div></div>
                <div class="fuel-summary-cell"><span class="fuel-summary-icon purple"><i class="fa-solid fa-star"></i></span><div><span>Best Efficiency</span><strong>{{ $mostEfficientBus ? number_format($mostEfficientBus->km_per_liter, 2) . ' km/L' : '—' }}</strong><small>{{ $mostEfficientBus?->bus_no ?? 'No data' }}</small></div></div>
                <div class="fuel-summary-cell"><span class="fuel-summary-icon orange"><i class="fa-solid fa-arrow-down"></i></span><div><span>Lowest Efficiency</span><strong>{{ $leastEfficientBus ? number_format($leastEfficientBus->km_per_liter, 2) . ' km/L' : '—' }}</strong><small>{{ $leastEfficientBus?->bus_no ?? 'No data' }}</small></div></div>
            </div>

            <article class="analytics-card analytics-domain-card fuel-details-card">
                <div class="analytics-card-header fuel-details-heading">
                    <div>
                        <h3>Fuel Usage Details</h3>
                        <p>Detailed list of fuel records by bus</p>
                    </div>
                </div>

                @if($fuelSummaries->isNotEmpty())
                    <div class="fuel-table-tools fuel-table-tools-reference">
                        <label class="fuel-table-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search bus or driver..." data-fuel-table-search></label>
                        <div class="fuel-table-action-group">
                            <button type="button" class="fuel-table-action"><i class="fa-solid fa-table-columns"></i> Columns</button>
                            <button type="button" class="fuel-table-action" data-fuel-export><i class="fa-solid fa-download"></i> Export</button>
                        </div>
                    </div>

                    <div class="table-wrap analytics-fuel-table-wrap fuel-details-table-wrap" tabindex="0" aria-label="Fuel usage details table">
                        <table class="analytics-fuel-table" data-fuel-details-table>
                            <thead><tr><th>Bus</th><th>Reports</th><th>Fuel Used (L)</th><th>Distance (km)</th><th>Efficiency (km/L)</th><th>Driver</th><th>Last Report</th></tr></thead>
                            <tbody>
                                @foreach($fuelSummaries as $row)
                                    @php
                                        $latestFuelRecord = $fuelRecords
                                            ->filter(fn ($record) => strtoupper(trim((string) $record->bus_no)) === strtoupper(trim((string) $row->bus_no)))
                                            ->sortByDesc(fn ($record) => optional($record->report_date)->format('Y-m-d') . '-' . str_pad((string) $record->id, 10, '0', STR_PAD_LEFT))
                                            ->first();
                                        $driverName = trim((string) ($latestFuelRecord?->driver_name ?? '')) ?: '—';
                                        $lastReport = $latestFuelRecord?->report_date?->format('M j, Y') ?? '—';
                                    @endphp
                                    <tr data-fuel-bus="{{ strtolower($row->bus_no) }}" data-fuel-search="{{ strtolower($row->bus_no . ' ' . $driverName) }}">
                                        <td><strong>{{ $row->bus_no }}</strong></td>
                                        <td>{{ $row->entries }}</td>
                                        <td>{{ number_format($row->fuel_liters, 1) }}</td>
                                        <td>{{ number_format($row->distance_km, 1) }}</td>
                                        <td><span class="fuel-efficiency-value">{{ number_format($row->km_per_liter, 2) }}</span></td>
                                        <td>{{ $driverName }}</td>
                                        <td>{{ $lastReport }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="fuel-table-footer" data-fuel-table-footer>
                        <span data-fuel-table-meta>Showing 1 to {{ min(10, $fuelSummaries->count()) }} of {{ $fuelSummaries->count() }} entries</span>
                        <div class="fuel-table-pagination">
                            <button type="button" class="fuel-page-arrow" data-fuel-page-prev aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></button>
                            <div data-fuel-page-numbers></div>
                            <button type="button" class="fuel-page-arrow" data-fuel-page-next aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></button>
                            <span class="fuel-page-size">10 / page</span>
                        </div>
                    </div>
                @else
                    <div class="analytics-compact-empty"><i class="fa-regular fa-folder-open"></i><span>No bus-level fuel records are available.</span></div>
                @endif
            </article>
        </div>

        <aside class="fuel-dashboard-side-column">
            <article class="analytics-card descriptive-availability-card fuel-side-card fuel-fleet-card">
                <x-analytics.card-header title="Fleet Availability" description="Current Bus Master List status." :badge="$totalBuses . ' buses'" />
                <div class="analytics-availability-layout fuel-availability-layout">
                    <div class="availability-score"><div class="fleet-css-donut fuel-fleet-donut" data-default-value="{{ number_format($fleetAvailability, 1) }}%" data-default-label="Active" data-active="{{ number_format($activePct, 2, '.', '') }}" data-maintenance="{{ number_format($maintenancePct, 2, '.', '') }}" data-inactive="{{ number_format($inactivePct, 2, '.', '') }}" style="--fleet-active: {{ number_format($activePct, 2, '.', '') }}%; --fleet-maintenance-end: {{ number_format($maintenanceEndPct, 2, '.', '') }}%;"><div class="fleet-css-donut-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div><div class="fleet-css-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div></div></div>
                    <div class="availability-breakdown"><div class="availability-row" data-donut-index="0" data-label="Active" data-value="{{ $activeBuses }}" data-percentage="{{ number_format($activePct, 1, '.', '') }}"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div><div class="availability-row" data-donut-index="1" data-label="Under Maintenance" data-value="{{ $underMaintenance }}" data-percentage="{{ number_format($maintenancePct, 1, '.', '') }}"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div><div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div><div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div></div>
                </div>
            </article>

            <article class="analytics-card fuel-side-card fuel-quality-card">
                <x-analytics.card-header title="Fuel Quality Checks" description="Completeness of selected fuel records" :badge="$periodLabel" />
                <div class="fuel-quality-body"><span class="fuel-quality-icon"><i class="fa-solid fa-shield-halved"></i></span><div class="fuel-quality-score"><strong>{{ number_format($fuelQualityPct, 1) }}%</strong><span>Pass Rate</span></div><div class="fuel-quality-counts"><div><span class="availability-dot operational"></span><span>Passed</span><strong>{{ $validFuelRecords }}</strong></div><div><span class="availability-dot inactive"></span><span>Failed</span><strong>{{ $incompleteFuelRecords }}</strong></div><div class="fuel-quality-total"><span>Total Checks</span><strong>{{ $fuelRecords->count() }}</strong></div></div></div>
            </article>

            <article class="analytics-card fuel-side-card fuel-review-card">
                <x-analytics.card-header title="Anomaly Alerts" description="Units flagged by recorded fuel signals" :badge="$periodLabel" />
                <div class="fuel-review-summary"><span class="fuel-review-alert"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $fuelReviewUnits->count() }}</strong><span>Total Alerts</span></div></div>
                <div class="fuel-review-lines"><div><span>Priority efficiency</span><strong>{{ $priorityFuelUnits }}</strong></div><div><span>Unusual efficiency</span><strong>{{ $reviewFuelUnits }}</strong></div><div><span>High idling</span><strong>{{ $highIdlingUnits->count() }}</strong></div></div>
            </article>

            <article class="analytics-card fuel-side-card fuel-distribution-card">
                <x-analytics.card-header title="Efficiency Distribution" description="Bus-level efficiency bands from selected fuel records" :badge="$periodLabel" />
                @php
                    $noDataFuelUnits = max(0, $fuelSummaries->count() - ($efficientFuelUnits + $normalFuelUnits + $reviewFuelUnits + $priorityFuelUnits));
                    $distributionTotal = max(1, $fuelSummaries->count());
                    $noDataFuelPct = ($noDataFuelUnits / $distributionTotal) * 100;
                @endphp
                <div class="fuel-efficiency-distribution">
                    <div class="fuel-distribution-bar"><span class="efficient" style="width: {{ $efficientFuelPct }}%"></span><span class="normal" style="width: {{ $normalFuelPct }}%"></span><span class="review" style="width: {{ $reviewFuelPct }}%"></span><span class="priority" style="width: {{ $priorityFuelPct }}%"></span><span class="no-data" style="width: {{ $noDataFuelPct }}%"></span></div>
                    <div class="fuel-distribution-legend five"><div><strong>{{ number_format($efficientFuelPct, 1) }}%</strong><span>High</span></div><div><strong>{{ number_format($normalFuelPct, 1) }}%</strong><span>Good</span></div><div><strong>{{ number_format($reviewFuelPct, 1) }}%</strong><span>Low</span></div><div><strong>{{ number_format($priorityFuelPct, 1) }}%</strong><span>Poor</span></div><div><strong>{{ number_format($noDataFuelPct, 1) }}%</strong><span>No Data</span></div></div>
                </div>
            </article>

            <article class="analytics-card fuel-side-card fuel-trend-card">
                <div class="analytics-card-header fuel-card-header-with-action">
                    <div><h3>Fuel Consumption Trend</h3><p>{{ $fuel['trendLabel'] ?? 'Last 7 Days' }} · recorded fuel volume by day</p></div>
                    <form method="GET" action="{{ url('/analytics/descriptive') }}" class="fuel-trend-window-form">
                        <input type="hidden" name="domain" value="fuel">
                        <input type="hidden" name="period" value="{{ $period }}">
                        @if($selectedBus !== 'ALL' && strtolower($selectedBus) !== 'all')<input type="hidden" name="bus" value="{{ $selectedBus }}">@endif
                        <select name="fuel_trend" class="fuel-reference-select" onchange="this.form.submit()" aria-label="Fuel trend window">
                            <option value="7-days" @selected(($fuel['trendWindow'] ?? '7-days') === '7-days')>Last 7 Days</option>
                            <option value="14-days" @selected(($fuel['trendWindow'] ?? '') === '14-days')>Last 14 Days</option>
                            <option value="30-days" @selected(($fuel['trendWindow'] ?? '') === '30-days')>Last 30 Days</option>
                        </select>
                    </form>
                </div>
                <x-analytics.line-chart :items="$fuel['trend'] ?? collect()" value-key="fuel_liters" label-key="label" suffix=" L" empty-text="No fuel trend is available for the selected filters." />
            </article>
        </aside>
    </div>
</section>
