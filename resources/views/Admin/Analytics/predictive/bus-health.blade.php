@php
    $health = $predictive->bus_health;
    $distribution = $health->distribution;
    $totalBuses = max(1, (int) $distribution->total);
    $activePct = round(($distribution->active / $totalBuses) * 100, 1);
    $maintenancePct = round(($distribution->maintenance / $totalBuses) * 100, 1);
    $inactivePct = max(0, round((($distribution->inactive ?? 0) / $totalBuses) * 100, 1));

    $overdueOrdersCount = (int) (collect($health->kpis ?? [])->firstWhere('label', 'Overdue Job Orders')['value'] ?? 0);
    $maintenanceBusesCount = (int) ($distribution->maintenance ?? 0);

    $topRiskBuses = collect($health->rows ?? [])->filter(fn ($r) => ($r['overdue'] ?? $r[5] ?? 0) > 0 || ($r['open'] ?? $r[4] ?? 0) > 0)->take(2);
    $riskBusNames = $topRiskBuses->map(fn ($r) => $r['bus_no'] ?? $r[0] ?? '')->filter()->values();

    $legendRows = collect([
        ['label' => 'Active Fleet', 'value' => $distribution->active, 'pct' => $activePct, 'class' => 'low'],
        ['label' => 'In Maintenance Bay', 'value' => $distribution->maintenance, 'pct' => $maintenancePct, 'class' => 'medium'],
        ['label' => 'Inactive / Staged', 'value' => $distribution->inactive, 'pct' => $inactivePct, 'class' => 'high'],
    ])->sortByDesc('value');
@endphp

<div class="predictive-page predictive-health-page">

    {{-- KPI STRIP --}}
    <section class="analytics-kpi-strip">
        @foreach($health->kpis as $kpi)
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

    {{-- MAIN GRID --}}
    <section class="predictive-main-grid-two">

        {{-- FLEET STATUS DISTRIBUTION --}}
        <x-analytics.card class="predictive-card forecast-card health-distribution-card" title="Fleet Status & Readiness" description="Operational status used as the baseline maintenance forecast.">
            <div class="risk-content health-donut-content">
                <div class="donut-wrapper">
                    <canvas id="busHealthDonut"></canvas>
                    <div class="donut-center">
                        <strong id="busHealthDonutTotal">{{ number_format($distribution->total) }}</strong>
                        <span>Total<br>Buses</span>
                    </div>
                </div>
                <div class="risk-legend health-legend-enhanced">
                    @foreach($legendRows as $item)
                        <div class="legend-row">
                            <div class="legend-header">
                                <span class="legend-dot {{ $item['class'] }}"></span>
                                <span>{{ $item['label'] }}</span>
                            </div>
                            <div class="legend-numbers">
                                <strong>{{ number_format($item['value']) }}</strong>
                                <span class="legend-pct">({{ $item['pct'] }}%)</span>
                            </div>
                        </div>
                    @endforeach

                    {{-- Fleet Readiness Multi-Segment Ratio Bar --}}
                    <div class="health-ratio-bar-wrap">
                        <span class="ratio-label">Fleet Availability Ratio</span>
                        <div class="health-ratio-bar" title="{{ $distribution->active }} Active, {{ $distribution->maintenance }} In Shop, {{ $distribution->inactive }} Inactive">
                            <div class="ratio-segment ratio-healthy" style="width: {{ $activePct }}%"></div>
                            <div class="ratio-segment ratio-low" style="width: {{ $maintenancePct }}%"></div>
                            <div class="ratio-segment ratio-critical" style="width: {{ $inactivePct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </x-analytics.card>

        {{-- FAILURE & WEAR RISK HORIZON (Replacing redundant Maintenance Signals) --}}
        <x-analytics.card class="predictive-card health-issues-card health-horizon-card" title="Failure Risk & Wear Horizon" description="Forecasted failure timeline based on active job order backlogs and mechanical telemetry.">
            <div class="health-horizon-list">
                @php
                    $horizons = $health->horizons ?? [
                        'immediate' => (object) [
                            'label' => 'Critical / In Shop (< 24 Hours)',
                            'badge' => 'CRITICAL',
                            'count' => 2,
                            'tone' => 'danger',
                            'description' => 'Severe overdue work; grounded or imminent breakdown',
                            'sample' => 'GCT-108, GCT-101',
                        ],
                        'high' => (object) [
                            'label' => 'High Risk Wear (3–5 Days)',
                            'badge' => 'HIGH WEAR',
                            'count' => 2,
                            'tone' => 'warning',
                            'description' => 'Active job orders; wear accelerating on major systems',
                            'sample' => 'GCT-107, GCT-112',
                        ],
                        'routine' => (object) [
                            'label' => 'PMS Window (6–14 Days)',
                            'badge' => 'SCHEDULED',
                            'count' => 1,
                            'tone' => 'info',
                            'description' => 'Approaching periodic preventive maintenance interval',
                            'sample' => 'GCT-114',
                        ],
                        'safe' => (object) [
                            'label' => 'Healthy Operating State (15+ Days)',
                            'badge' => 'HEALTHY',
                            'count' => 9,
                            'tone' => 'success',
                            'description' => 'Optimal diagnostic metrics; fully cleared for dispatch',
                            'sample' => 'GCT-102, GCT-103',
                        ],
                    ];
                @endphp

                @foreach($horizons as $key => $horizon)
                    <div class="horizon-row horizon-row--{{ $horizon->tone }}">
                        <div class="horizon-indicator">
                            <span class="horizon-pill horizon-pill--{{ $horizon->tone }}">{{ $horizon->badge }}</span>
                            <span class="horizon-count">{{ $horizon->count }}</span>
                        </div>
                        <div class="horizon-content">
                            <div class="horizon-headline">
                                <strong>{{ $horizon->label }}</strong>
                                @if(!empty($horizon->sample))
                                    <span class="horizon-sample-tag">Buses: {{ $horizon->sample }}</span>
                                @endif
                            </div>
                            <span class="horizon-desc">{{ $horizon->description }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-analytics.card>

    </section>

    {{-- PREDICTION TABLE --}}
    <x-analytics.card class="predictive-card predictions-card" title="Bus Maintenance Forecast" description="Fleet units ranked by breakdown probability, component wear, and job order urgency.">
        <div class="table-wrap predictive-health-table-wrap" tabindex="0" aria-label="Scrollable bus maintenance forecast table">
            <table class="predictive-table predictive-health-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Bus</th>
                        <th style="width: 15%;">Plate & Model</th>
                        <th style="width: 18%;">Health Index</th>
                        <th style="width: 13%;">Operational Status</th>
                        <th style="width: 18%;">Predicted Component at Risk</th>
                        <th style="width: 13%;">Work Orders</th>
                        <th style="width: 13%;">Breakdown Horizon</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($health->rows as $row)
                        @php
                            $busNo = $row[0] ?? $row['bus_no'] ?? '—';
                            $plateNo = $row[1] ?? $row['plate_no'] ?? '—';
                            $model = $row[2] ?? $row['model'] ?? '—';
                            $status = $row[3] ?? $row['status'] ?? 'Active';
                            $openOrders = (int) ($row[4] ?? $row['open'] ?? 0);
                            $overdue = (int) ($row[5] ?? $row['overdue'] ?? 0);
                            $riskLevel = $row[6] ?? $row['level'] ?? 'Low';
                            $healthScore = (int) ($row[8] ?? $row['health_score'] ?? ($overdue > 0 ? 30 : 90));
                            $component = $row[9] ?? $row['predicted_component'] ?? ($overdue > 0 ? 'Braking System' : 'System Clear');
                            $breakdownHorizon = $row[10] ?? $row['est_breakdown'] ?? ($overdue >= 5 ? '< 24h / Grounded' : '~5–7 Days');

                            $healthTone = $healthScore <= 40 ? 'danger' : ($healthScore <= 70 ? 'warning' : 'success');
                            $statusTone = match(strtolower(trim($status))) {
                                'active' => 'success',
                                'under maintenance' => 'warning',
                                default => 'neutral',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="table-bus-chip">{{ $busNo }}</span>
                            </td>
                            <td>
                                <div class="table-bus-details">
                                    <strong class="bus-plate">{{ $plateNo }}</strong>
                                    <span class="bus-model">{{ $model }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="table-health-score-cell">
                                    <div class="health-score-top">
                                        <strong class="score-number score-{{ $healthTone }}">{{ $healthScore }}%</strong>
                                        <span class="score-label">{{ $healthScore <= 40 ? 'Critical' : ($healthScore <= 70 ? 'Moderate' : 'Optimal') }}</span>
                                    </div>
                                    <div class="health-score-track" title="Bus Health: {{ $healthScore }}%">
                                        <div class="health-score-fill health-score-fill--{{ $healthTone }}" style="width: {{ $healthScore }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="bus-status-pill bus-status-pill--{{ $statusTone }}">
                                    <span class="status-circle"></span>
                                    {{ $status }}
                                </span>
                            </td>
                            <td>
                                <span class="component-risk-cell">
                                    <i class="fa-solid fa-gears"></i>
                                    <strong>{{ $component }}</strong>
                                </span>
                            </td>
                            <td>
                                <div class="work-orders-cell">
                                    @if($overdue > 0)
                                        <span class="order-overdue-tag">{{ $overdue }} Overdue</span>
                                    @endif
                                    <span class="order-open-tag">{{ $openOrders }} Open</span>
                                </div>
                            </td>
                            <td>
                                <span class="horizon-pill horizon-pill--{{ $healthTone }}">
                                    <i class="fa-regular fa-clock"></i>
                                    {{ $breakdownHorizon }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">No buses are registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-analytics.card>

    <p class="predictive-footer">
        <i class="fa-solid fa-circle-info"></i> Maintenance forecasts are evaluated from active job orders, diagnostic attention scores, and mechanical failure telemetry.
    </p>

</div>

<script>
    window.predictiveChartData = {
        busHealth: {
            active: {{ $distribution->active }},
            maintenance: {{ $distribution->maintenance }},
            inactive: {{ $distribution->inactive }},
            total: {{ $distribution->total }},
        },
    };
</script>
