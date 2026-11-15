@php
    $allPredictive = $predictive?->all;
    $kpis = collect($allPredictive?->kpis ?? []);
    $issues = collect($allPredictive?->issues ?? []);
    $tableRows = collect($allPredictive?->table_rows ?? []);
    $insights = collect($allPredictive?->insights ?? []);
    $risk = $allPredictive?->risk ?? (object) ['low' => 0, 'medium' => 0, 'high' => 0, 'total' => 1];
    $riskTotal = max(1, (int) ($risk->total ?? 1));
    $confidence = (int) round((($risk->low ?? 0) + (($risk->medium ?? 0) * .65)) / $riskTotal * 100);
    $confidence = max(0, min(100, $confidence));
    $projectedFuel = collect($allPredictive?->fuel_forecast ?? [])->sum();
    $periodText = [
        'this-month' => 'This Month',
        'last-30-days' => 'Last 30 Days',
        'last-3-months' => 'Last 3 Months',
        'this-year' => 'This Year',
    ][$period] ?? 'Selected Period';

    $toneClass = fn (string $tone): string => match ($tone) {
        'danger' => 'danger',
        'warning', 'orange', 'yellow' => 'warning',
        'success', 'green' => 'success',
        'purple' => 'purple',
        default => 'blue',
    };

    $riskBadge = fn (string $level): string => match (strtolower($level)) {
        'high' => 'danger',
        'medium' => 'warning',
        default => 'success',
    };
@endphp

<div class="predictive-page">
    <section class="analytics-kpi-strip" aria-label="Predictive analytics summary">
        @forelse($kpis as $kpi)
            <x-analytics.kpi
                :label="$kpi['label'] ?? 'Forecast Metric'"
                :value="$kpi['value'] ?? '0'"
                :description="$kpi['caption'] ?? 'Current forecast'"
                :icon="$kpi['icon'] ?? 'fa-chart-line'"
                :tone="match ($kpi['tone'] ?? '') {
                    'danger' => 'red',
                    'warning', 'orange', 'yellow' => 'yellow',
                    'success', 'green' => 'green',
                    'purple' => 'purple',
                    default => 'blue',
                }"
            />
        @empty
            <x-analytics.kpi
                label="Forecast Status"
                value="No data"
                description="No predictive records match the selected filters."
                icon="fa-chart-line"
            />
        @endforelse
    </section>

    <section class="predictive-main-grid">
        <article class="analytics-card prediction-chart-card">
            <div class="card-heading">
                <div>
                    <h3>Prediction Overview</h3>
                    <span>Recorded volume compared with records currently carrying risk signals.</span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="predictionOverviewChart" role="img" aria-label="Prediction overview chart"></canvas>
            </div>
        </article>

        <article class="analytics-card confidence-card">
            <div class="card-heading">
                <div>
                    <h3>Prediction Confidence</h3>
                    <span>Distribution across current risk buckets.</span>
                </div>
            </div>
            <div class="confidence-content">
                <div class="confidence-circle" style="--confidence: {{ $confidence }}%;">
                    <div class="confidence-inner">
                        <strong>{{ $confidence }}%</strong>
                        <span>Overall</span>
                    </div>
                </div>
                <div class="confidence-legend">
                    <div><span class="legend-dot high"></span><span>Low Risk</span><strong>{{ number_format((($risk->low ?? 0) / $riskTotal) * 100, 0) }}%</strong></div>
                    <div><span class="legend-dot medium"></span><span>Medium Risk</span><strong>{{ number_format((($risk->medium ?? 0) / $riskTotal) * 100, 0) }}%</strong></div>
                    <div><span class="legend-dot low"></span><span>High Risk</span><strong>{{ number_format((($risk->high ?? 0) / $riskTotal) * 100, 0) }}%</strong></div>
                </div>
            </div>
        </article>

        <article class="analytics-card risk-card">
            <div class="card-heading">
                <div>
                    <h3>Top Risk Predictions</h3>
                    <span>Highest-volume operational signals.</span>
                </div>
            </div>
            <div class="risk-list">
                @forelse($issues as $issue)
                    <div class="risk-item">
                        <div class="risk-left">
                            <i class="fa-solid {{ $issue['icon'] ?? 'fa-triangle-exclamation' }}"></i>
                            <div>
                                <strong>{{ $issue['title'] ?? 'Forecast signal' }}</strong>
                                <span>{{ $issue['description'] ?? 'Needs operational review.' }}</span>
                            </div>
                        </div>
                        <div class="risk-right">
                            <span class="risk-badge {{ $riskBadge($issue['level'] ?? 'Low') }}">{{ $issue['level'] ?? 'Low' }}</span>
                            <strong>{{ $issue['count'] ?? '0' }}</strong>
                        </div>
                    </div>
                @empty
                    <div class="analytics-compact-empty">
                        <i class="fa-regular fa-circle-check"></i>
                        <span>No risk signals were found for the selected filters.</span>
                    </div>
                @endforelse
            </div>
            <a href="{{ route('analytics.stage', ['stage' => 'predictive', 'domain' => 'fleet-trip'], false) }}" class="view-link">
                View fleet predictions <i class="fa-solid fa-arrow-right"></i>
            </a>
        </article>
    </section>

    <section class="prediction-detail-grid">
        <article class="analytics-card">
            <div class="card-heading">
                <div>
                    <h3>Domain Forecast Summary</h3>
                    <span>Forecast basis and signal volume by operating area.</span>
                </div>
            </div>
            <div class="responsive-table">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Basis</th>
                            <th>Signal</th>
                            <th>Risk</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tableRows as $row)
                            <tr>
                                <td>
                                    <span class="table-domain">
                                        <i class="fa-solid {{ $row->icon ?? 'fa-chart-line' }}"></i>
                                        {{ $row->domain ?? 'Forecast Area' }}
                                    </span>
                                </td>
                                <td>{{ $row->basis ?? '0 records' }}</td>
                                <td>{{ $row->signal ?? '0 signals' }}</td>
                                <td><span class="risk-badge {{ $riskBadge($row->level ?? 'low') }}">{{ ucfirst($row->level ?? 'low') }}</span></td>
                                <td>{{ $row->status ?? 'Derived forecast' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-cell">No forecast summary is available for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="analytics-card">
            <div class="card-heading">
                <div>
                    <h3>Fuel Demand Forecast</h3>
                    <span>Recorded fuel trend and projected demand.</span>
                </div>
            </div>
            <div class="small-chart">
                <canvas id="fuelForecastChart" role="img" aria-label="Fuel demand forecast chart"></canvas>
            </div>
            <div class="forecast-total">
                <div>
                    <span>Total Forecast</span>
                    <strong>{{ number_format($projectedFuel, 0) }} L</strong>
                </div>
                <small>{{ $periodText }}</small>
            </div>
        </article>

        <article class="analytics-card">
            <div class="card-heading">
                <div>
                    <h3>Risk Distribution</h3>
                    <span>Current predicted signal severity.</span>
                </div>
            </div>
            <div class="risk-distribution">
                <div class="risk-distribution-row">
                    <div><span class="legend-dot high"></span><span>Low Risk</span></div>
                    <strong>{{ number_format($risk->low ?? 0) }}</strong>
                </div>
                <div class="risk-meter"><span style="width: {{ min(100, (($risk->low ?? 0) / $riskTotal) * 100) }}%"></span></div>

                <div class="risk-distribution-row">
                    <div><span class="legend-dot medium"></span><span>Medium Risk</span></div>
                    <strong>{{ number_format($risk->medium ?? 0) }}</strong>
                </div>
                <div class="risk-meter warning"><span style="width: {{ min(100, (($risk->medium ?? 0) / $riskTotal) * 100) }}%"></span></div>

                <div class="risk-distribution-row">
                    <div><span class="legend-dot low"></span><span>High Risk</span></div>
                    <strong>{{ number_format($risk->high ?? 0) }}</strong>
                </div>
                <div class="risk-meter danger"><span style="width: {{ min(100, (($risk->high ?? 0) / $riskTotal) * 100) }}%"></span></div>

                <div class="risk-total">
                    <span>Total signals</span>
                    <strong>{{ number_format($riskTotal) }}</strong>
                </div>
            </div>
        </article>
    </section>

    <section class="analytics-card prediction-insights">
        <div class="card-heading">
            <div>
                <h3>Prediction Insights</h3>
                <span>Concise guidance generated from the current forecast set.</span>
            </div>
        </div>
        <div class="insight-grid">
            @forelse($insights as $insight)
                <article class="insight-item">
                    <div class="insight-icon {{ $toneClass($insight->tone ?? 'blue') }}">
                        <i class="fa-solid {{ $insight->icon ?? 'fa-lightbulb' }}"></i>
                    </div>
                    <div>
                        <strong>{{ $insight->title ?? 'Forecast insight' }}</strong>
                        <p>{{ $insight->text ?? 'No additional insight is available.' }}</p>
                    </div>
                </article>
            @empty
                <article class="insight-item">
                    <div class="insight-icon blue"><i class="fa-regular fa-lightbulb"></i></div>
                    <div>
                        <strong>No insights yet</strong>
                        <p>More records are needed before the predictive engine can produce meaningful guidance.</p>
                    </div>
                </article>
            @endforelse
        </div>
    </section>
</div>

<script>
    window.predictiveChartData = {
        overview: {
            labels: @json($allPredictive?->overview->labels ?? []),
            records: @json($allPredictive?->overview->records ?? []),
            at_risk: @json($allPredictive?->overview->at_risk ?? []),
        },
        risk: {
            low: {{ (int) ($risk->low ?? 0) }},
            medium: {{ (int) ($risk->medium ?? 0) }},
            high: {{ (int) ($risk->high ?? 0) }},
            total: {{ (int) $riskTotal }},
        },
        fuel_labels: @json($allPredictive?->fuel_labels ?? []),
        fuel_actual: @json($allPredictive?->fuel_actual ?? []),
        fuel_forecast: @json($allPredictive?->fuel_forecast ?? []),
    };
</script>
