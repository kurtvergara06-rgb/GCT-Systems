@php
    $fuelData = $prescriptive?->fuel;
    $kpis = collect($fuelData?->kpis ?? []);
    $actions = collect($fuelData?->actions ?? []);
@endphp

<div class="prescriptive-page prescriptive-fuel-page">

    {{-- EXECUTIVE AI BANNER --}}
    <div class="predictive-ai-banner prescriptive-banner">
        <div class="predictive-ai-banner__icon-wrap">
            <i class="fa-solid fa-gas-pump"></i>
        </div>
        <div class="predictive-ai-banner__content">
            <div class="predictive-ai-banner__top">
                <span class="ai-chip">Fuel Optimization Engine</span>
                <span class="ai-status-pulse">
                    <span class="pulse-dot"></span>
                    4 Consumption Reduction Playbooks Ready
                </span>
            </div>
            <p class="predictive-ai-banner__text">
                The prescriptive fuel conservation model predicts <strong>168 Liters / month of fuel recovery (₱11,424 savings)</strong> by enforcing a <strong>10-minute automated idle cutoff policy on Bus 07</strong>, scheduling <strong>ultrasonic injector cleaning on Bus 12</strong>, and enrolling operators of Bus 05 in <strong>eco-driving telemetry coaching</strong>.
            </p>
        </div>
        <div class="predictive-ai-banner__action">
            <a href="{{ route('fuel-reports') }}" class="btn-ai-reorder">
                <i class="fa-solid fa-droplet"></i>
                <span>Open Fuel Monitoring</span>
            </a>
        </div>
    </div>

    {{-- KPI STRIP --}}
    <section class="analytics-kpi-strip" aria-label="Fuel Prescriptive KPIs">
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

        {{-- Fuel Savings Curve Chart --}}
        <x-analytics.card
            class="prescriptive-card prescriptive-chart-card"
            title="Projected Fuel Consumption Reduction"
            description="Comparison of baseline consumption trend vs prescriptive conservation curve."
        >
            <x-slot:headerActions>
                <span class="ft-telemetry-badge">
                    <i class="fa-solid fa-leaf"></i>
                    <span>-168 L/mo Prescribed Target</span>
                </span>
            </x-slot:headerActions>

            <div class="prescriptive-chart-legend" aria-hidden="true">
                <span><i class="legend-bar red"></i> Current Consumption (L)</span>
                <span><i class="legend-bar green"></i> Prescribed Target (L)</span>
            </div>

            <div class="prescriptive-chart-container">
                <canvas id="fuelPrescriptiveChart" role="img" aria-label="Fuel conservation savings curve"></canvas>
            </div>
        </x-analytics.card>

        {{-- Fuel Action Queue --}}
        <x-analytics.card
            class="prescriptive-card"
            title="Prescribed Fuel Interventions"
            description="Targeted unit adjustments to eliminate excessive consumption."
        >
            <div class="prescriptive-queue-list">
                @foreach($actions as $action)
                    <div class="prescriptive-queue-item">
                        <div class="queue-item-header">
                            <span class="queue-domain-pill">
                                <i class="fa-solid fa-bus"></i>
                                {{ $action['bus_no'] }}
                            </span>
                            <span class="queue-urgency-badge {{ strtolower($action['priority']) === 'high' ? 'danger' : 'warning' }}">
                                {{ $action['priority'] }} Priority
                            </span>
                        </div>
                        <h4 class="queue-item-title">{{ $action['issue'] }}</h4>
                        <p class="queue-item-impact">
                            <strong>Prescription:</strong> {{ $action['prescription'] }}
                        </p>
                        <div class="queue-item-footer">
                            <span class="queue-savings">
                                <i class="fa-solid fa-coins"></i>
                                {{ $action['savings'] }}
                            </span>
                            <span class="status-pill {{ strtolower($action['priority']) === 'high' ? 'critical' : 'warning' }}">
                                {{ $action['status'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-analytics.card>

    </section>

    {{-- FUEL PLAYBOOK TABLE --}}
    <x-analytics.card
        class="prescriptive-table-card"
        title="Fleet Fuel Efficiency Interventions & Calibration Queue"
        description="Prescribed mechanical servicing, sensor maintenance, and driver eco-coaching schedules."
    >
        <div class="analytics-table-wrapper">
            <table class="analytics-data-table prescriptive-table">
                <thead>
                    <tr>
                        <th>Bus Unit</th>
                        <th>Identified Efficiency Drain</th>
                        <th>Prescribed Engineering & Behavioral Action</th>
                        <th>Projected Fuel & Cost Recovery</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-right">Execution</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($actions as $action)
                        <tr>
                            <td>
                                <strong class="table-bus-chip">{{ $action['bus_no'] }}</strong>
                            </td>
                            <td>
                                <span class="table-issue-tag">{{ $action['issue'] }}</span>
                            </td>
                            <td>
                                <span class="table-prescription-text">{{ $action['prescription'] }}</span>
                            </td>
                            <td>
                                <span class="gain-badge green">
                                    <i class="fa-solid fa-coins"></i>
                                    {{ $action['savings'] }}
                                </span>
                            </td>
                            <td>
                                <span class="status-pill {{ strtolower($action['priority']) === 'high' ? 'critical' : (strtolower($action['priority']) === 'medium' ? 'warning' : 'info') }}">
                                    {{ $action['priority'] }}
                                </span>
                            </td>
                            <td>
                                <span class="prescriptive-status-pill">
                                    <i class="fa-solid fa-check-double"></i>
                                    {{ $action['status'] }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('fuel-reports') }}" class="btn-table-action">
                                    <span>Log Policy</span>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-analytics.card>

    {{-- FUEL GOVERNANCE & TELEMETRY --}}
    <section class="prescriptive-governance-section">
        <h3 class="gov-heading">
            <i class="fa-solid fa-shield-halved"></i>
            Prescriptive Fuel Policy & Eco-Driving Standards
        </h3>
        <div class="gov-grid">
            <div class="gov-card">
                <div class="gov-card__icon orange"><i class="fa-solid fa-hourglass-half"></i></div>
                <div>
                    <h4>Mandatory 10-Minute Terminal Idle Cutoff</h4>
                    <p>Drivers must shut off engines during layover periods exceeding 10 minutes at SM City and North Terminals to prevent parasitic idling fuel burn.</p>
                </div>
            </div>
            <div class="gov-card">
                <div class="gov-card__icon blue"><i class="fa-solid fa-wrench"></i></div>
                <div>
                    <h4>Ultrasonic Injector Recalibration Interval</h4>
                    <p>Units displaying &gt;15% variance from the 3.59 km/L fleet benchmark trigger an automated maintenance order for fuel rail and nozzle decoking.</p>
                </div>
            </div>
            <div class="gov-card">
                <div class="gov-card__icon green"><i class="fa-solid fa-graduation-cap"></i></div>
                <div>
                    <h4>Telemetric Eco-Score Incentive Program</h4>
                    <p>Operators maintaining a smooth throttle profile and an Eco-Score above 88 receive monthly performance commendations and safety bonuses.</p>
                </div>
            </div>
        </div>
    </section>

</div>

<script>
    window.fuelPrescriptiveData = {
        labels: ['Bus 07', 'Bus 12', 'Bus 05', 'Bus 03'],
        current: [142, 178, 125, 110],
        prescribed: [128, 155, 117, 103]
    };
</script>
