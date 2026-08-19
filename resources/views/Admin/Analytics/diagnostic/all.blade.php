@php
    $all = $diagnostic->all;
    $fleetDiag = $diagnostic->fleet->diagnostics;
    $fuelDiag = $diagnostic->fuel;
    $healthDiag = $diagnostic->bus_health;
    $inventoryDiag = $diagnostic->inventory;
    $domainRows = collect([
        (object) ['domain' => 'Fleet & Trip', 'signal' => 'Trip records requiring review', 'observed' => (int) ($fleetDiag->review_count ?? 0), 'detail' => sprintf('%d delayed · %d high-idle · %d slow-moving', (int) ($fleetDiag->delay_count ?? 0), (int) ($fleetDiag->high_idle_count ?? 0), (int) ($fleetDiag->slow_movement_count ?? 0)), 'level' => ((int) ($fleetDiag->delay_count ?? 0)) > 0 ? 'High' : (((int) ($fleetDiag->review_count ?? 0)) > 0 ? 'Medium' : 'Low')],
        (object) ['domain' => 'Fuel', 'signal' => 'Fleet-relative fuel review signals', 'observed' => $fuelDiag->review_units->count(), 'detail' => sprintf('%d high-idling units · %.2f km/L fleet average', $fuelDiag->high_idling_units->count(), $fuelDiag->fleet_average), 'level' => $fuelDiag->high_idling_units->isNotEmpty() ? 'High' : ($fuelDiag->review_units->isNotEmpty() ? 'Medium' : 'Low')],
        (object) ['domain' => 'Bus Health', 'signal' => 'Status or maintenance attention', 'observed' => $healthDiag->attention_buses->count(), 'detail' => sprintf('%d open job orders · %d overdue', $healthDiag->open_orders->count(), $healthDiag->overdue_orders->count()), 'level' => $healthDiag->overdue_orders->isNotEmpty() ? 'High' : ($healthDiag->attention_buses->isNotEmpty() ? 'Medium' : 'Low')],
        (object) ['domain' => 'Inventory', 'signal' => 'Stock attention records', 'observed' => $inventoryDiag->attention_rows->count(), 'detail' => sprintf('%d low stock · %d out of stock', $inventoryDiag->low, $inventoryDiag->critical), 'level' => $inventoryDiag->critical > 0 ? 'High' : ($inventoryDiag->low > 0 ? 'Medium' : 'Low')],
    ]);
    $topDomain = $domainRows->sortByDesc('observed')->first();
@endphp

<section class="diag-stack">
    <div class="diag-kpis">
        <article class="diag-kpi"><div class="diag-kpi-icon"><i class="fa-solid fa-wave-square"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Diagnostic Signals</span><div class="diag-kpi-value">{{ number_format($all->signals) }}</div><small>Recorded signals across the four operational domains.</small></div></article>
        <article class="diag-kpi" data-tone="red"><div class="diag-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">High-Impact Signals</span><div class="diag-kpi-value">{{ number_format($all->high_impact) }}</div><small>Delayed trips, high-idling units, overdue work, and stockouts.</small></div></article>
        <article class="diag-kpi" data-tone="orange"><div class="diag-kpi-icon"><i class="fa-solid fa-diagram-project"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Contributing Factors</span><div class="diag-kpi-value">{{ number_format($all->contributing_factors) }}</div><small>Distinct recorded factor groups currently represented.</small></div></article>
        <article class="diag-kpi" data-tone="purple"><div class="diag-kpi-icon"><i class="fa-solid fa-crosshairs"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Areas With Issues</span><div class="diag-kpi-value">{{ $all->areas_with_issues }} / 4</div><small>Domains with at least one current investigation signal.</small></div></article>
    </div>

    <div class="diag-domain-grid">
        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Primary Contributing Factors</h3><p>Largest current evidence groups by operational domain.</p></div></div>
            <div class="diag-list">
                @foreach($all->top_factors as $index => $factor)
                    <div class="diag-list-row">
                        <span class="diag-list-rank">{{ $index + 1 }}</span>
                        <div><strong>{{ $factor->title }}</strong><small>{{ $factor->domain }}</small></div>
                        <span class="diag-list-value">{{ number_format($factor->value) }}</span>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Operational Impact Matrix</h3><p>Where recorded signals are concentrated. “—” means no supported link is asserted.</p></div></div>
            <div class="diag-matrix-wrap">
                <table class="diag-matrix">
                    <thead><tr><th>Area</th><th>Delay / Movement</th><th>Idle</th><th>Fuel Efficiency</th><th>Maintenance</th><th>Stock Risk</th></tr></thead>
                    <tbody>
                        <tr><td>Fleet & Trip</td><td class="{{ ($fleetDiag->delay_count ?? 0) > 0 ? 'high' : (($fleetDiag->slow_movement_count ?? 0) > 0 ? 'medium' : '') }}">{{ ($fleetDiag->delay_count ?? 0) > 0 ? 'High' : (($fleetDiag->slow_movement_count ?? 0) > 0 ? 'Medium' : 'Low') }}</td><td class="{{ ($fleetDiag->high_idle_count ?? 0) > 0 ? 'medium' : '' }}">{{ ($fleetDiag->high_idle_count ?? 0) > 0 ? 'Medium' : 'Low' }}</td><td>—</td><td>—</td><td>—</td></tr>
                        <tr><td>Fuel</td><td>—</td><td class="{{ $fuelDiag->high_idling_units->isNotEmpty() ? 'high' : 'low' }}">{{ $fuelDiag->high_idling_units->isNotEmpty() ? 'High' : 'Low' }}</td><td class="{{ $fuelDiag->review_units->isNotEmpty() ? 'high' : 'low' }}">{{ $fuelDiag->review_units->isNotEmpty() ? 'High' : 'Low' }}</td><td>—</td><td>—</td></tr>
                        <tr><td>Bus Health</td><td>—</td><td>—</td><td>—</td><td class="{{ $healthDiag->overdue_orders->isNotEmpty() ? 'high' : ($healthDiag->attention_buses->isNotEmpty() ? 'medium' : 'low') }}">{{ $healthDiag->overdue_orders->isNotEmpty() ? 'High' : ($healthDiag->attention_buses->isNotEmpty() ? 'Medium' : 'Low') }}</td><td>—</td></tr>
                        <tr><td>Inventory</td><td>—</td><td>—</td><td>—</td><td>—</td><td class="{{ $inventoryDiag->critical > 0 ? 'high' : ($inventoryDiag->low > 0 ? 'medium' : 'low') }}">{{ $inventoryDiag->critical > 0 ? 'High' : ($inventoryDiag->low > 0 ? 'Medium' : 'Low') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Signals Requiring Investigation</h3><p>Current domain-level evidence that deserves review.</p></div></div>
            <div class="diag-list">
                @foreach($domainRows as $index => $row)
                    <div class="diag-list-row">
                        <span class="diag-list-rank"><i class="fa-solid {{ $index === 0 ? 'fa-route' : ($index === 1 ? 'fa-gas-pump' : ($index === 2 ? 'fa-wrench' : 'fa-box')) }}"></i></span>
                        <div><strong>{{ $row->signal }}</strong><small>{{ $row->domain }} · {{ $row->detail }}</small></div>
                        <span class="diag-badge {{ strtolower($row->level) }}">{{ $row->level }}</span>
                    </div>
                @endforeach
            </div>
        </article>
    </div>

    <article class="diag-card diag-cross-table">
        <div class="diag-card-head"><div><h3>Cross-Domain Diagnostic Breakdown</h3><p>Observed evidence by domain. Counts are current records, not synthetic scores.</p></div></div>
        <div class="diag-table-wrap"><table class="diag-table"><thead><tr><th>Domain</th><th>Signal</th><th>Observed</th><th>Supporting Evidence</th><th>Level</th></tr></thead><tbody>
            @foreach($domainRows as $row)
                <tr><td><strong>{{ $row->domain }}</strong></td><td>{{ $row->signal }}</td><td>{{ number_format($row->observed) }}</td><td>{{ $row->detail }}</td><td><span class="diag-badge {{ strtolower($row->level) }}">{{ $row->level }}</span></td></tr>
            @endforeach
        </tbody></table></div>
    </article>

    <div class="diag-grid-3-equal">
        <article class="diag-card diag-insight"><div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div><div><h3>Key Diagnostic Insight</h3><p>@if($all->signals > 0)The largest current evidence group is <strong>{{ $topDomain?->domain }}</strong> with {{ number_format($topDomain?->observed ?? 0) }} records requiring review. This is a concentration signal, not a claim of mechanical or operational causality beyond the recorded evidence.@else No current diagnostic signal is present in the selected records. @endif</p></div></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Investigation Priorities</h3></div></div><ol class="diag-priority-list"><li>Review the highest-count domain and its supporting records first.</li><li>Compare high-idle and delayed trip records where both signals occur.</li><li>Review fuel units below the selected fleet efficiency baseline.</li><li>Verify non-active buses, open maintenance work, and stockout exposure.</li></ol></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Analysis Coverage</h3><p>Recorded sources currently included in this cross-domain view.</p></div></div><div class="diag-list"><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-route"></i></span><div><strong>Fleet & Trip</strong><small>Processed GPS trip records and route baselines.</small></div><span class="diag-badge info">Included</span></div><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-gas-pump"></i></span><div><strong>Fuel</strong><small>Fuel reports plus trip-derived idling context.</small></div><span class="diag-badge info">Included</span></div><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-wrench"></i></span><div><strong>Bus Health</strong><small>Bus status and recorded maintenance workload.</small></div><span class="diag-badge info">Included</span></div><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-boxes-stacked"></i></span><div><strong>Inventory</strong><small>On-hand quantity and reorder thresholds.</small></div><span class="diag-badge info">Included</span></div></div></article>
    </div>
</section>
