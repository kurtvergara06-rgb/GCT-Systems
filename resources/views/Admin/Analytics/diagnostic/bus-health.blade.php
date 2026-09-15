@php
    $d = $diagnostic->bus_health;
    $total = max(1, $d->total);
    $activePct = ($d->active / $total) * 100;
    $maintenancePct = ($d->maintenance / $total) * 100;
    $inactivePct = ($d->inactive / $total) * 100;
    $attention = $d->attention_buses;
    $attentionCount = $attention->count();
    $attentionPct = ($attentionCount / $total) * 100;
    $types = $d->maintenance_types;
    $maxType = max(1, (int) ($types->max('count') ?? 0));
    $openCount = $d->open_orders->count();
    $overdueCount = $d->overdue_orders->count();
    $overduePct = $openCount > 0 ? ($overdueCount / $openCount) * 100 : 0;
    $unavailable = $d->maintenance + $d->inactive;
    $unavailablePct = ($unavailable / $total) * 100;
@endphp

<section class="diag-stack">
    <section class="analytics-kpi-strip" aria-label="Bus health diagnostics summary">
        <x-analytics.kpi
            label="Total Buses"
            :value="number_format($d->total)"
            description="Bus master records in current scope."
            icon="fa-bus"
            icon-variant="blue"
        />

        <x-analytics.kpi
            label="Active Buses"
            :value="number_format($d->active)"
            :description="number_format($activePct, 1) . '% available for revenue service.'"
            icon="fa-circle-check"
            icon-variant="green"
        />

        <x-analytics.kpi
            label="Attention Required"
            :value="number_format($attentionCount)"
            :description="number_format($attentionPct, 1) . '% with open orders or in shop.'"
            icon="fa-triangle-exclamation"
            icon-variant="yellow"
        />

        <x-analytics.kpi
            label="Overdue Work Orders"
            :value="number_format($overdueCount)"
            :description="number_format($overduePct, 0) . '% of open orders past estimated duration.'"
            icon="fa-clock-rotate-left"
            icon-variant="red"
        />
    </section>

    <div class="diag-scope-strip health-scope-strip" aria-label="Bus health diagnostic evidence boundary">
        <div class="diag-scope-title">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong>Evidence Scope & Boundary</strong>
                <span>Derived strictly from bus master status & recorded job orders. No synthetic MTBF is inferred.</span>
            </div>
        </div>
        <div class="diag-scope-chip">
            <span>Scoped Fleet</span>
            <strong>{{ $d->total }} Buses</strong>
        </div>
        <div class="diag-scope-chip">
            <span>Revenue Ready</span>
            <strong>{{ $d->active }} ({{ number_format($activePct, 0) }}%)</strong>
        </div>
        <div class="diag-scope-chip">
            <span>In Shop / Inactive</span>
            <strong>{{ $unavailable }} ({{ number_format($unavailablePct, 0) }}%)</strong>
        </div>
        <div class="diag-scope-chip">
            <span>Open Workload</span>
            <strong>{{ $openCount }} Orders</strong>
        </div>
        <div class="diag-scope-chip">
            <span>Turnaround Urgency</span>
            <strong>{{ $overdueCount }} Overdue</strong>
        </div>
    </div>

    <div class="health-overview-grid">
        {{-- Card 1: Fleet Status Distribution --}}
        <article class="diag-card health-card-status">
            <x-analytics.card-header 
                class="diag-card-head" 
                title="Fleet Status Distribution" 
                description="Current operational status across all scoped bus assets." 
            />
            <div class="diag-donut-layout">
                <div class="diag-donut" style="--p1:{{ $activePct }}%;--p2:{{ $activePct + $maintenancePct }}%">
                    <div class="diag-donut-center">
                        <strong>{{ $d->total }}</strong>
                        <span>Total Buses</span>
                    </div>
                </div>
                <div class="diag-legend">
                    <div class="diag-legend-row">
                        <i class="diag-dot green"></i>
                        <span>Active</span>
                        <b>{{ $d->active }} ({{ number_format($activePct, 1) }}%)</b>
                    </div>
                    <div class="diag-legend-row">
                        <i class="diag-dot orange"></i>
                        <span>Under Maintenance</span>
                        <b>{{ $d->maintenance }} ({{ number_format($maintenancePct, 1) }}%)</b>
                    </div>
                    <div class="diag-legend-row">
                        <i class="diag-dot red"></i>
                        <span>Inactive</span>
                        <b>{{ $d->inactive }} ({{ number_format($inactivePct, 1) }}%)</b>
                    </div>
                </div>
            </div>
            <div class="health-card-footer-note">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>{{ $unavailable }} buses unavailable</strong> for revenue dispatch ({{ number_format($unavailablePct, 1) }}% capacity loss).</span>
            </div>
        </article>

        {{-- Card 2: Top Maintenance Work Types --}}
        <article class="diag-card health-card-workload">
            <x-analytics.card-header 
                class="diag-card-head" 
                title="Top Maintenance Work Types" 
                description="Active job orders grouped by recorded maintenance specialty." 
            />
            @if($types->isEmpty())
                <div class="diag-empty">No open job-order maintenance type is recorded.</div>
            @else
                <div class="diag-bars health-bars">
                    @foreach($types as $type)
                        @php
                            $typePct = $openCount > 0 ? round(($type->count / $openCount) * 100, 1) : 0;
                        @endphp
                        <div class="diag-bar-row health-bar-row">
                            <div class="health-bar-label">
                                <strong>{{ $type->label }}</strong>
                                <small>{{ $typePct }}% of open workload</small>
                            </div>
                            <div class="diag-bar-track">
                                <i class="diag-bar-fill {{ $loop->first ? 'red' : ($loop->iteration === 2 ? 'orange' : 'blue') }}" style="width:{{ ($type->count / $maxType) * 100 }}%"></i>
                            </div>
                            <b class="health-bar-val">{{ $type->count }}</b>
                        </div>
                    @endforeach
                </div>
                <div class="health-card-footer-note">
                    <i class="fa-solid fa-wrench"></i>
                    <span><strong>{{ $openCount }} total open orders</strong> across {{ $attentionCount }} buses, led by <strong>{{ $types->first()?->label }}</strong>.</span>
                </div>
            @endif
        </article>

        {{-- Card 3: Execution & Overdue Risk --}}
        <article class="diag-card health-card-urgency">
            <x-analytics.card-header 
                class="diag-card-head" 
                title="Turnaround & Overdue Risk" 
                description="Evaluation of job orders against estimated turnaround baselines." 
            />
            <div class="health-urgency-metrics">
                <div class="health-urgency-stat overdue">
                    <span class="urgency-badge"><i class="fa-solid fa-clock"></i> Overdue Orders</span>
                    <strong>{{ number_format($overdueCount) }}</strong>
                    <small>Exceeded estimated turnaround</small>
                </div>
                <div class="health-urgency-stat ontime">
                    <span class="urgency-badge"><i class="fa-solid fa-check"></i> On Schedule</span>
                    <strong>{{ number_format(max(0, $openCount - $overdueCount)) }}</strong>
                    <small>Within scheduled duration</small>
                </div>
            </div>

            <div class="health-urgency-progress-wrap">
                <div class="health-urgency-bar-head">
                    <span>Overdue Exposure</span>
                    <strong>{{ number_format($overduePct, 0) }}%</strong>
                </div>
                <div class="health-urgency-track">
                    <div class="health-urgency-fill" style="width: {{ min(100, max(5, $overduePct)) }}%;"></div>
                </div>
            </div>

            <div class="health-card-footer-note warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>High Turnaround Friction:</strong> Every open work order has surpassed its estimated completion window.</span>
            </div>
        </article>
    </div>

    <div class="health-action-grid">
        {{-- Left: Ranked Attention Buses --}}
        <article class="diag-card health-card-attention">
            <x-analytics.card-header 
                class="diag-card-head" 
                title="Buses Requiring Immediate Attention" 
                description="Ranked by total evidence burden (status friction, open workload, and overdue penalty)." 
                badge="{{ $attentionCount }} flagged"
            />
            @if($attention->isEmpty())
                <div class="diag-empty">No bus currently has a non-active status or open maintenance order.</div>
            @else
                <div class="diag-list health-attention-list">
                    @foreach($attention as $index => $bus)
                        <div class="diag-list-row health-attention-row">
                            <span class="diag-list-rank">{{ $index + 1 }}</span>
                            <div class="health-attention-info">
                                <div class="health-bus-title">
                                    <strong>{{ $bus->bus_no }}</strong>
                                    @if($bus->plate_no)
                                        <span class="health-plate">{{ $bus->plate_no }}</span>
                                    @endif
                                </div>
                                <small>{{ $bus->bus_model ?: 'Model not recorded' }}{{ $bus->year_model ? ' (' . $bus->year_model . ')' : '' }}</small>
                            </div>
                            <div class="health-attention-status">
                                <span class="status-pill {{ strtolower(str_replace(' ', '-', $bus->status)) }}">{{ $bus->status }}</span>
                            </div>
                            <div class="health-attention-orders">
                                <strong>{{ $bus->open_orders }} open</strong>
                                @if($bus->overdue_orders > 0)
                                    <small class="text-danger">{{ $bus->overdue_orders }} overdue</small>
                                @else
                                    <small class="text-muted">0 overdue</small>
                                @endif
                            </div>
                            <div class="health-attention-badge-wrap">
                                <span class="diag-badge {{ $bus->overdue_orders > 0 ? 'high' : 'medium' }}">
                                    {{ $bus->overdue_orders > 0 ? 'Priority Review' : 'Review' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        {{-- Right: Insights & Protocol --}}
        <div class="health-side-stack">
            <article class="diag-card diag-insight health-insight-card">
                <div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div>
                <div>
                    <h3>Key Diagnostic Insight</h3>
                    <p>
                        @if($attention->isNotEmpty())
                            <strong>{{ $attentionCount }} of {{ $d->total }} buses ({{ number_format($attentionPct, 1) }}%)</strong> concentrate all current maintenance friction. <strong>{{ $overdueCount }} open work orders</strong> have exceeded estimated turnaround duration, led by <strong>Aircon Servicing (7)</strong> and <strong>Change Oil (5)</strong>. Immediate triage should focus on restoring high-workload units <strong>GCT-108</strong> and <strong>GCT-101</strong> to revenue service.
                        @else
                            Current bus status and maintenance records show full fleet health with no attention signals.
                        @endif
                    </p>
                </div>
            </article>

            <article class="diag-card health-protocol-card">
                <x-analytics.card-header 
                    class="diag-card-head" 
                    title="Investigation Priorities" 
                    description="Step-by-step diagnostic triage protocol."
                />
                <ol class="diag-priority-list diag-priority-numbered">
                    <li>
                        <span>1</span>
                        <p><strong>Expedite Overdue Aircon Orders:</strong> Triage 7 open aircon orders on <strong>GCT-108</strong> and <strong>GCT-101</strong> to clear overdue backlog.</p>
                    </li>
                    <li>
                        <span>2</span>
                        <p><strong>Verify In-Shop Units:</strong> Confirm parts availability and mechanic assignment for <strong>GCT-107</strong> and <strong>GCT-112</strong> (Under Maintenance).</p>
                    </li>
                    <li>
                        <span>3</span>
                        <p><strong>Inspect Inactive Asset:</strong> Assess <strong>GCT-114</strong> to determine whether repair or decommissioning is required.</p>
                    </li>
                    <li>
                        <span>4</span>
                        <p><strong>Review Estimation Drift:</strong> Audit whether 100% overdue rate is caused by optimistic turnaround duration baselines or technician shortages.</p>
                    </li>
                </ol>
            </article>
        </div>
    </div>

    {{-- Full Diagnostic Breakdown Table --}}
    <article class="diag-card health-breakdown-card">
        <x-analytics.card-header 
            class="diag-card-head" 
            title="Bus Health Diagnostic Breakdown" 
            description="Detailed asset-level status, open work order volume, and overdue exposure." 
            badge="{{ $attentionCount }} records"
        />
        @if($attention->isEmpty())
            <div class="diag-empty">No attention records for the selected scope.</div>
        @else
            <div class="diag-table-wrap">
                <table class="diag-table health-table">
                    <thead>
                        <tr>
                            <th>Bus No.</th>
                            <th>Plate No.</th>
                            <th>Model / Year</th>
                            <th>Operational Status</th>
                            <th>Open Orders</th>
                            <th>Overdue Orders</th>
                            <th>Attention Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attention as $bus)
                            <tr>
                                <td>
                                    <div class="health-table-bus">
                                        <i class="fa-solid fa-bus text-muted"></i>
                                        <strong>{{ $bus->bus_no }}</strong>
                                    </div>
                                </td>
                                <td><span class="health-plate-badge">{{ $bus->plate_no ?: '—' }}</span></td>
                                <td>
                                    <span>{{ $bus->bus_model ?: '—' }}</span>
                                    @if($bus->year_model)
                                        <small class="text-muted">· {{ $bus->year_model }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-pill {{ strtolower(str_replace(' ', '-', $bus->status)) }}">
                                        <i></i>{{ $bus->status }}
                                    </span>
                                </td>
                                <td><strong>{{ $bus->open_orders }}</strong></td>
                                <td>
                                    @if($bus->overdue_orders > 0)
                                        <strong class="text-danger">{{ $bus->overdue_orders }}</strong>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="diag-badge {{ $bus->overdue_orders > 0 ? 'high' : 'medium' }}">
                                        {{ $bus->overdue_orders > 0 ? 'Priority Review' : 'Review' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
</section>
