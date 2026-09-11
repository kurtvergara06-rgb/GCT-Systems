<section class="analytics-kpi-strip">
    <x-analytics.kpi
        icon="fa-gas-pump"
        label="Fuel Used"
        :value="number_format($fuel['totalFuel'] ?? 0, 1) . ' L'"
        description="Recorded fuel volume"
        icon-variant="blue"
    />
    <x-analytics.kpi
        icon="fa-road"
        label="Linked Distance"
        :value="number_format($fuel['totalDistance'] ?? 0, 1) . ' km'"
        description="Distance attached to fuel reports"
        icon-variant="green"
    />
    <x-analytics.kpi
        icon="fa-gauge-high"
        label="Weighted Efficiency"
        :value="number_format($fuel['fleetAverage'] ?? 0, 2) . ' km/L'"
        description="Distance divided by recorded fuel"
        icon-variant="purple"
    />
    <x-analytics.kpi
        icon="fa-bus"
        label="Recorded Units"
        :value="$fuelSummaries->count()"
        description="Buses represented in reports"
        icon-variant="yellow"
    />
</section>

<section class="analytics-domain-content fuel-dashboard-content fuel-reference-dashboard">
    <div class="fuel-dashboard-layout">
        <div class="fuel-dashboard-main-column">
            <x-analytics.card class="analytics-domain-card fuel-usage-main-card" title="Fuel Usage by Bus" :description="$periodLabel . ' · highest recorded fuel volume by unit'">
                <x-slot:headerActions>
                    <select class="fuel-reference-select" aria-label="Fuel usage ranking">
                        <option>Top 10 by Volume</option>
                    </select>
                </x-slot:headerActions>

                @if($fuelBusChartData->isNotEmpty())
                    <div class="fuel-usage-chart fuel-usage-chart-large" data-fuel-points='@json($fuelBusChartData)'>
                        <canvas class="fuel-usage-canvas" role="img" aria-label="Fuel usage by bus chart"></canvas>
                        <div class="fuel-usage-tooltip" aria-hidden="true"><strong></strong><span><i class="fa-solid fa-gas-pump"></i> Fuel <b data-fuel-value></b></span><span><i class="fa-solid fa-road"></i> Distance <b data-distance-value></b></span><span><i class="fa-solid fa-gauge-high"></i> Efficiency <b data-efficiency-value></b></span></div>
                    </div>
                    <div class="fuel-usage-caption">Top {{ $fuelUsageRows->count() }} unit{{ $fuelUsageRows->count() === 1 ? '' : 's' }} by recorded fuel volume</div>
                @else
                    <div class="analytics-compact-empty"><i class="fa-regular fa-folder-open"></i><span>No fuel reports match the selected filters.</span></div>
                @endif
            </x-analytics.card>

            <div class="fuel-summary-strip">
                <x-analytics.kpi
                    label="Avg. Fuel per Bus"
                    :value="number_format($averageFuelPerBus, 1) . ' L'"
                    description="Per bus"
                    icon="fa-gas-pump"
                    icon-variant="blue"
                />
                <x-analytics.kpi
                    label="Avg. Distance per Bus"
                    :value="number_format($averageDistancePerBus, 1) . ' km'"
                    description="Per bus"
                    icon="fa-road"
                    icon-variant="green"
                />
                <x-analytics.kpi
                    label="Best Efficiency"
                    :value="$mostEfficientBus ? number_format($mostEfficientBus->km_per_liter, 2) . ' km/L' : '—'"
                    :description="$mostEfficientBus?->bus_no ?? 'No data'"
                    icon="fa-star"
                    icon-variant="purple"
                />
                <x-analytics.kpi
                    label="Lowest Efficiency"
                    :value="$leastEfficientBus ? number_format($leastEfficientBus->km_per_liter, 2) . ' km/L' : '—'"
                    :description="$leastEfficientBus?->bus_no ?? 'No data'"
                    icon="fa-arrow-down"
                    icon-variant="yellow"
                />
            </div>

            <x-analytics.card class="analytics-domain-card fuel-details-card" title="Fuel Usage Details" description="Recorded distance, fuel usage, efficiency, and review status by bus">
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
            </x-analytics.card>
        </div>

        <aside class="fuel-dashboard-side-column">
            <x-analytics.card class="descriptive-availability-card fuel-fleet-card" title="Fleet Availability" description="Current Bus Master List status." :badge="$totalBuses . ' buses'">
                <div class="analytics-availability-layout fuel-availability-layout">
                    <div class="availability-score"><div class="fleet-css-donut fuel-fleet-donut" data-default-value="{{ number_format($fleetAvailability, 1) }}%" data-default-label="Active" data-active="{{ number_format($activePct, 2, '.', '') }}" data-maintenance="{{ number_format($maintenancePct, 2, '.', '') }}" data-inactive="{{ number_format($inactivePct, 2, '.', '') }}" style="--fleet-active: {{ number_format($activePct, 2, '.', '') }}%; --fleet-maintenance-end: {{ number_format($maintenanceEndPct, 2, '.', '') }}%;"><div class="fleet-css-donut-center"><strong>{{ number_format($fleetAvailability, 1) }}%</strong><span>Active</span></div><div class="fleet-css-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div></div></div>
                    <div class="availability-breakdown"><div class="availability-row" data-donut-index="0" data-label="Active" data-value="{{ $activeBuses }}" data-percentage="{{ number_format($activePct, 1, '.', '') }}"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }}</strong></div><div class="availability-row" data-donut-index="1" data-label="Under Maintenance" data-value="{{ $underMaintenance }}" data-percentage="{{ number_format($maintenancePct, 1, '.', '') }}"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }}</strong></div><div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }}</strong></div><div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div></div>
                </div>
            </x-analytics.card>

            <x-analytics.card class="fuel-quality-card" title="Fuel Quality Checks" description="Completeness of selected fuel records" :badge="$periodLabel">
                <div class="fuel-quality-body"><span class="fuel-quality-icon"><i class="fa-solid fa-shield-halved"></i></span><div class="fuel-quality-score"><strong>{{ number_format($fuelQualityPct, 1) }}%</strong><span>Pass Rate</span></div><div class="fuel-quality-counts"><div><span class="availability-dot operational"></span><span>Passed</span><strong>{{ $validFuelRecords }}</strong></div><div><span class="availability-dot inactive"></span><span>Failed</span><strong>{{ $incompleteFuelRecords }}</strong></div><div class="fuel-quality-total"><span>Total Checks</span><strong>{{ $fuelRecords->count() }}</strong></div></div></div>
            </x-analytics.card>

            <x-analytics.card class="fuel-review-card" title="Anomaly Alerts" description="Units flagged by recorded fuel signals" :badge="$periodLabel">
                <div class="fuel-review-summary"><span class="fuel-review-alert"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $fuelReviewUnits->count() }}</strong><span>Total Alerts</span></div></div>
                <div class="fuel-review-lines"><div><span>Priority efficiency</span><strong>{{ $priorityFuelUnits }}</strong></div><div><span>Unusual efficiency</span><strong>{{ $reviewFuelUnits }}</strong></div><div><span>High idling</span><strong>{{ $highIdlingUnits->count() }}</strong></div></div>
            </x-analytics.card>

            <x-analytics.card class="fuel-distribution-card" title="Efficiency Distribution" description="Bus-level efficiency bands from selected fuel records" :badge="$periodLabel">
                @php
                    $noDataFuelUnits = max(0, $fuelSummaries->count() - ($efficientFuelUnits + $normalFuelUnits + $reviewFuelUnits + $priorityFuelUnits));
                    $distributionTotal = max(1, $fuelSummaries->count());
                    $noDataFuelPct = ($noDataFuelUnits / $distributionTotal) * 100;
                @endphp
                <div class="fuel-efficiency-distribution">
                    <div class="fuel-distribution-bar"><span class="efficient" style="width: {{ $efficientFuelPct }}%"></span><span class="normal" style="width: {{ $normalFuelPct }}%"></span><span class="review" style="width: {{ $reviewFuelPct }}%"></span><span class="priority" style="width: {{ $priorityFuelPct }}%"></span><span class="no-data" style="width: {{ $noDataFuelPct }}%"></span></div>
                    <div class="fuel-distribution-legend five"><div><strong>{{ number_format($efficientFuelPct, 1) }}%</strong><span>High</span></div><div><strong>{{ number_format($normalFuelPct, 1) }}%</strong><span>Good</span></div><div><strong>{{ number_format($reviewFuelPct, 1) }}%</strong><span>Low</span></div><div><strong>{{ number_format($priorityFuelPct, 1) }}%</strong><span>Poor</span></div><div><strong>{{ number_format($noDataFuelPct, 1) }}%</strong><span>No Data</span></div></div>
                </div>
            </x-analytics.card>

            <x-analytics.card class="fuel-trend-card" :title="'Fuel Consumption Trend'" :description="($fuel['trendLabel'] ?? 'Last 7 Days') . ' · recorded fuel volume by day'">
                <x-slot:headerActions>
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
                </x-slot:headerActions>
                <x-analytics.line-chart :items="$fuel['trend'] ?? collect()" value-key="fuel_liters" label-key="label" suffix=" L" empty-text="No fuel trend is available for the selected filters." />
            </x-analytics.card>
        </aside>
    </div>
</section>

<style>
.fuel-reference-kpis{gap:10px!important;margin-bottom:12px!important}.fuel-reference-kpis>.analytics-kpi{min-height:104px!important;padding:14px 16px!important;border-radius:13px!important}.fuel-reference-dashboard .fuel-dashboard-layout{grid-template-columns:minmax(0,1.78fr) minmax(330px,.92fr)!important;gap:12px!important}.fuel-reference-dashboard .fuel-dashboard-main-column,.fuel-reference-dashboard .fuel-dashboard-side-column{gap:10px!important}.fuel-reference-dashboard .fuel-usage-main-card{padding:16px 17px!important}.fuel-reference-dashboard .fuel-usage-chart-large{height:286px!important;min-height:286px!important}.fuel-reference-select{height:38px;padding:0 30px 0 11px;border:1px solid #d9e3ef;border-radius:8px;background:#fff;color:#203553;font:600 12px Poppins,sans-serif;outline:0}.fuel-reference-dashboard .fuel-summary-strip{min-height:76px!important}.fuel-reference-dashboard .fuel-summary-cell{padding:10px 12px!important;gap:9px!important}.fuel-reference-dashboard .fuel-summary-icon{width:38px!important;height:38px!important;flex-basis:38px!important;font-size:15px!important}.fuel-reference-dashboard .fuel-summary-cell strong{font-size:16px!important}.fuel-reference-dashboard .fuel-details-card{padding:16px 17px!important}.fuel-reference-dashboard .fuel-details-card .analytics-card__title{font-size:16px!important}.fuel-reference-dashboard .fuel-details-card .analytics-card__description{font-size:10.5px!important}.fuel-reference-dashboard .fuel-table-tools{margin-bottom:10px!important}.fuel-reference-dashboard .fuel-table-tools>span{font-size:10.5px!important}.fuel-reference-dashboard .fuel-table-search{width:min(300px,100%)!important}.fuel-reference-dashboard .fuel-table-search input{height:38px!important;font-size:11.5px!important}.fuel-reference-dashboard .fuel-details-table-wrap{max-height:440px!important;overflow:auto!important;scrollbar-gutter:stable!important}.fuel-reference-dashboard .analytics-fuel-table{min-width:720px!important;font-size:11.5px!important}.fuel-reference-dashboard .analytics-fuel-table thead th{padding:10px 11px!important;font-size:10px!important}.fuel-reference-dashboard .analytics-fuel-table tbody td{padding:8px 11px!important;font-size:11.5px!important}.fuel-reference-dashboard .analytics-fuel-table td strong{font-size:12.5px!important}.fuel-reference-dashboard .fuel-efficiency-value,.fuel-reference-dashboard .fuel-status-pill{min-height:23px!important;padding:4px 9px!important;font-size:10.5px!important}.fuel-reference-dashboard .fuel-side-card{padding:13px 14px!important;border-radius:12px!important}.fuel-reference-dashboard .fuel-side-card .analytics-card__header{margin-bottom:9px!important}.fuel-reference-dashboard .fuel-side-card .analytics-card__title{font-size:16px!important;line-height:1.2!important}.fuel-reference-dashboard .fuel-side-card .analytics-card__description{font-size:11.5px!important;line-height:1.4!important}.fuel-reference-dashboard .fuel-side-card .analytics-card__badge{font-size:10.5px!important;padding:5px 8px!important}.fuel-reference-dashboard .fuel-fleet-card{min-height:150px!important}.fuel-reference-dashboard .fuel-availability-layout{grid-template-columns:108px minmax(0,1fr)!important}.fuel-reference-dashboard .fuel-fleet-donut{width:100px!important;height:100px!important}.fuel-reference-dashboard .fuel-fleet-donut::after{inset:17px!important}.fuel-reference-dashboard .fuel-quality-body{grid-template-columns:44px 105px minmax(0,1fr)!important;gap:11px!important}.fuel-reference-dashboard .fuel-quality-icon{width:44px!important;height:44px!important;font-size:18px!important}.fuel-reference-dashboard .fuel-quality-score strong,.fuel-reference-dashboard .fuel-review-summary strong{font-size:24px!important;line-height:1!important}.fuel-reference-dashboard .fuel-quality-score span,.fuel-reference-dashboard .fuel-review-summary>div>span{font-size:11.5px!important;line-height:1.35!important}.fuel-reference-dashboard .fuel-quality-counts>div,.fuel-reference-dashboard .fuel-review-lines>div{min-height:30px!important;padding:6px 9px!important;font-size:11.5px!important}.fuel-reference-dashboard .fuel-quality-counts strong,.fuel-reference-dashboard .fuel-review-lines strong{font-size:12.5px!important}.fuel-quality-total{grid-column:1/-1}.fuel-reference-dashboard .fuel-review-summary{margin-bottom:8px!important}.fuel-reference-dashboard .fuel-distribution-bar{height:10px!important}.fuel-reference-dashboard .fuel-distribution-bar .no-data{background:#94a3b8}.fuel-reference-dashboard .fuel-distribution-legend.five{grid-template-columns:repeat(5,1fr)!important;gap:7px!important}.fuel-reference-dashboard .fuel-distribution-legend strong{font-size:12.5px!important}.fuel-reference-dashboard .fuel-distribution-legend span{font-size:10.5px!important;line-height:1.25!important}.fuel-reference-dashboard .fuel-trend-card{min-height:245px!important}.fuel-reference-dashboard .fuel-trend-card .analytics-line-chart{min-height:178px!important}.fuel-reference-dashboard .fuel-trend-card .analytics-line-chart,.fuel-reference-dashboard .fuel-trend-card .line-chart,.fuel-reference-dashboard .fuel-trend-card svg{max-height:178px!important}.fuel-reference-dashboard .fuel-trend-card .analytics-chart-label,.fuel-reference-dashboard .fuel-trend-card .analytics-chart-value,.fuel-reference-dashboard .fuel-trend-card .analytics-chart-y-label{font-size:11.5px!important;font-weight:700!important}.fuel-reference-dashboard .fuel-trend-card .analytics-chart-tooltip{font-size:12px!important}.fuel-reference-dashboard .fuel-trend-card .analytics-chart-tooltip strong{font-size:12.5px!important}.fuel-reference-dashboard .fuel-trend-card .analytics-chart-tooltip span{font-size:12px!important}@media(max-width:1100px){.fuel-reference-dashboard .fuel-dashboard-layout{grid-template-columns:1fr!important}.fuel-reference-dashboard .fuel-dashboard-side-column{grid-template-columns:repeat(2,minmax(0,1fr))}.fuel-reference-dashboard .fuel-trend-card{grid-column:1/-1}}@media(max-width:760px){.fuel-reference-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important}.fuel-reference-dashboard .fuel-dashboard-side-column{grid-template-columns:1fr}.fuel-card-header-with-action{align-items:stretch!important;flex-direction:column}}
</style>