@php
    $d = $diagnostic->bus_health;
    $total = max(1,$d->total);
    $activePct = ($d->active/$total)*100;
    $maintenancePct = ($d->maintenance/$total)*100;
    $inactivePct = ($d->inactive/$total)*100;
    $attention = $d->attention_buses;
    $types = $d->maintenance_types;
    $maxType = max(1,(int)($types->max('count') ?? 0));
@endphp

<section class="diag-stack">
    <section class="analytics-kpi-strip" aria-label="Bus health diagnostics summary">
        <x-analytics.kpi
            label="Total Buses"
            :value="number_format($d->total)"
            description="Bus master records in the selected scope."
            icon="fa-bus"
            icon-variant="blue"
        />

        <x-analytics.kpi
            label="Active"
            :value="number_format($d->active)"
            :description="number_format($activePct, 1) . '% of scoped buses.'"
            icon="fa-circle-check"
            icon-variant="green"
        />

        <x-analytics.kpi
            label="Under Maintenance"
            :value="number_format($d->maintenance)"
            :description="number_format($maintenancePct, 1) . '% of scoped buses.'"
            icon="fa-screwdriver-wrench"
            icon-variant="yellow"
        />

        <x-analytics.kpi
            label="Overdue Work Orders"
            :value="number_format($d->overdue_orders->count())"
            description="Open work beyond its recorded estimated duration."
            icon="fa-clock-rotate-left"
            icon-variant="red"
        />
    </section>

    <div class="health-overview-grid">
        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Fleet Status Distribution" description="Current bus-master status, used instead of an invented mechanical health score." />
            <div class="diag-donut-layout">
                <div class="diag-donut" style="--p1:{{ $activePct }}%;--p2:{{ $activePct+$maintenancePct }}%"><div class="diag-donut-center"><strong>{{ $d->total }}</strong><span>Buses</span></div></div>
                <div class="diag-legend"><div class="diag-legend-row"><i class="diag-dot green"></i><span>Active</span><b>{{ $d->active }} ({{ number_format($activePct,1) }}%)</b></div><div class="diag-legend-row"><i class="diag-dot orange"></i><span>Under Maintenance</span><b>{{ $d->maintenance }} ({{ number_format($maintenancePct,1) }}%)</b></div><div class="diag-legend-row"><i class="diag-dot red"></i><span>Inactive</span><b>{{ $d->inactive }} ({{ number_format($inactivePct,1) }}%)</b></div></div>
            </div>
        </article>

        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Top Maintenance Work Types" description="Open job orders grouped by recorded maintenance type." />
            @if($types->isEmpty())<div class="diag-empty">No open job-order maintenance type is recorded.</div>@else<div class="diag-bars">@foreach($types as $type)<div class="diag-bar-row"><span>{{ $type->label }}</span><div class="diag-bar-track"><i class="diag-bar-fill {{ $loop->first ? 'red' : ($loop->iteration === 2 ? 'orange' : '') }}" style="width:{{ ($type->count/$maxType)*100 }}%"></i></div><b>{{ $type->count }}</b></div>@endforeach</div>@endif
        </article>

        <article class="diag-card">
            <x-analytics.card-header class="diag-card-head" title="Buses Requiring Attention" description="Status and recorded maintenance workload, ranked by evidence burden." />
            @if($attention->isEmpty())<div class="diag-empty">No bus currently has a non-active status or open maintenance order.</div>@else<div class="diag-list">@foreach($attention->take(6) as $bus)<div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-bus"></i></span><div><strong>{{ $bus->bus_no }}</strong><small>{{ $bus->status }} · {{ $bus->open_orders }} open · {{ $bus->overdue_orders }} overdue</small></div><span class="diag-badge {{ $bus->overdue_orders > 0 ? 'high' : 'medium' }}">{{ $bus->attention_score }}</span></div>@endforeach</div>@endif
        </article>
    </div>

    <div class="health-secondary-grid">
        <article class="diag-card"><x-analytics.card-header class="diag-card-head" title="Current Status by Bus" /><div class="health-status-list">@forelse($d->buses->take(5) as $bus)<div class="health-status-row"><div><strong>{{ $bus->bus_no }}</strong><div style="color:#7a8aa1;font-size:9.5px">{{ $bus->bus_model ?: 'Model not recorded' }}</div></div><span class="diag-badge {{ $bus->status === 'Active' ? 'low' : ($bus->status === 'Inactive' ? 'high' : 'medium') }}">{{ $bus->status }}</span></div>@empty<div class="diag-empty">No bus records.</div>@endforelse</div></article>
        <article class="diag-card">
            <x-analytics.kpi label="Open Maintenance" :value="number_format($d->open_orders->count())" description="Open job orders" icon="fa-screwdriver-wrench" tone="yellow" />
            <div style="margin-top:12px" class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-clock"></i></span><div><strong>Overdue</strong><small>Exceeded recorded estimate</small></div><span class="diag-list-value">{{ $d->overdue_orders->count() }}</span></div>
        </article>
        <article class="diag-card">
            <x-analytics.kpi label="Attention Coverage" :value="number_format($attention->count())" :description="'of ' . $d->total . ' scoped buses'" icon="fa-bus" tone="orange" />
            <div class="diag-bar-track" style="margin-top:16px;height:10px"><i class="diag-bar-fill orange" style="width:{{ min(100,($attention->count()/$total)*100) }}%"></i></div>
        </article>
        <article class="diag-card">
            <x-analytics.kpi label="Inactive Exposure" :value="number_format($d->inactive)" :description="number_format($inactivePct,1) . '% of scoped buses'" icon="fa-circle-exclamation" tone="red" />
        </article>
        <article class="diag-card"><x-analytics.card-header class="diag-card-head" title="Data Boundary" /><p style="margin:0;color:#64758d;font-size:10px;line-height:1.55">No mechanical health score, MTBF, component-failure probability, or health trend is inferred unless those measurements are directly supported by recorded maintenance data.</p></article>
    </div>

    <article class="diag-card">
        <x-analytics.card-header class="diag-card-head" title="Bus Health Diagnostic Breakdown" description="Current bus status with recorded open and overdue maintenance workload." />
        @if($attention->isEmpty())<div class="diag-empty">No attention records for the selected scope.</div>@else<div class="diag-table-wrap"><table class="diag-table"><thead><tr><th>Bus</th><th>Plate</th><th>Model</th><th>Status</th><th>Open Orders</th><th>Overdue Orders</th><th>Attention Level</th></tr></thead><tbody>@foreach($attention->take(12) as $bus)<tr><td><strong>{{ $bus->bus_no }}</strong></td><td>{{ $bus->plate_no ?: '—' }}</td><td>{{ $bus->bus_model ?: '—' }}{{ $bus->year_model ? ' · '.$bus->year_model : '' }}</td><td>{{ $bus->status }}</td><td>{{ $bus->open_orders }}</td><td>{{ $bus->overdue_orders }}</td><td><span class="diag-badge {{ $bus->overdue_orders > 0 ? 'high' : 'medium' }}">{{ $bus->overdue_orders > 0 ? 'Priority Review' : 'Review' }}</span></td></tr>@endforeach</tbody></table></div>@endif
    </article>

    <div class="diag-grid-2">
        <article class="diag-card diag-insight"><div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div><div><h3>Key Insight</h3><p>@if($attention->isNotEmpty()){{ $attention->count() }} buses currently have status or maintenance evidence requiring attention. {{ $d->overdue_orders->count() }} recorded work orders have exceeded their estimated duration, which is the strongest supported urgency signal on this page.@else Current bus status and maintenance records do not show an attention signal. @endif</p></div></article>
        <article class="diag-card"><x-analytics.card-header class="diag-card-head" title="Investigation Priorities" /><ol class="diag-priority-list"><li>Review buses with overdue open work orders first.</li><li>Verify buses currently marked Under Maintenance or Inactive.</li><li>Review repeated maintenance types for workload concentration.</li><li>Confirm maintenance records are complete before inferring component-level causes.</li></ol></article>
    </div>
</section>
