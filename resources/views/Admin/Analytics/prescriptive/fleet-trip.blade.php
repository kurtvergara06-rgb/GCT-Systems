@php
    $fleetData = $prescriptive?->fleet;
    $kpis = collect($fleetData?->kpis ?? []);
    $actions = collect($fleetData?->actions ?? []);
    $chartRoutes = $actions->map(function ($a) {
        $parts = explode(' - ', $a['route']);
        return [
            'route_no' => $parts[0] ?? $a['route'],
            'corridor' => $parts[1] ?? '',
            'full' => $a['route'],
        ];
    })->values()->all();
@endphp

<div class="prescriptive-page prescriptive-fleet-page">

    {{-- KPI STRIP --}}
    <section class="analytics-kpi-strip" aria-label="Fleet Prescriptive KPIs">
        @foreach($kpis as $kpi)
            <x-analytics.kpi
                :label="$kpi['label']"
                :value="$kpi['value']"
                :description="$kpi['caption']"
                :icon="$kpi['icon']"
                :icon-variant="match ($kpi['tone']) {
                    'danger' => 'red',
                    'warning' => 'yellow',
                    'success' => 'green',
                    'purple' => 'purple',
                    default => 'blue',
                }"
            />
        @endforeach
    </section>

    {{-- MIDDLE GRID --}}
    <section class="prescriptive-domain-grid">

        {{-- Route Optimization Headway Chart --}}
        <x-analytics.card
            class="prescriptive-card prescriptive-chart-card"
            title="Headway & Schedule Recovery Simulation"
            description="Comparison of projected trip delay with vs without prescriptive dispatch staggering."
        >
            <x-slot:headerActions>
                <span class="ft-telemetry-badge">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>-24 mins peak savings</span>
                </span>
            </x-slot:headerActions>

            <div class="prescriptive-chart-legend" aria-hidden="true">
                <span class="chart-legend-item">
                    <span class="chart-legend-indicator delay"></span>
                    <span>Unmitigated Peak Delay</span>
                </span>
                <span class="chart-legend-item">
                    <span class="chart-legend-indicator prescribed"></span>
                    <span>Prescribed Staggered Headway</span>
                </span>
            </div>

            <div class="prescriptive-chart-container">
                <canvas id="fleetPrescriptiveChart" role="img" aria-label="Fleet schedule recovery chart"></canvas>
            </div>
        </x-analytics.card>

        {{-- Dispatch Playbook Action Queue --}}
        <x-analytics.card
            class="prescriptive-card"
            title="Prescribed Dispatch Playbooks"
            description="Real-time operational interventions ready for execution."
        >
            <div class="prescriptive-queue-list">
                @foreach($actions as $action)
                    <div class="prescriptive-queue-item">
                        <div class="queue-item-header">
                            <div class="queue-route-label">
                                <i class="fa-solid fa-route"></i>
                                <span>{{ $action['route'] }}</span>
                            </div>
                            <span class="queue-priority-indicator {{ strtolower($action['priority']) === 'high' ? 'high' : 'medium' }}">
                                <span class="priority-dot"></span>
                                {{ $action['priority'] }} Priority
                            </span>
                        </div>
                        <h4 class="queue-item-title">{{ $action['issue'] }}</h4>
                        <p class="queue-item-impact">
                            {{ $action['prescribed_action'] }}
                        </p>
                        <div class="queue-item-footer">
                            <div class="queue-impact-metric">
                                <i class="fa-solid fa-arrow-trend-up"></i>
                                <span>{{ $action['impact'] }}</span>
                            </div>
                            <a href="{{ $action['action_url'] }}" class="btn-queue-action">
                                <span>Apply Playbook</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-analytics.card>

    </section>

    {{-- ACTION ROADMAP TABLE --}}
    <x-analytics.card
        class="prescriptive-table-card"
        title="Corridor Dispatch Interventions & Mitigation Roadmap"
        description="Prescribed timetable modifications, standby staging, and turnaround policies."
    >
        <div class="analytics-table-wrapper">
            <table class="analytics-data-table prescriptive-table">
                <thead>
                    <tr>
                        <th>Corridor / Route</th>
                        <th>Identified Bottleneck</th>
                        <th>Prescribed Operational Strategy</th>
                        <th>Expected Delay Recovery</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actions as $action)
                        <tr>
                            <td>
                                <strong class="table-route-name">{{ $action['route'] }}</strong>
                            </td>
                            <td>
                                <span class="table-issue-tag">{{ $action['issue'] }}</span>
                            </td>
                            <td>
                                <span class="table-prescription-text">{{ $action['prescribed_action'] }}</span>
                            </td>
                            <td>
                                <span class="gain-badge green">
                                    <i class="fa-solid fa-arrow-trend-up"></i>
                                    <span>{{ $action['impact'] }}</span>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill {{ strtolower($action['priority']) === 'high' ? 'critical' : 'warning' }}">
                                    <span class="status-dot"></span>
                                    {{ $action['priority'] }}
                                </span>
                            </td>
                            <td>
                                <span class="prescriptive-status-pill {{ strtolower(str_replace(' ', '-', $action['status'])) }}">
                                    <i class="fa-solid {{ str_contains(strtolower($action['status']), 'recommend') ? 'fa-circle-check' : 'fa-clock' }}"></i>
                                    <span>{{ $action['status'] }}</span>
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ $action['action_url'] }}" class="btn-table-action">
                                    <span>Dispatch</span>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-analytics.card>

    {{-- HUMAN-IN-THE-LOOP AI GOVERNANCE --}}
    <section class="prescriptive-governance-section">
        <h3 class="gov-heading">
            <i class="fa-solid fa-shield-halved"></i>
            Prescriptive Dispatch Governance & Constraints
        </h3>
        <div class="gov-grid">
            <div class="gov-card">
                <div class="gov-card__icon blue"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <h4>Driver Shift Limits Compliance</h4>
                    <p>Schedule staggering honors the DOLE and LTFRB 8-hour maximum continuous driving regulations without triggering overtime penalties.</p>
                </div>
            </div>
            <div class="gov-card">
                <div class="gov-card__icon green"><i class="fa-solid fa-bus"></i></div>
                <div>
                    <h4>Depot Standby Staging Protocol</h4>
                    <p>Standby buses GCT-104 & GCT-109 maintain hot-readiness at Ayala depot with 5-minute activation SLA for peak surge demand.</p>
                </div>
            </div>
            <div class="gov-card">
                <div class="gov-card__icon purple"><i class="fa-solid fa-sliders"></i></div>
                <div>
                    <h4>Dynamic Layover Windows</h4>
                    <p>Turnaround buffers adapt dynamically between 10 to 18 minutes based on live telemetry congestion indices along Osmeña corridor.</p>
                </div>
            </div>
        </div>
    </section>

</div>

<script>
    window.fleetPrescriptiveData = {
        routes: @json($chartRoutes),
        unmitigated: [18, 22, 16, 12],
        prescribed: [4, 12, 8, 3]
    };
</script>

