@php
    $healthData = $prescriptive?->bus_health;
    $kpis = collect($healthData?->kpis ?? []);
    $actions = collect($healthData?->actions ?? []);
@endphp

<div class="prescriptive-page prescriptive-health-page">

    {{-- EXECUTIVE AI BANNER --}}
    <div class="predictive-ai-banner prescriptive-banner">
        <div class="predictive-ai-banner__icon-wrap">
            <i class="fa-solid fa-screwdriver-wrench"></i>
        </div>
        <div class="predictive-ai-banner__content">
            <div class="predictive-ai-banner__top">
                <span class="ai-chip">Maintenance Prescriptive Engine</span>
                <span class="ai-status-pulse">
                    <span class="pulse-dot"></span>
                    4 Expedited Workshop Bay Playbooks Active
                </span>
            </div>
            <p class="predictive-ai-banner__text">
                The prescriptive maintenance engine recommends <strong>reassigning 2 mechanics to Bay 2 (Heavy Lift)</strong> to service <strong>GCT-108 (Brake Pads)</strong> and <strong>GCT-101 (Cooling System)</strong> immediately. Executing these interventions reduces job order turnaround by <strong>36 hours</strong> and avoids estimated <strong>₱14,000+ in emergency roadside breakdown towing costs</strong>.
            </p>
        </div>
        <div class="predictive-ai-banner__action">
            <a href="{{ route('job-orders') }}" class="btn-ai-reorder">
                <i class="fa-solid fa-wrench"></i>
                <span>Open Maintenance Bays</span>
            </a>
        </div>
    </div>

    {{-- KPI STRIP --}}
    <section class="analytics-kpi-strip" aria-label="Bus Health Prescriptive KPIs">
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

        {{-- Turnaround Compression Chart --}}
        <x-analytics.card
            class="prescriptive-card prescriptive-chart-card"
            title="Job Order Turnaround Time Compression"
            description="Comparison of standard repair hours vs accelerated prescriptive mechanic allocation."
        >
            <x-slot:headerActions>
                <span class="ft-telemetry-badge">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>-36 Hours Saved Across Fleet</span>
                </span>
            </x-slot:headerActions>

            <div class="prescriptive-chart-legend" aria-hidden="true">
                <span><i class="legend-bar red"></i> Standard Turnaround (Hours)</span>
                <span><i class="legend-bar green"></i> Prescribed Accelerated (Hours)</span>
            </div>

            <div class="prescriptive-chart-container">
                <canvas id="healthPrescriptiveChart" role="img" aria-label="Turnaround time acceleration chart"></canvas>
            </div>
        </x-analytics.card>

        {{-- Workshop Bay Allocation Queue --}}
        <x-analytics.card
            class="prescriptive-card"
            title="Prescribed Bay Reassignments"
            description="Optimal mechanic and bay staging to clear overdue maintenance backlogs."
        >
            <div class="prescriptive-queue-list">
                @foreach($actions as $action)
                    <div class="prescriptive-queue-item">
                        <div class="queue-item-header">
                            <span class="queue-domain-pill">
                                <i class="fa-solid fa-bus"></i>
                                {{ $action['bus_no'] }}
                            </span>
                            <span class="queue-urgency-badge {{ str_contains(strtolower($action['priority']), 'critical') ? 'danger' : 'warning' }}">
                                {{ $action['priority'] }}
                            </span>
                        </div>
                        <h4 class="queue-item-title">{{ $action['component'] }}</h4>
                        <p class="queue-item-impact">
                            <strong>Prescription:</strong> {{ $action['prescription'] }}
                        </p>
                        <div class="queue-item-footer">
                            <span class="queue-savings">
                                <i class="fa-solid fa-warehouse"></i>
                                {{ $action['bay'] }}
                            </span>
                            <span class="status-pill {{ str_contains(strtolower($action['priority']), 'critical') ? 'critical' : 'warning' }}">
                                {{ $action['status'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-analytics.card>

    </section>

    {{-- EXPEDITED WORK ORDER TABLE --}}
    <x-analytics.card
        class="prescriptive-table-card"
        title="Expedited Job Order & Bay Allocation Schedule"
        description="Prescribed mechanic dispatches to eliminate critical bus component failures."
    >
        <div class="analytics-table-wrapper">
            <table class="analytics-data-table prescriptive-table">
                <thead>
                    <tr>
                        <th>Bus Unit</th>
                        <th>Target System / Component</th>
                        <th>Prescribed Workshop Intervention</th>
                        <th>Designated Bay</th>
                        <th>Urgency Window</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actions as $action)
                        <tr>
                            <td>
                                <strong class="table-bus-chip">{{ $action['bus_no'] }}</strong>
                            </td>
                            <td>
                                <span class="table-issue-tag">{{ $action['component'] }}</span>
                            </td>
                            <td>
                                <span class="table-prescription-text">{{ $action['prescription'] }}</span>
                            </td>
                            <td>
                                <span class="gain-badge blue">
                                    <i class="fa-solid fa-warehouse"></i>
                                    {{ $action['bay'] }}
                                </span>
                            </td>
                            <td>
                                <span class="status-pill {{ str_contains(strtolower($action['priority']), 'critical') ? 'critical' : 'warning' }}">
                                    {{ $action['priority'] }}
                                </span>
                            </td>
                            <td>
                                <span class="prescriptive-status-pill">
                                    <i class="fa-solid fa-wrench"></i>
                                    {{ $action['status'] }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('job-orders') }}" class="btn-table-action">
                                    <span>Assign Bay</span>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-analytics.card>

    {{-- MAINTENANCE GOVERNANCE & SAFETY --}}
    <section class="prescriptive-governance-section">
        <h3 class="gov-heading">
            <i class="fa-solid fa-shield-halved"></i>
            Workshop Safety Protocols & Certification Standards
        </h3>
        <div class="gov-grid">
            <div class="gov-card">
                <div class="gov-card__icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <h4>Zero-Dispatch Policy on Brake Criticals</h4>
                    <p>Units with brake pad thickness &lt;3mm or leaking brake booster lines are hard-locked in the dispatch system until Bay 2 signoff.</p>
                </div>
            </div>
            <div class="gov-card">
                <div class="gov-card__icon purple"><i class="fa-solid fa-clipboard-check"></i></div>
                <div>
                    <h4>Multi-Point Quality Signoff</h4>
                    <p>Post-repair releases require lead technician torque check, fluid level verification, and a 15-minute dyno/road validation test.</p>
                </div>
            </div>
            <div class="gov-card">
                <div class="gov-card__icon green"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div>
                    <h4>3,000 km Recalibration Windows</h4>
                    <p>All safety-critical fasteners and suspension bushings are systematically re-torqued during secondary PMS checkups.</p>
                </div>
            </div>
        </div>
    </section>

</div>

<script>
    window.busHealthPrescriptiveData = {
        labels: ['GCT-108 (Brakes)', 'GCT-101 (Radiator)', 'GCT-110 (Clutch)', 'GCT-104 (Alternator)'],
        standard: [48, 36, 24, 18],
        accelerated: [12, 10, 8, 6]
    };
</script>
