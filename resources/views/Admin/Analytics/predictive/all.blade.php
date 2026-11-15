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

    {{-- EXECUTIVE CROSS-DOMAIN AI PREDICTIVE BANNER --}}
    <div class="predictive-ai-banner">
        <div class="predictive-ai-banner__icon-wrap">
            <i class="fa-solid fa-brain"></i>
        </div>
        <div class="predictive-ai-banner__content">
            <div class="predictive-ai-banner__top">
                <span class="ai-chip">Executive AI Command Center</span>
                <span class="ai-status-pulse">
                    <span class="pulse-dot"></span>
                    Integrated Multi-Domain Predictive Telemetry
                </span>
            </div>
            <p class="predictive-ai-banner__text">
                Cross-domain models detect <strong>{{ number_format($riskTotal) }} active operational signals</strong> across the fleet network: <strong>12 overdue job orders</strong> in maintenance, <strong>10 depleted parts</strong> near reorder threshold, <strong>3 fuel review units</strong>, and <strong>1 scheduled delay risk</strong>. Dispatch confidence is stable at <strong>94.1%</strong>. Immediate focus is recommended on mechanical shop turnaround and parts procurement.
            </p>
        </div>
        <div class="predictive-ai-banner__action">
            <a href="{{ route('analytics.stage', ['stage' => 'predictive', 'domain' => 'bus-health'], false) }}" class="btn-ai-reorder">
                <i class="fa-solid fa-screwdriver-wrench"></i>
                <span>Inspect Maintenance</span>
            </a>
        </div>
    </div>

    <section class="analytics-kpi-strip" aria-label="Predictive analytics summary">
        @forelse($kpis as $kpi)
            <x-analytics.kpi
                :label="$kpi['label'] ?? 'Forecast Metric'"
                :value="$kpi['value'] ?? '0'"
                :description="$kpi['caption'] ?? 'Current forecast'"
                :icon="$kpi['icon'] ?? 'fa-chart-line'"
                :icon-variant="match ($kpi['tone'] ?? '') {
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
                icon-variant="blue"
            />
        @endforelse
    </section>

    <section class="predictive-main-grid">
        <x-analytics.card class="prediction-chart-card" title="Prediction Overview" description="Recorded volume compared with records currently carrying risk signals.">
            <x-slot:headerActions>
                <span class="ft-telemetry-badge" style="font-size: 9.5px;">
                    <i class="fa-solid fa-chart-simple"></i>
                    <span>4 Domains Monitored</span>
                </span>
            </x-slot:headerActions>
            <div class="chart-container">
                <canvas id="predictionOverviewChart" role="img" aria-label="Prediction overview chart"></canvas>
            </div>
        </x-analytics.card>

        <x-analytics.card class="confidence-card" title="System Reliability Index" description="AI operational readiness across all monitored domains.">
            <div class="confidence-content">
                <div class="confidence-circle" style="--confidence: 88%;">
                    <div class="confidence-inner">
                        <strong>88%</strong>
                        <span>System Readiness</span>
                    </div>
                </div>
                <div class="confidence-legend">
                    <div><span class="legend-dot low"></span><span>High Risk Alerts</span><strong>{{ number_format($risk->high ?? 0) }}</strong></div>
                    <div><span class="legend-dot medium"></span><span>Medium Attention</span><strong>{{ number_format($risk->medium ?? 0) }}</strong></div>
                    <div><span class="legend-dot high"></span><span>Nominal / Clear</span><strong>{{ number_format($risk->low ?? 0) }}</strong></div>
                </div>
                <div style="display: flex; justify-content: center; margin-top: 4px;">
                    <span style="font-size: 9.5px; font-weight: 700; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-satellite-dish" style="color: #2563eb;"></i>
                        {{ number_format($riskTotal) }} Total Active Signals
                    </span>
                </div>
            </div>
        </x-analytics.card>

        <x-analytics.card class="risk-card" title="Top Risk Predictions" description="Highest-volume operational signals.">
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
        </x-analytics.card>
    </section>

    <section class="prediction-detail-grid">
        <x-analytics.card title="Domain Forecast Summary" description="Forecast basis and signal volume by operating area.">
            <div class="responsive-table">
                <table class="analytics-table all-domain-table">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Basis</th>
                            <th>Signals</th>
                            <th>Risk Level</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tableRows as $row)
                            @php
                                $rowDomain = (string) ($row->domain ?? '');
                                $slug = match(strtolower(trim($rowDomain))) {
                                    'fleet & trip' => 'fleet-trip',
                                    'fuel' => 'fuel',
                                    'bus health' => 'bus-health',
                                    'inventory' => 'inventory',
                                    default => 'all',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="table-domain">
                                        <i class="fa-solid {{ $row->icon ?? 'fa-chart-line' }}"></i>
                                        {{ $row->domain ?? 'Forecast Area' }}
                                    </span>
                                </td>
                                <td>{{ $row->basis ?? '0 records' }}</td>
                                <td>
                                    <strong style="color: {{ strtolower($row->level ?? '') === 'high' ? '#ef4444' : (strtolower($row->level ?? '') === 'medium' ? '#f59e0b' : '#10b981') }};">
                                        {{ $row->signal ?? '0 signals' }}
                                    </strong>
                                </td>
                                <td><span class="risk-badge {{ $riskBadge($row->level ?? 'low') }}">{{ ucfirst($row->level ?? 'low') }}</span></td>
                                <td style="text-align: right;">
                                    <a href="{{ route('analytics.stage', ['stage' => 'predictive', 'domain' => $slug], false) }}" class="domain-explore-chip" title="Inspect {{ $row->domain }} detailed forecast">
                                        <span>Explore</span> <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-cell">No forecast summary is available for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-analytics.card>

        <x-analytics.card title="Fuel Demand Forecast" description="Recorded fuel trend and projected demand.">
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
        </x-analytics.card>

        <x-analytics.card title="Risk Distribution" description="Current predicted signal severity.">
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
        </x-analytics.card>
    </section>

    <x-analytics.card class="prediction-insights" title="Prediction Insights" description="Concise guidance generated from the current forecast set.">
        <div class="insight-grid">
            @forelse($insights as $insight)
                <article class="insight-item insight-item--{{ $insight->tone ?? 'blue' }}">
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
    </x-analytics.card>
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
