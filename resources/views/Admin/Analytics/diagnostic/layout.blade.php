@php
    $activeAnalysis = in_array(request('domain'), ['fuel'], true) ? 'fuel' : 'fleet-trip';
    $diagnosticUrl = route('analytics.stage', ['stage' => 'diagnostic'], false);
    $normalizedSelectedBus = strtoupper(trim((string) $selectedBus));

    $incidentData = $diagnostic->incident;
    $fuelData = $diagnostic->fuel;

    $periodOptions = [
        'this-month' => 'This Month',
        'this-week' => 'This Week',
        'last-30-days' => 'Last 30 Days',
        'last-90-days' => 'Last 90 Days',
        'last-12-months' => 'Last 12 Months',
    ];

    if ($activeAnalysis === 'fleet-trip') {
        $chartPayload = [
            'activeAnalysis' => 'fleet-trip',
            'trendLabels' => $incidentData->incident_trend->pluck('label')->toArray(),
            'trendIncidents' => $incidentData->incident_trend->pluck('incidents')->toArray(),
            'trendReplacements' => $incidentData->incident_trend->pluck('replacements')->toArray(),
            'causeLabels' => $incidentData->cause_distribution->pluck('label')->toArray(),
            'causeValues' => $incidentData->cause_distribution->pluck('count')->toArray(),
            'causeColors' => $incidentData->cause_distribution->pluck('color')->toArray(),
            'intervalLabels' => $incidentData->replacement_distribution->pluck('category')->toArray(),
            'intervalValues' => $incidentData->replacement_distribution->pluck('count')->toArray(),
            'intervalColors' => $incidentData->replacement_distribution->pluck('color')->toArray(),
        ];
    } else {
        $chartPayload = [
            'activeAnalysis' => 'fuel',
            'fuelTrendLabels' => $fuelData->trend->pluck('label')->toArray(),
            'fuelTrendLiters' => $fuelData->trend->pluck('fuel_liters')->toArray(),
            'fuelTrendEfficiency' => $fuelData->trend->pluck('efficiency')->toArray(),
            'efficiencyLabels' => $fuelData->efficiency_distribution->pluck('label')->toArray(),
            'efficiencyValues' => $fuelData->efficiency_distribution->pluck('count')->toArray(),
            'efficiencyColors' => $fuelData->efficiency_distribution->pluck('color')->toArray(),
        ];
    }

    $currentFilters = request()->only(['period', 'bus', 'preview']);
    $isDemo = $activeAnalysis === 'fleet-trip' ? $incidentData->is_demo : $fuelData->is_demo;
@endphp
<x-layout.app
    title="FROMS - Diagnostic Analytics"
    :assets="[
        'resources/css/Admin/Analytics/diagnostic/diagnostic-rebuild.css',
        'resources/css/Admin/Analytics/design-system.css',
        'resources/js/Admin/Analytics/diagnostic/diagnostic-analytics.js'
    ]"
    :partial-navigation="false"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />
        <main class="main analytics-stage-page diagnostic-rebuild">
            <x-layout.topbar
                title="Diagnostic Analytics"
                subtitle="Incident and replacement evidence, fuel consumption patterns, and review signals · Objective 6.2."
            />

            <!-- Hidden JSON payload consumed by Chart.js script -->
            <script type="application/json" id="diagnosticAnalyticsData">
                {!! json_encode($chartPayload) !!}
            </script>

            <x-analytics.stage-controls
                :url="$diagnosticUrl"
                :tabs="[
                    'fleet-trip' => ['Incident & Replacement', 'fa-triangle-exclamation'],
                    'fuel' => ['Fuel & Consumption', 'fa-gas-pump'],
                ]"
                :active="$activeAnalysis"
                :period="$period"
                :period-options="$periodOptions"
                :bus-options="$busOptions"
                :selected-bus="$selectedBus"
                :date-label="ucwords(str_replace('-', ' ', $period))"
            />
            @if($isDemo)
                <div class="diagnostic-sample-note" role="note" style="display:flex;justify-content:flex-end;margin:8px 0 12px;color:#7b5c14;font-size:10px;font-weight:700;">
                    <span style="background:#fff7df;border:1px solid #eedda6;padding:5px 9px;border-radius:7px;"><i class="fa-solid fa-flask" aria-hidden="true"></i> Sample Data · Illustrative, not verified records</span>
                </div>
            @endif

            @if($activeAnalysis === 'fleet-trip')
                <!-- =========================================================================
                     THESIS OBJECTIVE 6.2.1 — INCIDENT & REPLACEMENT ANALYSIS
                     ========================================================================= -->

                <!-- 1. Top 4 KPI Summary Cards Grid -->
                <section class="stats-grid diagnostic-stats-grid">
                    <!-- Total Incidents -->
                    <x-ui.summary-card
                        label="Total Incidents"
                        value="{{ $incidentData->total_incidents }}"
                        small="{{ $incidentData->breakdowns_count }} breakdowns · {{ $incidentData->resolved_count }} resolved"
                        icon="fa-triangle-exclamation"
                        color="red"
                        href="{{ route('incidents') }}"
                    />

                    <!-- Bus Replacements -->
                    <x-ui.summary-card
                        label="Bus Replacements"
                        value="{{ $incidentData->replacements_count }}"
                        small="{{ round(($incidentData->replacements_count / max(1, $incidentData->total_incidents)) * 100) }}% replacement swap rate"
                        icon="fa-people-arrows"
                        color="purple"
                        href="{{ route('incidents') }}"
                    />

                    <!-- Average Downtime -->
                    <x-ui.summary-card
                        label="Average Downtime"
                        value="{{ $incidentData->average_downtime_formatted }}"
                        small="Incident-to-dispatch response time"
                        icon="fa-clock-rotate-left"
                        color="blue"
                    />

                    <!-- Primary Root Cause -->
                    <x-ui.summary-card
                        label="Primary Root Cause"
                        class="stat-card--text"
                        :value="$incidentData->most_common_cause"
                        small="Leading operational failure mode"
                        icon="fa-screwdriver-wrench"
                        color="yellow"
                    />
                </section>

                <!-- 2. Charts Row 1: Incidents & Replacement Trend + Root-Cause Donut -->
                <div class="diagnostic-grid grid-wide-left">
                    <!-- Incidents vs Replacements Over Time -->
                    <div class="diagnostic-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">DIAGNOSTIC FREQUENCY</span>
                                <h2><i class="fa-solid fa-chart-column"></i> Incidents &amp; Replacement Trend</h2>
                                <p>Recorded disruptions and emergency replacement bus dispatches over time.</p>
                            </div>

                            <div class="chart-header-actions">
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-triangle-exclamation text-red"></i>
                                    <span>Incidents: <strong>{{ $incidentData->total_incidents }}</strong></span>
                                </div>
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-people-arrows text-purple"></i>
                                    <span>Replacements: <strong>{{ $incidentData->replacements_count }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="diagnostic-chart-shell">
                            <canvas id="diagnosticIncidentTrendChart"></canvas>
                        </div>
                    </div>

                    <!-- Root-Cause Classification Donut -->
                    <div class="diagnostic-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">ROOT-CAUSE ANALYSIS</span>
                                <h2><i class="fa-solid fa-chart-pie"></i> Incidents by Cause</h2>
                                <p>Primary diagnostic categories derived from incident failure logs.</p>
                            </div>
                            <span class="chart-kpi-pill">
                                <strong>{{ $incidentData->cause_distribution->count() }}</strong> Categories
                            </span>
                        </div>

                        <div class="fleet-health-visual-wrap">
                            <div class="fleet-health-chart-container">
                                <canvas id="diagnosticCauseDonutChart"></canvas>
                                <div class="fleet-health-chart-center" aria-hidden="true">
                                    <strong>{{ $incidentData->total_incidents }}</strong>
                                    <span>Total</span>
                                </div>
                            </div>
                            <div class="fleet-health-status-stack">
                                @foreach($incidentData->cause_distribution as $cause)
                                    <div class="fleet-health-row">
                                        <div class="fleet-health-left">
                                            <span class="status-indicator-dot" style="background:{{ $cause->color }}"></span>
                                            <div class="status-name-desc">
                                                <strong>{{ $cause->label }}</strong>
                                                <small>{{ $cause->count }} event{{ $cause->count === 1 ? '' : 's' }} documented</small>
                                            </div>
                                        </div>
                                        <div class="fleet-health-right">
                                            <span class="fleet-count" style="color:{{ $cause->color }}">{{ $cause->count }}</span>
                                            <small>{{ $cause->pct }}%</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Charts Row 2: Replacement Outcomes + Severity Metrics -->
                <div class="diagnostic-grid">
                    <!-- Replacement Dispatch Response Intervals -->
                    <div class="diagnostic-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">DISPATCH LATENCY</span>
                                <h2><i class="fa-solid fa-stopwatch"></i> Replacement Response Latency</h2>
                                <p>Interval from incident breakdown report to replacement bus en-route dispatch.</p>
                            </div>
                            <a href="{{ route('incidents') }}" class="dashboard-view-link">
                                <span>Incidents</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="diagnostic-chart-shell short">
                            <canvas id="diagnosticReplacementIntervalChart"></canvas>
                        </div>
                    </div>

                    <!-- Fleet Reliability & Severity Diagnostic Overview -->
                    <div class="diagnostic-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">RELIABILITY INDEX</span>
                                <h2><i class="fa-solid fa-shield-halved"></i> Fleet Disruption &amp; Recovery Index</h2>
                                <p>Diagnostic assessment of service disruption containment and recovery efficiency.</p>
                            </div>
                            <span class="chart-kpi-pill text-green">
                                <i class="fa-solid fa-circle-check"></i> Monitored
                            </span>
                        </div>

                        <div class="diagnostic-metric-tiles">
                            <div class="diagnostic-tile">
                                <i class="fa-solid fa-shield-heart text-green"></i>
                                <div>
                                    <strong>{{ $incidentData->safety_score }}%</strong>
                                    <small>Fleet Reliability Score</small>
                                </div>
                            </div>

                            <div class="diagnostic-tile">
                                <i class="fa-solid fa-clock text-blue"></i>
                                <div>
                                    <strong>{{ $incidentData->average_downtime_formatted }}</strong>
                                    <small>Average Dispatch Interval</small>
                                </div>
                            </div>

                            <div class="diagnostic-tile">
                                <i class="fa-solid fa-wrench text-amber"></i>
                                <div>
                                    <strong>{{ round(($incidentData->resolved_count / max(1, $incidentData->total_incidents)) * 100) }}%</strong>
                                    <small>Incident Resolution Rate</small>
                                </div>
                            </div>
                        </div>

                        <div class="baseline-callout-card" style="margin-top: 14px;">
                            <h4><i class="fa-solid fa-circle-info"></i> Thesis Objective 6.2.1 Diagnostic Benchmark</h4>
                            <p>
                                Tracks unplanned bus breakdowns, root-cause categories, and dispatch response times to diagnose recurring equipment failures and assess fleet replacement readiness.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 4. Detailed Diagnostic Evidence Table (Full Width) -->
                <div class="diagnostic-dashboard-card full-width">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="dashboard-eyebrow">INCIDENT EVIDENCE &amp; LOGS</span>
                            <h2><i class="fa-solid fa-file-medical"></i> Incident Diagnostic Evidence &amp; Replacement Records</h2>
                            <p>Detailed incident reports, affected buses, replacement pairings, and maintenance linkages.</p>
                        </div>
                        <a href="{{ route('incidents') }}" class="dashboard-view-link">
                            <span>View Operations Center</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    @if($incidentData->records->isNotEmpty())
                        <div class="diagnostic-table-wrapper">
                            <table class="diagnostic-table">
                                <thead>
                                    <tr>
                                        <th>Incident No</th>
                                        <th>Affected Bus &amp; Driver</th>
                                        <th>Route / Client</th>
                                        <th>Diagnostic Root Cause</th>
                                        <th>Replacement Bus</th>
                                        <th>Downtime</th>
                                        <th>Status</th>
                                        <th>Maintenance Link</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($incidentData->records as $record)
                                        <tr>
                                            <td>
                                                <span class="code-pill">{{ $record->incident_no }}</span>
                                                @if($record->is_demo)
                                                    <span class="badge-chip demo-badge" style="font-size: 9px; padding: 1px 4px; background: #fef3c7; color: #b45309; margin-left: 4px;">SAMPLE</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                                    <span class="bus-chip"><i class="fa-solid fa-bus"></i> {{ $record->bus_no }}</span>
                                                    <small class="text-muted"><i class="fa-solid fa-user"></i> {{ $record->driver_name }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                <small><strong>{{ $record->route_name }}</strong></small>
                                            </td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                                    <strong>{{ $record->incident_type }}</strong>
                                                    <small class="text-muted">{{ \Illuminate\Support\Str::limit($record->description, 50) }}</small>
                                                </div>
                                            </td>
                                            <td>
                                                @if($record->replacement_bus_no)
                                                    <span class="swap-chip">
                                                        <i class="fa-solid fa-bus-flip"></i> {{ $record->replacement_bus_no }}
                                                    </span>
                                                @else
                                                    <small class="text-muted"><i class="fa-solid fa-wrench"></i> On-Site Repair</small>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>{{ $record->downtime_formatted }}</strong>
                                            </td>
                                            <td>
                                                <span class="badge-status {{ strtolower($record->status) === 'resolved' ? 'optimal' : 'anomaly' }}">
                                                    {{ $record->status }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($record->job_order_no)
                                                    <span class="code-pill" style="background:#f5f3ff; border-color:#ddd6fe; color:#6d28d9;">
                                                        <i class="fa-solid fa-screwdriver-wrench"></i> {{ $record->job_order_no }}
                                                    </span>
                                                @else
                                                    <small class="text-muted">—</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="dashboard-empty-state">
                            <div class="dashboard-empty-icon"><i class="fa-solid fa-shield-check"></i></div>
                            <h3>Zero Incidents Recorded</h3>
                            <p>No operational disruptions or breakdown reports match the current filters.</p>
                        </div>
                    @endif
                </div>

            @else
                <!-- =========================================================================
                     THESIS OBJECTIVE 6.2.2 — FUEL & CONSUMPTION ANALYSIS
                     ========================================================================= -->

                <!-- 1. Top 4 KPI Summary Cards Grid -->
                <section class="stats-grid diagnostic-stats-grid">
                    <!-- Total Fuel Consumption -->
                    <x-ui.summary-card
                        label="Total Fuel Consumption"
                        value="{{ number_format($fuelData->total_fuel, 1) }} L"
                        small="Recorded fuel volume across fleet"
                        icon="fa-gas-pump"
                        color="blue"
                    />

                    <!-- Average Fuel Efficiency -->
                    <x-ui.summary-card
                        label="Fleet Avg Efficiency"
                        value="{{ number_format($fuelData->fleet_average, 2) }} km/L"
                        small="Derived from total distance &amp; fuel"
                        icon="fa-gauge-high"
                        color="green"
                    />

                    <!-- Objective 6.2.2 Anomalies -->
                    <x-ui.summary-card
                        label="Baseline Anomalies"
                        value="{{ $fuelData->anomalies_count }}"
                        small="≥20% decrease from 3-test baseline"
                        icon="fa-triangle-exclamation"
                        color="red"
                    />

                    <!-- Estimated Idle Loss -->
                    <x-ui.summary-card
                        label="Estimated Idle Loss"
                        value="{{ number_format($fuelData->idle_loss_liters, 1) }} L"
                        small="Fuel burned during engine idling"
                        icon="fa-stopwatch"
                        color="cyan"
                    />
                </section>

                <!-- 2. Charts Row 1: Dual-Axis Trend + Efficiency Distribution Donut -->
                <div class="diagnostic-grid grid-wide-left">
                    <!-- Dual-Axis Fuel Consumption (L) & Efficiency (km/L) Trend -->
                    <div class="diagnostic-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">CONSUMPTION TELEMETRY</span>
                                <h2><i class="fa-solid fa-chart-column"></i> Fuel Volume &amp; Efficiency Trend</h2>
                                <p>Recorded fuel liters consumed (bars) alongside vehicle fuel economy (line).</p>
                            </div>

                            <div class="chart-header-actions">
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-gas-pump text-blue"></i>
                                    <span>Total: <strong>{{ number_format($fuelData->total_fuel, 1) }} L</strong></span>
                                </div>
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-gauge-high text-green"></i>
                                    <span>Avg: <strong>{{ number_format($fuelData->fleet_average, 2) }} km/L</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="diagnostic-chart-shell">
                            <canvas id="diagnosticFuelTrendChart"></canvas>
                        </div>
                    </div>

                    <!-- Fleet Fuel Efficiency Tiers Donut -->
                    <div class="diagnostic-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">EFFICIENCY TIERS</span>
                                <h2><i class="fa-solid fa-chart-pie"></i> Efficiency Distribution</h2>
                                <p>Fleet vehicles categorised against target operational fuel standards.</p>
                            </div>
                            <span class="chart-kpi-pill text-blue">
                                <strong>{{ $fuelData->bus_summaries->count() }}</strong> Units Monitored
                            </span>
                        </div>

                        <div class="fleet-health-visual-wrap">
                            <div class="fleet-health-chart-container">
                                <canvas id="diagnosticEfficiencyDonutChart"></canvas>
                                <div class="fleet-health-chart-center" aria-hidden="true">
                                    <strong>{{ number_format($fuelData->fleet_average, 1) }}</strong>
                                    <span>km / L</span>
                                </div>
                            </div>
                            <div class="fleet-health-status-stack">
                                @foreach($fuelData->efficiency_distribution as $tier)
                                    <div class="fleet-health-row">
                                        <div class="fleet-health-left">
                                            <span class="status-indicator-dot" style="background:{{ $tier->color }}"></span>
                                            <div class="status-name-desc">
                                                <strong>{{ $tier->label }}</strong>
                                                <small>{{ $tier->count }} vehicle{{ $tier->count === 1 ? '' : 's' }} in tier</small>
                                            </div>
                                        </div>
                                        <div class="fleet-health-right">
                                            <span class="fleet-count" style="color:{{ $tier->color }}">{{ $tier->count }}</span>
                                            <small>{{ $tier->pct }}%</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Charts Row 2: Buses with Review Signals + Idle Diagnostic Breakdown -->
                <div class="diagnostic-grid">
                    <!-- Buses with Review Signals -->
                    <div class="diagnostic-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">INSPECTION SIGNALS</span>
                                <h2><i class="fa-solid fa-triangle-exclamation"></i> Buses Requiring Fuel Review</h2>
                                <p>Units flagged under Objective 6.2.2 baseline decrease or severe idle intensity.</p>
                            </div>
                            <span class="chart-kpi-pill text-red">
                                <strong>{{ $fuelData->review_units->count() }}</strong> Flagged
                            </span>
                        </div>

                        @if($fuelData->review_units->isNotEmpty())
                            <div class="fleet-health-status-stack" style="max-height: 250px;">
                                @foreach($fuelData->review_units as $unit)
                                    <div class="fleet-health-row">
                                        <div class="fleet-health-left">
                                            <span class="status-indicator-dot dot-red"></span>
                                            <div class="status-name-desc">
                                                <strong>{{ $unit->bus_no }}</strong>
                                                <small>
                                                    @if(isset($unit->drop_percent) && $unit->drop_percent < 0)
                                                        {{ abs($unit->drop_percent) }}% below 3-test baseline ({{ $unit->baseline_efficiency }} km/L)
                                                    @else
                                                        Below fleet average ({{ number_format((float)($unit->km_per_liter ?? 0), 2) }} km/L)
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                        <div class="fleet-health-right">
                                            <span class="badge-status anomaly">Confirmed Anomaly</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-empty-state" style="min-height: 160px; padding: 20px;">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-circle-check text-green"></i></div>
                                <h3>Zero Fuel Review Signals</h3>
                                <p>No vehicle in this selection violates the Objective 6.2.2 20% baseline reduction threshold.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Idling Impact & Idle Loss Breakdown -->
                    <div class="diagnostic-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">TELEMETRY IDLE IMPACT</span>
                                <h2><i class="fa-solid fa-hourglass-half"></i> Idling vs Moving Consumption</h2>
                                <p>Analysis of excess engine idling and non-productive fuel loss.</p>
                            </div>
                            <span class="chart-kpi-pill text-cyan">
                                <i class="fa-solid fa-gauge"></i> Telemetry
                            </span>
                        </div>

                        <div class="diagnostic-metric-tiles">
                            <div class="diagnostic-tile">
                                <i class="fa-solid fa-stopwatch text-cyan"></i>
                                <div>
                                    <strong>{{ round($fuelData->idling_median, 1) }}m</strong>
                                    <small>Median Idle / 100km</small>
                                </div>
                            </div>

                            <div class="diagnostic-tile">
                                <i class="fa-solid fa-fire-flame-curved text-red"></i>
                                <div>
                                    <strong>{{ number_format($fuelData->idle_loss_liters, 1) }} L</strong>
                                    <small>Estimated Fuel Lost</small>
                                </div>
                            </div>

                            <div class="diagnostic-tile">
                                <i class="fa-solid fa-gas-pump text-green"></i>
                                <div>
                                    <strong>{{ number_format($fuelData->total_distance, 0) }} km</strong>
                                    <small>Total Fleet Traveled</small>
                                </div>
                            </div>
                        </div>

                        <div class="baseline-callout-card" style="margin-top: 14px;">
                            <h4><i class="fa-solid fa-circle-info"></i> Idle Loss Calculation Rule</h4>
                            <p>
                                Computed using commercial telemetry idle rate (1.5 L / engine idle hour). Excessive idle duration is flagged when idle minutes per 100km exceed the fleet median by over 25%.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 4. Historical Baseline Evidence Table (Full Width) -->
                <div class="diagnostic-dashboard-card full-width">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="dashboard-eyebrow">OBJECTIVE 6.2.2 BASELINE EVIDENCE</span>
                            <h2><i class="fa-solid fa-table-list"></i> Vehicle-Specific Historical Baseline &amp; Anomaly Verification</h2>
                            <p>Verification of vehicle historical baseline (minimum 3 prior measurements) and confirmation of ≥20% efficiency drops.</p>
                        </div>
                        <span class="chart-kpi-pill text-blue">
                            <strong>{{ $fuelData->bus_summaries->count() }}</strong> Units Analyzed
                        </span>
                    </div>

                    @if($fuelData->bus_summaries->isNotEmpty())
                        <div class="diagnostic-table-wrapper">
                            <table class="diagnostic-table">
                                <thead>
                                    <tr>
                                        <th>Bus No</th>
                                        <th>Distance (km)</th>
                                        <th>Fuel (L)</th>
                                        <th>Measured Efficiency</th>
                                        <th>Prior Tests</th>
                                        <th>3-Test Baseline</th>
                                        <th>Baseline Variance</th>
                                        <th>Objective 6.2.2 Finding</th>
                                        <th>Diagnostic Advisory</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($fuelData->bus_summaries as $unit)
                                        @php
                                            $hasBaseline = $unit->has_baseline ?? false;
                                            $isAnomaly = $unit->is_objective_622_anomaly ?? false;
                                            $drop = $unit->drop_percent ?? 0;
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="bus-chip"><i class="fa-solid fa-bus"></i> {{ $unit->bus_no }}</span>
                                                @if($unit->is_demo ?? false)
                                                    <span class="badge-chip demo-badge" style="font-size: 9px; padding: 1px 4px; background: #fef3c7; color: #b45309; margin-left: 4px;">SAMPLE</span>
                                                @endif
                                            </td>
                                            <td>{{ number_format((float)($unit->distance_km ?? 0), 1) }}</td>
                                            <td><strong>{{ number_format((float)($unit->fuel_liters ?? 0), 1) }}</strong></td>
                                            <td>
                                                <strong>{{ number_format((float)($unit->km_per_liter ?? 0), 2) }} km/L</strong>
                                            </td>
                                            <td>
                                                <span class="badge-chip">{{ $unit->prior_measurements_count ?? 0 }} tests</span>
                                            </td>
                                            <td>
                                                @if($hasBaseline)
                                                    <strong>{{ number_format((float)($unit->baseline_efficiency ?? 0), 2) }} km/L</strong>
                                                @else
                                                    <small class="text-muted">Insufficient Baseline</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($hasBaseline)
                                                    <span style="font-weight: 700; color: {{ $drop <= -20 ? '#dc2626' : ($drop < 0 ? '#d97706' : '#16a34a') }};">
                                                        {{ $drop > 0 ? '+'.$drop : $drop }}%
                                                    </span>
                                                @else
                                                    <small class="text-muted">—</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($isAnomaly)
                                                    <span class="badge-status anomaly">Confirmed Anomaly (≥20% Drop)</span>
                                                @elseif($hasBaseline)
                                                    <span class="badge-status optimal">Within Baseline</span>
                                                @else
                                                    <span class="badge-status insufficient">Insufficient Baseline (&lt;3)</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($isAnomaly)
                                                    <small style="color: #b91c1c; font-weight: 600;">
                                                        <i class="fa-solid fa-wrench"></i> Refer to shop for fuel injector &amp; intake valve check
                                                    </small>
                                                @elseif(($unit->idling_minutes ?? 0) >= 120)
                                                    <small style="color: #d97706;">
                                                        <i class="fa-solid fa-stopwatch"></i> Excessive idle review with driver
                                                    </small>
                                                @else
                                                    <small class="text-muted">No diagnostic action required</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- 5. Thesis Objective 6.2.2 Rule Callout Card -->
                <div class="baseline-callout-card" style="margin-bottom: 24px;">
                    <h4><i class="fa-solid fa-circle-info"></i> Thesis Objective 6.2.2 Scientific Rule</h4>
                    <p>
                        A fuel consumption anomaly must not be treated as confirmed solely because it is below the fleet average. Objective 6.2.2 requires a vehicle-specific historical baseline with at least three prior valid measurements and a decrease of at least 20% to confirm an actionable mechanical inconsistency.
                    </p>
                </div>

            @endif
        </main>
    </div>
</x-layout.app>
