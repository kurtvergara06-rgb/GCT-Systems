@php
    $health = $predictive->bus_health;
    $distribution = $health->distribution;
    $totalLoops = max(1, (int) $distribution->total);
    $activePct = round(($distribution->active / $totalLoops) * 100);
    $maintenancePct = round(($distribution->maintenance / $totalLoops) * 100);
    $inactivePct = max(0, 100 - $activePct - $maintenancePct);

    $legendRows = collect([
        ['label' => 'Active', 'value' => $distribution->active, 'pct' => $activePct, 'class' => 'low'],
        ['label' => 'Under Maintenance', 'value' => $distribution->maintenance, 'pct' => $maintenancePct, 'class' => 'medium'],
        ['label' => 'Inactive', 'value' => $distribution->inactive, 'pct' => $inactivePct, 'class' => 'high'],
    ])->sortByDesc('value');
@endphp

<div class="predictive-page predictive-health-page">

    {{-- KPI STRIP --}}
    <section class="predictive-kpis">
        @foreach($health->kpis as $kpi)
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
    <section class="predictive-main-grid-two">

        <article class="predictive-card forecast-card">
            <div class="card-heading">
                <div>
                    <h3>Fleet Status Distribution</h3>
                    <p>Recorded bus status used as the maintenance forecast basis.</p>
                </div>
            </div>
            <div class="risk-content">
                <div class="donut-wrapper">
                    <canvas id="busHealthDonut"></canvas>
                    <div class="donut-center">
                        <strong id="busHealthDonutTotal">{{ number_format($distribution->total) }}</strong>
                        <span>Total<br>Buses</span>
                    </div>
                </div>
                <div class="risk-legend">
                    @foreach($legendRows as $item)
                        <div>
                            <span class="legend-dot {{ $item['class'] }}"></span>
                            <span>{{ $item['label'] }}</span>
                            <strong>{{ number_format($item['value']) }} ({{ $item['pct'] }}%)</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </article>

        <article class="predictive-card health-issues-card">
            <div class="card-heading">
                <div>
                    <h3>Maintenance Signals</h3>
                    <p>Workload indicators from job order records.</p>
                </div>
            </div>
            <div class="issue-list">
                @foreach($health->issues as $issue)
                    <div class="issue-row">
                        <div class="issue-icon {{ $issue->tone }}">
                            <i class="fa-solid {{ $issue->icon }}"></i>
                        </div>
                        <div class="issue-info">
                            <strong>{{ $issue->title }}</strong>
                            <span>{{ $issue->description }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

    </section>

    {{-- PREDICTION TABLE --}}
    <section class="predictive-card predictions-card">
        <div class="card-heading">
            <div>
                <h3>Bus Maintenance Forecast</h3>
                <p>Buses ranked by maintenance risk from job order attention.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="predictive-table">
                <thead>
                    <tr>
                        <th>Bus</th>
                        <th>Plate No.</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th>Open Orders</th>
                        <th>Overdue</th>
                        <th>Risk Level</th>
                        <th>Attention Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($health->rows as $row)
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
                            <td colspan="8">No buses are registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="predictive-footer">
        Maintenance forecasts are derived from bus status and recorded job order workload. Results may vary.
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
