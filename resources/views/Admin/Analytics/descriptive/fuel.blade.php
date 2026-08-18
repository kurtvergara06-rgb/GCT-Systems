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
                        <label class="fuel-table-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search bus or driver..." data-fuel-reference-search></label>
                        <div class="fuel-table-action-group">
                            <button type="button" class="fuel-table-action"><i class="fa-solid fa-table-columns"></i> Columns</button>
                            <button type="button" class="fuel-table-action" data-fuel-export><i class="fa-solid fa-download"></i> Export</button>
                        </div>
                    </div>

                    <div class="table-wrap analytics-fuel-table-wrap fuel-details-table-wrap" tabindex="0" aria-label="Fuel usage details table">
                        <table class="analytics-fuel-table" data-fuel-reference-table>
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
                                    <tr data-fuel-search="{{ strtolower($row->bus_no . ' ' . $driverName) }}">
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
                        <span data-fuel-table-meta></span>
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

<style>
.fuel-reference-kpis{gap:10px!important;margin-bottom:12px!important}.fuel-reference-kpis>.analytics-kpi{min-height:104px!important;padding:14px 16px!important;border-radius:13px!important}.fuel-reference-dashboard .fuel-dashboard-layout{grid-template-columns:minmax(0,1.78fr) minmax(330px,.92fr)!important;gap:12px!important}.fuel-reference-dashboard .fuel-dashboard-main-column,.fuel-reference-dashboard .fuel-dashboard-side-column{gap:10px!important}.fuel-reference-dashboard .fuel-usage-main-card{padding:16px 17px!important}.fuel-reference-dashboard .fuel-usage-chart-large{height:286px!important;min-height:286px!important}.fuel-card-header-with-action{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:12px!important}.fuel-reference-select{height:34px;padding:0 30px 0 11px;border:1px solid #d9e3ef;border-radius:8px;background:#fff;color:#203553;font:600 10px Poppins,sans-serif;outline:0}.fuel-reference-dashboard .fuel-summary-strip{min-height:76px!important}.fuel-reference-dashboard .fuel-summary-cell{padding:10px 12px!important;gap:9px!important}.fuel-reference-dashboard .fuel-summary-icon{width:38px!important;height:38px!important;flex-basis:38px!important;font-size:15px!important}.fuel-reference-dashboard .fuel-summary-cell strong{font-size:16px!important}.fuel-reference-dashboard .fuel-details-card{padding:14px 15px 12px!important}.fuel-table-tools-reference{margin:0 0 9px!important}.fuel-table-action-group{display:flex;gap:7px}.fuel-table-action{height:34px;padding:0 11px;border:1px solid #d9e3ef;border-radius:8px;background:#fff;color:#1557d5;font:700 10px Poppins,sans-serif;cursor:pointer}.fuel-table-action i{margin-right:5px}.fuel-reference-dashboard .fuel-table-search{width:min(290px,100%)!important}.fuel-reference-dashboard .fuel-details-table-wrap{max-height:none!important;overflow-x:auto!important;overflow-y:hidden!important}.fuel-reference-dashboard .analytics-fuel-table{min-width:780px!important;font-size:9.8px!important}.fuel-reference-dashboard .analytics-fuel-table thead th{padding:7px 8px!important;font-size:8px!important}.fuel-reference-dashboard .analytics-fuel-table tbody td{padding:5px 8px!important;font-size:9.4px!important}.fuel-reference-dashboard .analytics-fuel-table td strong{font-size:9.8px!important}.fuel-reference-dashboard .fuel-efficiency-value{min-height:19px!important;padding:3px 7px!important;font-size:8.6px!important}.fuel-table-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:9px;color:#718096;font-size:9.4px}.fuel-table-pagination{display:flex;align-items:center;gap:5px}.fuel-table-pagination [data-fuel-page-numbers]{display:flex;gap:4px}.fuel-page-arrow,.fuel-page-number{width:27px;height:27px;border:1px solid #d9e3ef;border-radius:6px;background:#fff;color:#526277;font:700 9.5px Poppins,sans-serif;cursor:pointer}.fuel-page-number.active{border-color:#2563eb;background:#2563eb;color:#fff}.fuel-page-arrow:disabled{opacity:.35;cursor:not-allowed}.fuel-page-size{height:27px;display:inline-flex;align-items:center;padding:0 9px;border:1px solid #d9e3ef;border-radius:6px;background:#fff;color:#526277}.fuel-reference-dashboard .fuel-side-card{padding:11px 12px!important;border-radius:12px!important}.fuel-reference-dashboard .fuel-side-card .analytics-card-header{margin-bottom:7px!important}.fuel-reference-dashboard .fuel-side-card .analytics-card-header h3{font-size:13px!important}.fuel-reference-dashboard .fuel-side-card .analytics-card-header p{font-size:8.7px!important}.fuel-reference-dashboard .fuel-fleet-card{min-height:150px!important}.fuel-reference-dashboard .fuel-availability-layout{grid-template-columns:108px minmax(0,1fr)!important}.fuel-reference-dashboard .fuel-fleet-donut{width:100px!important;height:100px!important}.fuel-reference-dashboard .fuel-fleet-donut::after{inset:17px!important}.fuel-reference-dashboard .fuel-quality-body{grid-template-columns:40px 86px minmax(0,1fr)!important;gap:9px!important}.fuel-reference-dashboard .fuel-quality-icon{width:40px!important;height:40px!important;font-size:16px!important}.fuel-reference-dashboard .fuel-quality-score strong,.fuel-reference-dashboard .fuel-review-summary strong{font-size:18px!important}.fuel-reference-dashboard .fuel-quality-counts>div,.fuel-reference-dashboard .fuel-review-lines>div{min-height:25px!important;padding:4px 7px!important;font-size:8.8px!important}.fuel-quality-total{grid-column:1/-1}.fuel-reference-dashboard .fuel-review-summary{margin-bottom:6px!important}.fuel-reference-dashboard .fuel-distribution-bar{height:8px!important}.fuel-reference-dashboard .fuel-distribution-bar .no-data{background:#94a3b8}.fuel-reference-dashboard .fuel-distribution-legend.five{grid-template-columns:repeat(5,1fr)!important}.fuel-reference-dashboard .fuel-distribution-legend strong{font-size:10px!important}.fuel-reference-dashboard .fuel-distribution-legend span{font-size:8px!important}.fuel-reference-dashboard .fuel-trend-card{min-height:215px!important}.fuel-reference-dashboard .fuel-trend-card .analytics-line-chart{min-height:150px!important}.fuel-reference-dashboard .fuel-trend-card .analytics-line-chart,.fuel-reference-dashboard .fuel-trend-card .line-chart,.fuel-reference-dashboard .fuel-trend-card svg{max-height:150px!important}.fuel-reference-dashboard .fuel-trend-card .analytics-chart-label,.fuel-reference-dashboard .fuel-trend-card .analytics-chart-value,.fuel-reference-dashboard .fuel-trend-card .analytics-chart-y-label{font-size:9.5px!important}@media(max-width:1100px){.fuel-reference-dashboard .fuel-dashboard-layout{grid-template-columns:1fr!important}.fuel-reference-dashboard .fuel-dashboard-side-column{grid-template-columns:repeat(2,minmax(0,1fr))}.fuel-reference-dashboard .fuel-trend-card{grid-column:1/-1}}@media(max-width:760px){.fuel-reference-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important}.fuel-reference-dashboard .fuel-dashboard-side-column{grid-template-columns:1fr}.fuel-card-header-with-action{align-items:stretch!important;flex-direction:column}.fuel-table-tools-reference{align-items:stretch!important;flex-direction:column}.fuel-table-action-group{justify-content:flex-end}.fuel-table-footer{align-items:flex-start;flex-direction:column}}
</style>

<script>
(() => {
    const root = document.currentScript?.previousElementSibling?.previousElementSibling?.classList?.contains('fuel-reference-dashboard')
        ? document.currentScript.previousElementSibling.previousElementSibling
        : document.querySelector('.fuel-reference-dashboard');
    if (!root || root.dataset.referenceBound === 'true') return;
    root.dataset.referenceBound = 'true';

    const table = root.querySelector('[data-fuel-reference-table]');
    const search = root.querySelector('[data-fuel-reference-search]');
    const meta = root.querySelector('[data-fuel-table-meta]');
    const numbers = root.querySelector('[data-fuel-page-numbers]');
    const prev = root.querySelector('[data-fuel-page-prev]');
    const next = root.querySelector('[data-fuel-page-next]');
    const exportButton = root.querySelector('[data-fuel-export]');
    if (!table) return;

    const allRows = Array.from(table.querySelectorAll('tbody tr'));
    const pageSize = 10;
    let page = 1;
    let filtered = [...allRows];

    const render = () => {
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        page = Math.min(page, totalPages);
        const start = (page - 1) * pageSize;
        const end = Math.min(start + pageSize, filtered.length);
        allRows.forEach((row) => { row.hidden = true; });
        filtered.slice(start, end).forEach((row) => { row.hidden = false; });
        if (meta) meta.textContent = filtered.length ? `Showing ${start + 1} to ${end} of ${filtered.length} entries` : 'Showing 0 entries';
        if (numbers) {
            numbers.innerHTML = '';
            for (let index = 1; index <= totalPages; index += 1) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `fuel-page-number${index === page ? ' active' : ''}`;
                button.textContent = String(index);
                button.addEventListener('click', () => { page = index; render(); });
                numbers.appendChild(button);
            }
        }
        if (prev) prev.disabled = page <= 1;
        if (next) next.disabled = page >= totalPages;
    };

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLowerCase();
        filtered = allRows.filter((row) => !query || String(row.dataset.fuelSearch || '').includes(query));
        page = 1;
        render();
    });
    prev?.addEventListener('click', () => { if (page > 1) { page -= 1; render(); } });
    next?.addEventListener('click', () => { const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize)); if (page < totalPages) { page += 1; render(); } });

    exportButton?.addEventListener('click', () => {
        const rows = [Array.from(table.querySelectorAll('thead th')).map((cell) => cell.textContent.trim()), ...allRows.map((row) => Array.from(row.cells).map((cell) => cell.textContent.trim()))];
        const csv = rows.map((row) => row.map((value) => `"${String(value).replaceAll('"','""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'fuel-usage-details.csv';
        anchor.click();
        URL.revokeObjectURL(url);
    });

    render();
})();
</script>
