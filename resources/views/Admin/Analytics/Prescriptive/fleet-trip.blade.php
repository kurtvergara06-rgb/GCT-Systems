@php
    $fleetData = $prescriptive?->fleet;
    $kpis = collect($fleetData?->kpis ?? []);
    $actions = collect($fleetData?->actions ?? []);
@endphp

<div class="prescriptive-page prescriptive-fleet-page">

    {{-- EXECUTIVE AI BANNER --}}
    <div class="predictive-ai-banner prescriptive-banner">
        <div class="predictive-ai-banner__icon-wrap">
            <i class="fa-solid fa-route"></i>
        </div>
        <div class="predictive-ai-banner__content">
            <div class="predictive-ai-banner__top">
                <span class="ai-chip">Fleet Dispatch Optimization</span>
                <span class="ai-status-pulse">
                    <span class="pulse-dot"></span>
                    4 Corridor Headway Playbooks Ready
                </span>
            </div>
            <p class="predictive-ai-banner__text">
                The prescriptive dispatch optimizer recommends <strong>staggering departure times on Route 3 (+5m offset)</strong> and <strong>staging standby bus GCT-104 at Ayala depot</strong> to recover <strong>24 minutes of cumulative peak delay</strong> and lift on-time arrival to <strong>96.4%</strong> across metropolitan corridors.
            </p>
        </div>
        <div class="predictive-ai-banner__action">
            <a href="{{ route('trip-schedule') }}" class="btn-ai-reorder">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Open Dispatch Board</span>
            </a>
        </div>
    </div>

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
                <span><i class="legend-bar red"></i> Unmitigated Delay (mins)</span>
                <span><i class="legend-bar green"></i> Prescribed Headway (mins)</span>
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
                            <span class="queue-domain-pill">
                                <i class="fa-solid fa-route"></i>
                                {{ $action['route'] }}
                            </span>
                            <span class="queue-urgency-badge {{ strtolower($action['priority']) === 'high' ? 'danger' : 'warning' }}">
                                {{ $action['priority'] }} Priority
                            </span>
                        </div>
                        <h4 class="queue-item-title">{{ $action['issue'] }}</h4>
                        <p class="queue-item-impact">
                            <strong>Prescription:</strong> {{ $action['prescribed_action'] }}
                        </p>
                        <div class="queue-item-footer">
                            <span class="queue-savings">
                                <i class="fa-solid fa-gauge-high"></i>
                                {{ $action['impact'] }}
                            </span>
                            <a href="{{ $action['action_url'] }}" class="btn-queue-action">
                                <i class="fa-solid fa-arrow-right"></i>
                                <span>Apply</span>
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
                                    {{ $action['impact'] }}
                                </span>
                            </td>
                            <td>
                                <span class="status-pill {{ strtolower($action['priority']) === 'high' ? 'critical' : 'warning' }}">
                                    {{ $action['priority'] }}
                                </span>
                            </td>
                            <td>
                                <span class="prescriptive-status-pill">
                                    <i class="fa-solid fa-clock"></i>
                                    {{ $action['status'] }}
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
        labels: @json($actions->pluck('route')->map(fn($r) => explode(' - ', $r)[0] ?? $r)),
        unmitigated: [18, 22, 16, 12],
        prescribed: [4, 12, 8, 3]
    };
</script>
