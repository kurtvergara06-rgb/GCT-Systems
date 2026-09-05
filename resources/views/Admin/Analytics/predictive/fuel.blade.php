@php
    $fuelPredict = $predictive->fuel;
    $distribution = $fuelPredict->distribution;
    $totalRecords = max(1, (int) $distribution->total);
    $lowShare = round(($distribution->low / $totalRecords) * 100);
    $mediumShare = round(($distribution->medium / $totalRecords) * 100);
    $highShare = round(($distribution->high / $totalRecords) * 100);
@endphp

<div class="predictive-page predictive-fuel-page">

    {{-- KPI STRIP --}}
    <section class="predictive-kpis">
        @foreach($fuelPredict->kpis as $kpi)
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

    {{-- CHART GRID --}}
    <section class="predictive-main-grid">

        <article class="predictive-card forecast-card">
            <div class="card-heading">
                <div>
                    <h3>Fuel Consumption Trend</h3>
                    <p>Recorded daily liters vs projected consumption.</p>
                </div>
            </div>
            <div class="chart-container large-chart">
                <canvas id="consumptionChart" role="img" aria-label="Fuel consumption trend chart"></canvas>
            </div>
        </article>

        <article class="predictive-card">
            <div class="card-heading">
                <div>
                    <h3>Fuel Efficiency Trend</h3>
                    <p>km/L trend with missing-day baseline fill.</p>
                </div>
            </div>
            <div class="chart-container large-chart">
                <canvas id="efficiencyChart" role="img" aria-label="Fuel efficiency trend chart"></canvas>
            </div>
        </article>

    </section>

    {{-- PREDICTION TABLE --}}
    <section class="predictive-card predictions-card">
        <div class="card-heading">
            <div>
                <h3>Fuel Consumption Predictions</h3>
                <p>Units ranked by fuel review priority from recorded efficiency data.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="predictive-table">
                <thead>
                    <tr>
                        <th>Bus</th>
                        <th>Distance (km)</th>
                        <th>Fuel Used (L)</th>
                        <th>Efficiency (km/L)</th>
                        <th>Idling (min)</th>
                        <th>Status</th>
                        <th>Risk Level</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fuelPredict->rows as $row)
                        <tr>
                            <td><strong>{{ $row[0] }}</strong></td>
                            <td>{{ $row[1] }}</td>
                            <td>{{ $row[2] }}</td>
                            <td>{{ $row[3] }}</td>
                            <td>{{ $row[4] }}</td>
                            <td>{{ $row[5] }}</td>
                            <td><span class="risk-badge {{ strtolower($row[6]) }}">{{ $row[6] }}</span></td>
                            <td>{{ $row[7] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No fuel records are available for the selected period.</td>
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
                    <h3>Fuel Risk Distribution</h3>
                    <p>Units grouped by fuel review status.</p>
                </div>
            </div>
            <div class="risk-content">
                <div class="risk-circle">
                    <span>{{ number_format($distribution->total) }}</span>
                    <small>Total Units</small>
                </div>
                <div class="risk-legend">
                    <div>
                        <span class="legend-dot low"></span>
                        <span>Low Risk</span>
                        <strong>{{ number_format($distribution->low) }} ({{ $lowShare }}%)</strong>
                    </div>
                    <div>
                        <span class="legend-dot medium"></span>
                        <span>Medium Risk</span>
                        <strong>{{ number_format($distribution->medium) }} ({{ $mediumShare }}%)</strong>
                    </div>
                    <div>
                        <span class="legend-dot high"></span>
                        <span>High Risk</span>
                        <strong>{{ number_format($distribution->high) }} ({{ $highShare }}%)</strong>
                    </div>
                </div>
            </div>
        </article>

        <article class="predictive-card">
            <div class="card-heading">
                <div>
                    <h3>Top Fuel Risk Factors</h3>
                    <p>Ranked drivers identified from recorded fuel records.</p>
                </div>
            </div>
            <div class="issue-list">
                @foreach($fuelPredict->factors as $index => $factor)
                    <div class="issue-row">
                        <span class="issue-rank">{{ $index + 1 }}</span>
                        <div class="issue-info">
                            <strong>{{ $factor->title }}</strong>
                            <span>{{ $factor->description }}</span>
                        </div>
                        <span class="risk-badge {{ strtolower($factor->level) }}">{{ $factor->level }}</span>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="predictive-card">
            <div class="card-heading">
                <div>
                    <h3>Fuel Demand Forecast</h3>
                    <p>Projected daily consumption from the recorded trend.</p>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="fuelForecastChart" role="img" aria-label="Fuel demand forecast chart"></canvas>
            </div>
        </article>

    </section>

    <p class="predictive-footer">
        Projections are derived from recorded fuel efficiency trends. Results may vary and are not guaranteed.
    </p>

</div>

<script>
    window.predictiveChartData = {
        risk: {
            low: {{ $distribution->low }},
            medium: {{ $distribution->medium }},
            high: {{ $distribution->high }},
            total: {{ $distribution->total }},
        },
        fuel_labels: @json($fuelPredict->trend_labels),
        fuel_actual: @json($fuelPredict->trend_actual),
        fuel_forecast: @json($fuelPredict->trend_forecast),
        efficiency_labels: @json($fuelPredict->efficiency_labels),
        efficiency_actual: @json($fuelPredict->efficiency_actual),
        efficiency_forecast: @json($fuelPredict->efficiency_forecast),
    };
</script>