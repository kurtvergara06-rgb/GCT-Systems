@php
    $all = $predictive->all;

    $donutLabels = [
        ['label' => 'Low Risk', 'value' => $all->risk->low, 'class' => 'low'],
        ['label' => 'Medium Risk', 'value' => $all->risk->medium, 'class' => 'medium'],
        ['label' => 'High Risk', 'value' => $all->risk->high, 'class' => 'high'],
    ];

    $overviewData = [
        'labels' => $all->overview->labels,
        'records' => $all->overview->records,
        'at_risk' => $all->overview->at_risk,
    ];
@endphp

<div class="predictive-page predictive-all-page">

    {{-- KPI STRIP --}}
    <section class="predictive-kpis">
        @foreach($all->kpis as $kpi)
            <article class="predictive-kpi">
                <div class="predictive-kpi-icon {{ $kpi['tone'] }}">
                    <i class="fa-solid {{ $kpi['icon'] }}"></i>
                </div>
                <div class="predictive-kpi-copy">
                    <span>{{ $kpi['label'] }}</span>
                    <strong>{{ $kpi['value'] }}</strong>
                    <small>{{ $kpi['caption'] }}</small>
                </div>
            </article>
        @endforeach
    </section>

    {{-- MAIN GRID --}}
    <section class="predictive-main-grid">

        <article class="predictive-card forecast-card">
            <div class="card-heading">
                <div>
                    <h3>Cross-Domain Overview</h3>
                    <p>Recorded basis vs predicted risk signal by operational domain.</p>
                </div>
            </div>
            <div class="chart-container large-chart">
                <canvas id="predictionOverviewChart" role="img" aria-label="Cross-domain recorded basis and at-risk counts"></canvas>
            </div>
        </article>

        <article class="predictive-card risk-card">
            <div class="card-heading">
                <div>
                    <h3>Risk Distribution</h3>
                    <p>Forecast records grouped by risk level.</p>
                </div>
            </div>
            <div class="risk-content">
                <div class="donut-wrapper">
                    <canvas id="riskDonut"></canvas>
                    <div class="donut-center">
                        <strong id="riskDonutTotal">{{ number_format($all->risk->total) }}</strong>
                        <span>Predicted<br>Records</span>
                    </div>
                </div>
                <div class="risk-legend">
                    @foreach($donutLabels as $item)
                        <div>
                            <span class="legend-dot {{ $item['class'] }}"></span>
                            <span>{{ $item['label'] }}</span>
                            <strong>{{ number_format($item['value']) }}
                                ({{ $all->risk->total > 0 ? number_format(($item['value'] / $all->risk->total) * 100, 1) : 0 }}%)</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </article>

        <article class="predictive-card issues-card">
            <div class="card-heading">
                <div>
                    <h3>Top Predicted Issues</h3>
                    <p>Ranked by forecast record volume.</p>
                </div>
            </div>
            <div class="issue-list">
                @forelse($all->issues as $issue)
                    <div class="issue-row">
                        <span class="issue-rank">{{ $issue['rank'] }}</span>
                        <div class="issue-icon {{ $issue['class'] }}">
                            <i class="fa-solid {{ $issue['icon'] }}"></i>
                        </div>
                        <div class="issue-info">
                            <strong>{{ $issue['title'] }}</strong>
                            <span>{{ $issue['description'] }}</span>
                        </div>
                        <span class="risk-badge {{ strtolower($issue['level']) }}">{{ $issue['level'] }}</span>
                        <strong class="issue-count">{{ $issue['count'] }}</strong>
                    </div>
                @empty
                    <div class="issue-row">
                        <div class="issue-info">
                            <strong>No predicted issues</strong>
                            <span>No risk signals were found for the selected period.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        </article>

    </section>

    {{-- FORECAST HIGHLIGHTS --}}
    <section class="predictive-card predictions-card">
        <div class="card-heading">
            <div>
                <h3>Forecast Highlights</h3>
                <p>Forecast signal per domain with the recorded basis used.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="predictive-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Recorded Basis</th>
                        <th>Forecast Signal</th>
                        <th>Risk Level</th>
                        <th>Forecast Basis</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($all->table_rows as $row)
                        <tr>
                            <td><strong><i class="fa-solid {{ $row->icon }}"></i> {{ $row->domain }}</strong></td>
                            <td>{{ $row->basis }}</td>
                            <td>{{ $row->signal }}</td>
                            <td><span class="risk-badge {{ $row->level }}">{{ ucfirst($row->level) }}</span></td>
                            <td>{{ $row->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No forecast signals are available for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- BOTTOM GRID --}}
    <section class="predictive-bottom-grid">

        <article class="predictive-card">
            <div class="card-heading">
                <div>
                    <h3>Fuel Demand Forecast</h3>
                    <p>Recorded daily consumption vs projected trend.</p>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="fuelForecastChart" role="img" aria-label="Fuel demand forecast chart"></canvas>
            </div>
        </article>

        <article class="predictive-card insights-card">
            <div class="card-heading">
                <div>
                    <h3>Predictive Insights</h3>
                    <p>Forecast context generated from recorded trends.</p>
                </div>
            </div>
            <div class="insight-grid">
                @foreach($all->insights as $insight)
                    <div class="insight-item">
                        <div class="insight-icon {{ $insight->tone }}">
                            <i class="fa-solid {{ $insight->icon }}"></i>
                        </div>
                        <div>
                            <strong>{{ $insight->title }}</strong>
                            <p>{{ $insight->text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

    </section>

    <p class="predictive-footer">
        Predictions are derived from recorded operational data and current trends. Results may vary and are not guaranteed.
    </p>

</div>

<script>
    window.predictiveChartData = {
        overview: @json($overviewData),
        risk: {
            low: $all->risk->low,
            medium: $all->risk->medium,
            high: $all->risk->high,
            total: $all->risk->total,
        },
        fuel_labels: $all->fuel_labels,
        fuel_actual: $all->fuel_actual,
        fuel_forecast: $all->fuel_forecast,
    };
</script>