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
    <div class="diag-kpis">
        <article class="diag-kpi"><div class="diag-kpi-icon"><i class="fa-solid fa-bus"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Total Buses</span><div class="diag-kpi-value">{{ number_format($d->total) }}</div><small>Bus master records in the selected scope.</small></div></article>
        <article class="diag-kpi" data-tone="green"><div class="diag-kpi-icon"><i class="fa-solid fa-circle-check"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Active</span><div class="diag-kpi-value">{{ number_format($d->active) }}</div><small>{{ number_format($activePct,1) }}% of scoped buses.</small></div></article>
        <article class="diag-kpi" data-tone="orange"><div class="diag-kpi-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Under Maintenance</span><div class="diag-kpi-value">{{ number_format($d->maintenance) }}</div><small>{{ number_format($maintenancePct,1) }}% of scoped buses.</small></div></article>
        <article class="diag-kpi" data-tone="red"><div class="diag-kpi-icon"><i class="fa-solid fa-clock-rotate-left"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Overdue Work Orders</span><div class="diag-kpi-value">{{ number_format($d->overdue_orders->count()) }}</div><small>Open work beyond its recorded estimated duration.</small></div></article>
    </div>

    <div class="health-overview-grid">
        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Fleet Status Distribution</h3><p>Current bus-master status, used instead of an invented mechanical health score.</p></div></div>
            <div class="diag-donut-layout">
                <div class="diag-donut" style="--p1:{{ $activePct }}%;--p2:{{ $activePct+$maintenancePct }}%"><div class="diag-donut-center"><strong>{{ $d->total }}</strong><span>Buses</span></div></div>
                <div class="diag-legend"><div class="diag-legend-row"><i class="diag-dot green"></i><span>Active</span><b>{{ $d->active }} ({{ number_format($activePct,1) }}%)</b></div><div class="diag-legend-row"><i class="diag-dot orange"></i><span>Under Maintenance</span><b>{{ $d->maintenance }} ({{ number_format($maintenancePct,1) }}%)</b></div><div class="diag-legend-row"><i class="diag-dot red"></i><span>Inactive</span><b>{{ $d->inactive }} ({{ number_format($inactivePct,1) }}%)</b></div></div>
            </div>
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Top Maintenance Work Types</h3><p>Open job orders grouped by recorded maintenance type.</p></div></div>
            @if($types->isEmpty())<div class="diag-empty">No open job-order maintenance type is recorded.</div>@else<div class="diag-bars">@foreach($types as $type)<div class="diag-bar-row"><span>{{ $type->label }}</span><div class="diag-bar-track"><i class="diag-bar-fill {{ $loop->first ? 'red' : ($loop->iteration === 2 ? 'orange' : '') }}" style="width:{{ ($type->count/$maxType)*100 }}%"></i></div><b>{{ $type->count }}</b></div>@endforeach</div>@endif
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Buses Requiring Attention</h3><p>Status and recorded maintenance workload, ranked by evidence burden.</p></div></div>
            @if($attention->isEmpty())<div class="diag-empty">No bus currently has a non-active status or open maintenance order.</div>@else<div class="diag-list">@foreach($attention->take(6) as $bus)<div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-bus"></i></span><div><strong>{{ $bus->bus_no }}</strong><small>{{ $bus->status }} · {{ $bus->open_orders }} open · {{ $bus->overdue_orders }} overdue</small></div><span class="diag-badge {{ $bus->overdue_orders > 0 ? 'high' : 'medium' }}">{{ $bus->attention_score }}</span></div>@endforeach</div>@endif
        </article>
    </div>

    <div class="health-secondary-grid">
        <article class="diag-card"><div class="diag-card-head"><div><h3>Current Status by Bus</h3></div></div><div class="health-status-list">@forelse($d->buses->take(5) as $bus)<div class="health-status-row"><div><strong>{{ $bus->bus_no }}</strong><div style="color:#7a8aa1;font-size:9.5px">{{ $bus->bus_model ?: 'Model not recorded' }}</div></div><span class="diag-badge {{ $bus->status === 'Active' ? 'low' : ($bus->status === 'Inactive' ? 'high' : 'medium') }}">{{ $bus->status }}</span></div>@empty<div class="diag-empty">No bus records.</div>@endforelse</div></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Open Maintenance</h3><p>Current job-order workload.</p></div></div><div class="diag-kpi-value">{{ number_format($d->open_orders->count()) }}</div><small style="color:#75869d">Open job orders</small><div style="margin-top:12px" class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-clock"></i></span><div><strong>Overdue</strong><small>Exceeded recorded estimate</small></div><span class="diag-list-value">{{ $d->overdue_orders->count() }}</span></div></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Attention Coverage</h3><p>Buses with current status or maintenance signals.</p></div></div><div class="diag-kpi-value">{{ number_format($attention->count()) }}</div><small style="color:#75869d">of {{ $d->total }} scoped buses</small><div class="diag-bar-track" style="margin-top:16px;height:10px"><i class="diag-bar-fill orange" style="width:{{ min(100,($attention->count()/$total)*100) }}%"></i></div></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Inactive Exposure</h3></div></div><div class="diag-kpi-value">{{ number_format($d->inactive) }}</div><small style="color:#75869d">{{ number_format($inactivePct,1) }}% of scoped buses</small></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Data Boundary</h3></div></div><p style="margin:0;color:#64758d;font-size:10px;line-height:1.55">No mechanical health score, MTBF, component-failure probability, or health trend is inferred unless those measurements are directly supported by recorded maintenance data.</p></article>
    </div>

    <article class="diag-card">
        <div class="diag-card-head"><div><h3>Bus Health Diagnostic Breakdown</h3><p>Current bus status with recorded open and overdue maintenance workload.</p></div></div>
        @if($attention->isEmpty())<div class="diag-empty">No attention records for the selected scope.</div>@else<div class="diag-table-wrap"><table class="diag-table"><thead><tr><th>Bus</th><th>Plate</th><th>Model</th><th>Status</th><th>Open Orders</th><th>Overdue Orders</th><th>Attention Level</th></tr></thead><tbody>@foreach($attention->take(12) as $bus)<tr><td><strong>{{ $bus->bus_no }}</strong></td><td>{{ $bus->plate_no ?: '—' }}</td><td>{{ $bus->bus_model ?: '—' }}{{ $bus->year_model ? ' · '.$bus->year_model : '' }}</td><td>{{ $bus->status }}</td><td>{{ $bus->open_orders }}</td><td>{{ $bus->overdue_orders }}</td><td><span class="diag-badge {{ $bus->overdue_orders > 0 ? 'high' : 'medium' }}">{{ $bus->overdue_orders > 0 ? 'Priority Review' : 'Review' }}</span></td></tr>@endforeach</tbody></table></div>@endif
    </article>

    <div class="diag-grid-2">
        <article class="diag-card diag-insight"><div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div><div><h3>Key Insight</h3><p>@if($attention->isNotEmpty()){{ $attention->count() }} buses currently have status or maintenance evidence requiring attention. {{ $d->overdue_orders->count() }} recorded work orders have exceeded their estimated duration, which is the strongest supported urgency signal on this page.@else Current bus status and maintenance records do not show an attention signal. @endif</p></div></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Investigation Priorities</h3></div></div><ol class="diag-priority-list"><li>Review buses with overdue open work orders first.</li><li>Verify buses currently marked Under Maintenance or Inactive.</li><li>Review repeated maintenance types for workload concentration.</li><li>Confirm maintenance records are complete before inferring component-level causes.</li></ol></article>
    </div>
</section>
