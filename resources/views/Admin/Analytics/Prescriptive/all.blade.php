@php
    $prescriptiveData = $prescriptive?->all;
    $kpis = collect($prescriptiveData?->kpis ?? []);
    $queue = collect($prescriptiveData?->queue ?? []);
    $tableRows = collect($prescriptiveData?->table_rows ?? []);
    $execStats = $prescriptiveData?->execution_stats ?? (object) ['completed' => 8, 'in_progress' => 5, 'pending' => 7, 'total' => 20];
    $savingsChart = $prescriptiveData?->savings_chart ?? (object) [
        'labels' => ['Fleet Optimization', 'Fuel Conservation', 'Preventive PMS', 'Bulk Procurement'],
        'current' => [12000, 15000, 18000, 22000],
        'prescriptive' => [24000, 32000, 41000, 48500],
    ];
@endphp

<div class="prescriptive-page prescriptive-all-page">

    {{-- EXECUTIVE AI PRESCRIPTIVE COMMAND BANNER --}}
    <div class="predictive-ai-banner prescriptive-banner">
        <div class="predictive-ai-banner__icon-wrap">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div class="predictive-ai-banner__content">
            <div class="predictive-ai-banner__top">
                <span class="ai-chip">Prescriptive Action Engine</span>
                <span class="ai-status-pulse">
                    <span class="pulse-dot"></span>
                    16 Prioritized Operational Playbooks Active
                </span>
            </div>
            <p class="predictive-ai-banner__text">
                The prescriptive engine recommends <strong>7 immediate high-impact interventions</strong>: generate emergency purchase requisitions for <strong>10 depleted brake and filter parts</strong>, reassign 2 mechanics to <strong>Bay 2 to compress job order backlog by 36h</strong>, and enforce <strong>10-min idle cutoffs to save ₱11,400/month</strong>. Executing these playbooks secures an estimated <strong>+5.8% on-time dispatch recovery</strong>.
            </p>
        </div>
        <div class="predictive-ai-banner__action">
            <a href="#actionQueueSection" class="btn-ai-reorder">
                <i class="fa-solid fa-list-check"></i>
                <span>Review Priority Queue</span>
            </a>
        </div>
    </div>

    {{-- KPI STRIP --}}
    <section class="analytics-kpi-strip" aria-label="Prescriptive summary KPIs">
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

    {{-- TOP 3-COLUMN GRID --}}
    <section class="prescriptive-main-grid">

        {{-- 1. Prescriptive Impact Chart --}}
        <x-analytics.card
            class="prescriptive-card prescriptive-chart-card"
            title="Prescriptive ROI & Savings Projection"
            description="Baseline operating cost vs projected savings under prescriptive playbooks."
        >
            <x-slot:headerActions>
                <span class="ft-telemetry-badge">
                    <i class="fa-solid fa-coins"></i>
                    <span>Est. ₱48,500/mo Savings</span>
                </span>
            </x-slot:headerActions>

            <div class="prescriptive-chart-legend" aria-hidden="true">
                <span><i class="legend-bar blue"></i> Business as Usual</span>
                <span><i class="legend-bar green"></i> Prescriptive Optimization</span>
            </div>

            <div class="prescriptive-chart-container">
                <canvas id="prescriptiveImpactChart" role="img" aria-label="Prescriptive savings projection chart"></canvas>
            </div>
        </x-analytics.card>

        {{-- 2. Execution Pipeline Donut --}}
        <x-analytics.card
            class="prescriptive-card prescriptive-donut-card"
            title="Prescriptive Pipeline Status"
            description="Implementation state of AI-prescribed recommendations."
        >
            <div class="prescriptive-donut-body">
                <div class="prescriptive-donut-ring">
                    <canvas id="actionDonut" role="img" aria-label="Prescriptive action distribution donut"></canvas>
                    <div class="prescriptive-donut-center">
                        <strong>{{ $execStats->total }}</strong>
                        <span>Total Plans</span>
                    </div>
                </div>

                <ul class="prescriptive-donut-list">
                    <li>
                        <span class="donut-dot success"></span>
                        <span>Adopted & Deployed</span>
                        <strong>{{ $execStats->completed }} <small>(40%)</small></strong>
                    </li>
                    <li>
                        <span class="donut-dot warning"></span>
                        <span>In Execution / Bay</span>
                        <strong>{{ $execStats->in_progress }} <small>(25%)</small></strong>
                    </li>
                    <li>
                        <span class="donut-dot danger"></span>
                        <span>Pending Operator</span>
                        <strong>{{ $execStats->pending }} <small>(35%)</small></strong>
                    </li>
                </ul>
            </div>

            <div class="health-ratio-bar-wrap" style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">
                        <i class="fa-solid fa-circle-check" style="color: #10b981; margin-right: 4px;"></i> Action Compliance Score
                    </span>
                    <strong style="font-size: 11px; font-weight: 800; color: #0f172a;">82.4%</strong>
                </div>
                <div class="health-ratio-bar" title="82.4% Prescriptive Adoption">
                    <div class="ratio-segment ratio-healthy" style="width: 82.4%"></div>
                    <div class="ratio-segment ratio-low" style="width: 17.6%"></div>
                </div>
            </div>
        </x-analytics.card>

        {{-- 3. Priority Action Queue --}}
        <x-analytics.card
            id="actionQueueSection"
            class="prescriptive-card prescriptive-queue-card"
            title="Top Prescriptive Interventions"
            description="Ranked by highest impact on fleet reliability and cost containment."
        >
            <div class="prescriptive-queue-list">
                @foreach($queue as $item)
                    <article class="prescriptive-queue-item">
                        <div class="queue-rank-badge">{{ $item['rank'] }}</div>
                        <div class="queue-item-icon {{ $item['badge'] }}">
                            <i class="fa-solid {{ $item['icon'] }}"></i>
                        </div>
                        <div class="queue-item-body">
                            <div class="queue-item-header">
                                <h6>{{ $item['title'] }}</h6>
                                <span class="queue-urgency {{ $item['badge'] }}">{{ $item['urgency'] }}</span>
                            </div>
                            <p>{{ $item['impact'] }}</p>
                            <div class="queue-meta-row">
                                <span class="queue-domain-tag">{{ $item['domain'] }}</span>
                                <span class="queue-savings-tag"><i class="fa-solid fa-coins"></i> {{ $item['savings'] }}</span>
                            </div>
                        </div>
                        <div class="queue-item-action">
                            <a href="{{ $item['action_url'] }}" class="btn-queue-action">
                                <span>{{ $item['action_label'] }}</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-analytics.card>

    </section>

    {{-- LOWER SECTION: DOMAIN SUMMARY TABLE & ACTION PROTOCOLS --}}
    <section class="prescriptive-lower-grid">

        {{-- Domain Prescriptive Summary Table --}}
        <x-analytics.card
            class="prescriptive-card prescriptive-table-card"
            title="Cross-Domain Action Roadmap"
            description="Consolidated prescriptive strategies ready for operator authorization."
        >
            <div class="responsive-table">
                <table class="analytics-table prescriptive-table">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Recommended Interventions</th>
                            <th>Targeted Operational Gain</th>
                            <th>Urgency Level</th>
                            <th>Workflow Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tableRows as $row)
                            <tr>
                                <td>
                                    <span class="table-domain">
                                        <i class="fa-solid {{ $row->icon }}"></i>
                                        {{ $row->domain }}
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: #1e293b; font-size: 11.5px;">{{ $row->prescriptions }}</strong>
                                </td>
                                <td>
                                    <span class="gain-badge"><i class="fa-solid fa-arrow-trend-up"></i> {{ $row->expected_gain }}</span>
                                </td>
                                <td>
                                    <span class="risk-badge {{ $row->level === 'high' ? 'danger' : 'warning' }}">
                                        {{ ucfirst($row->level) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="status-chip {{ strtolower(str_replace(' ', '-', $row->status)) }}">
                                        {{ $row->status }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="{{ route('analytics.stage', ['stage' => 'prescriptive', 'domain' => $row->slug], false) }}" class="domain-explore-chip" title="View detailed {{ $row->domain }} prescriptive playbook">
                                        <span>Open Playbook</span>
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-analytics.card>

        {{-- Prescriptive Decision Governance --}}
        <x-analytics.card
            class="prescriptive-card prescriptive-rules-card"
            title="AI Prescriptive Governance & Human-in-the-Loop"
            description="Operating guardrails ensuring all AI prescriptions require operator confirmation."
        >
            <div class="prescriptive-governance-list">
                <div class="gov-item">
                    <div class="gov-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <div class="gov-content">
                        <h6>Operator Discretion Protocol</h6>
                        <p>No automated dispatches, mechanic work orders, or purchase requisitions are finalized without active supervisor approval.</p>
                    </div>
                </div>
                <div class="gov-item">
                    <div class="gov-icon"><i class="fa-solid fa-scale-balanced"></i></div>
                    <div class="gov-content">
                        <h6>Cost vs Risk Balancing</h6>
                        <p>Purchase orders prioritize zero-stock safety critical components (brakes, coolant) over non-essential inventory buffers.</p>
                    </div>
                </div>
                <div class="gov-item">
                    <div class="gov-icon"><i class="fa-solid fa-route"></i></div>
                    <div class="gov-content">
                        <h6>Headway Buffer Margin</h6>
                        <p>Route adjustments maintain strict ±5 minute transit regulatory guidelines to preserve passenger connection consistency.</p>
                    </div>
                </div>
            </div>
        </x-analytics.card>

    </section>

    {{-- FOOTER DISCLAIMER --}}
    <div class="predictive-footer">
        <i class="fa-solid fa-circle-info"></i>
        <span>Prescriptive recommendations are generated from predictive risk horizons and historical baselines. Supervisors retain full dispatch authority.</span>
    </div>

</div>

{{-- DATA FOR CHARTS --}}
<script>
    window.prescriptiveChartData = {
        savings: @json($savingsChart),
        execution: {
            completed: {{ (int) ($execStats->completed ?? 8) }},
            in_progress: {{ (int) ($execStats->in_progress ?? 5) }},
            pending: {{ (int) ($execStats->pending ?? 7) }},
            total: {{ (int) ($execStats->total ?? 20) }}
        }
    };
</script>
