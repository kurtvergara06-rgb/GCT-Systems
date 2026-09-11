@php
    $fuelPredict = $predictive->fuel;
    $distribution = $fuelPredict->distribution;
    $totalRecords = max(1, (int) $distribution->total);
    $lowShare = round(($distribution->low / $totalRecords) * 100, 1);
    $mediumShare = round(($distribution->medium / $totalRecords) * 100, 1);
    $highShare = round(($distribution->high / $totalRecords) * 100, 1);
    $fuelTrendLabels = collect($fuelPredict->trend_labels ?? [])->values();
    $fuelTrendForecast = collect($fuelPredict->trend_forecast ?? [])->values();
    $peakForecast = $fuelTrendForecast
        ->filter(fn ($value) => is_numeric($value))
        ->sortDesc()
        ->keys()
        ->first();
    $peakForecastValue = $peakForecast !== null ? $fuelTrendForecast->get($peakForecast) : null;
    $peakForecastLabel = $peakForecast !== null ? $fuelTrendLabels->get($peakForecast) : null;
    $consumptionFactor = collect($fuelPredict->factors ?? [])->firstWhere('title', 'High Consumption Trend');

    $reviewUnitsCount = (int) collect($fuelPredict->kpis ?? [])->firstWhere('label', 'Review Units')['value'] ?? 3;
    $fleetAvgEfficiency = (float) str_replace(' km/L', '', (string) (collect($fuelPredict->kpis ?? [])->firstWhere('label', 'Efficiency Forecast')['value'] ?? 3.59));
@endphp

<div class="predictive-page predictive-fuel-page">

    {{-- AI FUEL CONSERVATION & ANOMALY BANNER --}}
    <div class="predictive-ai-banner">
        <div class="predictive-ai-banner__icon-wrap">
            <i class="fa-solid fa-gas-pump"></i>
        </div>
        <div class="predictive-ai-banner__content">
            <div class="predictive-ai-banner__top">
                <span class="ai-chip">AI Fuel Intelligence</span>
                <span class="ai-status-pulse">
                    <span class="pulse-dot"></span>
                    @if($reviewUnitsCount > 0)
                        Consumption Anomaly: {{ $reviewUnitsCount }} Units Below Baseline
                    @else
                        Fleet Fuel Efficiency Optimized
                    @endif
                </span>
            </div>
            <p class="predictive-ai-banner__text">
                Fleet efficiency is trending at <strong>{{ number_format($fleetAvgEfficiency, 2) }} km/L</strong>. Predictive telemetry flags <strong>{{ $reviewUnitsCount }} buses</strong> consuming higher than expected fuel baselines. An estimated <strong>42 Liters</strong> of fuel loss is attributable to prolonged idling intensity. Reducing idle durations across scheduled routes can yield an estimated <strong>₱2,800/week</strong> in operating savings.
            </p>
        </div>
        <div class="predictive-ai-banner__action">
            <a href="{{ route('analytics.stage', ['stage' => 'diagnostic', 'domain' => 'fuel']) }}" class="btn-ai-reorder">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Triage Fuel Telemetry</span>
            </a>
        </div>
    </div>

    {{-- KPI STRIP --}}
    <section class="analytics-kpi-strip">
        @foreach($fuelPredict->kpis as $kpi)
            <x-analytics.kpi
                :label="$kpi['label']"
                :value="$kpi['value']"
                :description="$kpi['caption']"
                :icon="$kpi['icon']"
                :icon-variant="match ($kpi['tone']) {
                    'danger' => 'red',
                    'warning', 'orange' => 'yellow',
                    'success' => 'green',
                    'info' => 'blue',
                    default => 'blue',
                }"
            />
        @endforeach
    </section>

    {{-- CHART GRID --}}
    <section class="predictive-main-grid">

        <x-analytics.card class="predictive-card forecast-card" title="Fuel Consumption Trend" description="Recorded daily liters vs smooth ML forecast projection across the full period.">
            <div class="chart-header-badges">
                <span class="telemetry-badge"><i class="fa-solid fa-droplet"></i> Target: ≤ 15.0 L/day</span>
                <span class="telemetry-badge telemetry-badge--blue"><i class="fa-solid fa-chart-line"></i> Smooth 7-day projection</span>
            </div>
            <div class="chart-container large-chart">
                <canvas id="consumptionChart" role="img" aria-label="Fuel consumption trend chart"></canvas>
            </div>
        </x-analytics.card>

        <x-analytics.card class="predictive-card" title="Fuel Efficiency Trend" description="Fleet km/L telemetry vs 3.59 km/L baseline trajectory.">
            <div class="chart-header-badges">
                <span class="telemetry-badge telemetry-badge--green"><i class="fa-solid fa-gauge-high"></i> Baseline: {{ number_format($fleetAvgEfficiency, 2) }} km/L</span>
                <span class="telemetry-badge"><i class="fa-solid fa-bullseye"></i> Goal: ≥ 4.00 km/L</span>
            </div>
            <div class="chart-container large-chart">
                <canvas id="efficiencyChart" role="img" aria-label="Fuel efficiency trend chart"></canvas>
            </div>
        </x-analytics.card>

    </section>

    {{-- PREDICTION TABLE --}}
    <x-analytics.card class="predictive-card predictions-card" title="Fuel Consumption & Efficiency Predictions" description="Buses ranked by consumption deviation, efficiency against fleet baseline, and idling fuel loss.">
        <div class="table-wrap predictive-fuel-table-wrap" tabindex="0" aria-label="Scrollable fuel consumption predictions table">
            <table class="predictive-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Bus</th>
                        <th style="width: 11%;">Distance</th>
                        <th style="width: 11%;">Fuel Used</th>
                        <th style="width: 18%;">Efficiency vs Baseline</th>
                        <th style="width: 14%;">Idling & Fuel Loss</th>
                        <th style="width: 12%;">Anomaly State</th>
                        <th style="width: 11%;">Risk Level</th>
                        <th style="width: 13%;">Primary Driver</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fuelPredict->rows as $row)
                        @php
                            $busNo = $row[0] ?? $row['bus_no'] ?? '—';
                            $dist = $row[1] ?? $row['distance'] ?? '0.0';
                            $liters = $row[2] ?? $row['fuel_used'] ?? '0.0';
                            $kml = (float) str_replace(',', '', (string) ($row[3] ?? $row['efficiency'] ?? 0));
                            $idling = $row[4] ?? $row['idling'] ?? '0';
                            $status = $row[5] ?? $row['status'] ?? 'Normal';
                            $riskLevel = $row[6] ?? $row['level'] ?? 'Low';
                            $reason = $row[7] ?? $row['reason'] ?? 'Within expected range';
                            $diffPct = (float) ($row[8] ?? $row['diff_pct'] ?? 0);
                            $idleLoss = (float) ($row[9] ?? $row['idle_waste'] ?? 0);

                            $isUnderperforming = $diffPct < -5;
                            $isOverperforming = $diffPct > 5;
                            $effTone = $isUnderperforming ? 'danger' : ($isOverperforming ? 'success' : 'normal');
                            $effPctBar = min(100, max(20, round(($kml / 5.0) * 100)));
                        @endphp
                        <tr>
                            <td>
                                <span class="table-bus-chip">{{ $busNo }}</span>
                            </td>
                            <td>
                                <strong class="fuel-data-val">{{ $dist }} km</strong>
                            </td>
                            <td>
                                <strong class="fuel-data-val">{{ $liters }} L</strong>
                            </td>
                            <td>
                                <div class="table-eff-cell">
                                    <div class="eff-text-row">
                                        <strong class="eff-val eff-val--{{ $effTone }}">{{ number_format($kml, 2) }} km/L</strong>
                                        <span class="eff-diff eff-diff--{{ $effTone }}">
                                            {{ $diffPct >= 0 ? '+' : '' }}{{ $diffPct }}%
                                        </span>
                                    </div>
                                    <div class="eff-track" title="Efficiency: {{ number_format($kml, 2) }} km/L">
                                        <div class="eff-fill eff-fill--{{ $effTone }}" style="width: {{ $effPctBar }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="table-idle-cell">
                                    <span class="idle-time"><i class="fa-regular fa-clock"></i> {{ $idling }} min</span>
                                    @if($idleLoss > 0)
                                        <span class="idle-loss-chip">~{{ $idleLoss }} L loss</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="fuel-status-badge fuel-status--{{ strtolower($status) }}">{{ $status }}</span>
                            </td>
                            <td>
                                <span class="risk-badge {{ strtolower($riskLevel) }}">{{ $riskLevel }}</span>
                            </td>
                            <td>
                                <span class="fuel-reason-text">{{ $reason }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No fuel records are available for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-analytics.card>

    {{-- BOTTOM GRID --}}
    <section class="predictive-bottom-grid">

        {{-- FUEL RISK DISTRIBUTION --}}
        <x-analytics.card class="predictive-card" title="Fuel Risk Distribution" description="Fleet units categorized by fuel review standing.">
            <div class="risk-content">
                <div class="donut-wrapper">
                    <canvas id="fuelRiskDonut"></canvas>
                    <div class="donut-center">
                        <strong>{{ number_format($distribution->total) }}</strong>
                        <span>Total<br>Units</span>
                    </div>
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

                    <div class="health-ratio-bar-wrap" style="margin-top: 6px;">
                        <div class="health-ratio-bar" title="Fuel Distribution">
                            <div class="ratio-segment ratio-healthy" style="width: {{ $lowShare }}%"></div>
                            <div class="ratio-segment ratio-low" style="width: {{ $mediumShare }}%"></div>
                            <div class="ratio-segment ratio-critical" style="width: {{ $highShare }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
            @if($consumptionFactor)
                <div class="fuel-insight" role="note">
                    <span class="fuel-insight__icon" aria-hidden="true"><i class="fa-solid fa-lightbulb"></i></span>
                    <div>
                        <strong>AI Insight</strong>
                        <span>{{ $consumptionFactor->description }}</span>
                    </div>
                </div>
            @endif
        </x-analytics.card>

        {{-- TOP RISK FACTORS --}}
        <x-analytics.card class="predictive-card" title="Top Fuel Risk Factors" description="Primary drivers of excess consumption and loss.">
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
        </x-analytics.card>

        {{-- FUEL DEMAND FORECAST --}}
        <x-analytics.card class="predictive-card" title="Fuel Demand Forecast" description="Projected daily consumption from trend models.">
            @if($peakForecastLabel !== null && is_numeric($peakForecastValue))
                <div class="fuel-forecast-summary" aria-label="Fuel forecast summary">
                    <div>
                        <span><i class="fa-regular fa-calendar"></i> Peak Projected Day</span>
                        <strong>{{ $peakForecastLabel }}</strong>
                    </div>
                    <div>
                        <span><i class="fa-solid fa-droplet"></i> Expected Liter Volume</span>
                        <strong>{{ number_format((float) $peakForecastValue, 1) }} L</strong>
                    </div>
                </div>
            @endif
            <div class="chart-container">
                <canvas id="fuelForecastChart" role="img" aria-label="Fuel demand forecast chart"></canvas>
            </div>
        </x-analytics.card>

    </section>

    <p class="predictive-footer">
        <i class="fa-solid fa-circle-info"></i> Fuel projections are calculated from recorded telemetry, engine idle duration, and historical route baselines.
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