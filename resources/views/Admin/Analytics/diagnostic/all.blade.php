@php
    $all = $diagnostic->all;
    $fleetDiag = $diagnostic->fleet->diagnostics;
    $fuelDiag = $diagnostic->fuel;
    $healthDiag = $diagnostic->bus_health;
    $inventoryDiag = $diagnostic->inventory;

    $domainRows = collect([
        (object) [
            'domain' => 'Fleet & Trip',
            'signal' => 'Trip records requiring review',
            'metric' => 'Delay / movement / idling',
            'observed' => (int) ($fleetDiag->review_count ?? 0),
            'detail' => sprintf('%d delayed · %d high-idle · %d slow-moving', (int) ($fleetDiag->delay_count ?? 0), (int) ($fleetDiag->high_idle_count ?? 0), (int) ($fleetDiag->slow_movement_count ?? 0)),
            'level' => ((int) ($fleetDiag->delay_count ?? 0)) > 0 ? 'High' : (((int) ($fleetDiag->review_count ?? 0)) > 0 ? 'Medium' : 'Low'),
            'status' => ((int) ($fleetDiag->review_count ?? 0)) > 0 ? 'Investigate' : 'Stable',
            'focus' => 'Compare delayed, slow-moving, and high-idle trip records against their route baselines.',
        ],
        (object) [
            'domain' => 'Fuel',
            'signal' => 'Fleet-relative fuel review signals',
            'metric' => 'Fuel efficiency / idling',
            'observed' => $fuelDiag->review_units->count(),
            'detail' => sprintf('%d high-idling units · %.2f km/L fleet average', $fuelDiag->high_idling_units->count(), $fuelDiag->fleet_average),
            'level' => $fuelDiag->high_idling_units->isNotEmpty() ? 'High' : ($fuelDiag->review_units->isNotEmpty() ? 'Medium' : 'Low'),
            'status' => $fuelDiag->review_units->isNotEmpty() ? 'Investigate' : 'Stable',
            'focus' => 'Review units below the selected fleet efficiency baseline and compare their idling intensity.',
        ],
        (object) [
            'domain' => 'Bus Health',
            'signal' => 'Status or maintenance attention',
            'metric' => 'Fleet status / maintenance workload',
            'observed' => $healthDiag->attention_buses->count(),
            'detail' => sprintf('%d open job orders · %d overdue', $healthDiag->open_orders->count(), $healthDiag->overdue_orders->count()),
            'level' => $healthDiag->overdue_orders->isNotEmpty() ? 'High' : ($healthDiag->attention_buses->isNotEmpty() ? 'Medium' : 'Low'),
            'status' => $healthDiag->attention_buses->isNotEmpty() ? 'Investigate' : 'Stable',
            'focus' => 'Inspect non-active buses, open job orders, and overdue maintenance work.',
        ],
        (object) [
            'domain' => 'Inventory',
            'signal' => 'Stock attention records',
            'metric' => 'On-hand / reorder exposure',
            'observed' => $inventoryDiag->attention_rows->count(),
            'detail' => sprintf('%d low stock · %d out of stock', $inventoryDiag->low, $inventoryDiag->critical),
            'level' => $inventoryDiag->critical > 0 ? 'High' : ($inventoryDiag->low > 0 ? 'Medium' : 'Low'),
            'status' => $inventoryDiag->attention_rows->isNotEmpty() ? 'Investigate' : 'Stable',
            'focus' => 'Review out-of-stock items first, then low-stock items nearest their reorder threshold.',
        ],
    ]);

    $rankedRows = $domainRows->sortByDesc('observed')->values();
    $topDomain = $rankedRows->first();
    $baselineCoverage = (float) ($fleetDiag->baseline_coverage_percent ?? 0);
    $fuelReviewCount = $fuelDiag->review_units->count();
    $stockAttentionCount = $inventoryDiag->attention_rows->count();
    $sourceCoverage = collect([
        ['label' => 'Fleet & Trip', 'value' => (int) ($diagnostic->fleet->trip_count ?? 0), 'detail' => sprintf('%.0f%% route-baseline coverage', $baselineCoverage), 'icon' => 'fa-route'],
        ['label' => 'Fuel', 'value' => $fuelDiag->bus_summaries->count(), 'detail' => $fuelReviewCount . ' units flagged for review', 'icon' => 'fa-gas-pump'],
        ['label' => 'Bus Health', 'value' => (int) $healthDiag->total, 'detail' => $healthDiag->open_orders->count() . ' open job orders', 'icon' => 'fa-screwdriver-wrench'],
        ['label' => 'Inventory', 'value' => (int) $inventoryDiag->total, 'detail' => $stockAttentionCount . ' stock attention records', 'icon' => 'fa-boxes-stacked'],
    ]);
@endphp

<section class="diag-stack diag-all-stack">
    <div class="diag-kpis diag-kpis-six">
        <article class="diag-kpi">
            <div class="diag-kpi-icon"><i class="fa-solid fa-wave-square"></i></div>
            <div class="diag-kpi-copy"><span class="diag-kpi-label">Diagnostic Signals</span><div class="diag-kpi-value">{{ number_format($all->signals) }}</div><small>Recorded signals across all four operational domains.</small></div>
        </article>
        <article class="diag-kpi" data-tone="red">
            <div class="diag-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="diag-kpi-copy"><span class="diag-kpi-label">High-Impact Signals</span><div class="diag-kpi-value">{{ number_format($all->high_impact) }}</div><small>Delayed trips, high-idling units, overdue work, and stockouts.</small></div>
        </article>
        <article class="diag-kpi" data-tone="purple">
            <div class="diag-kpi-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div class="diag-kpi-copy"><span class="diag-kpi-label">Areas With Issues</span><div class="diag-kpi-value">{{ $all->areas_with_issues }} / 4</div><small>Operational domains with at least one current investigation signal.</small></div>
        </article>
        <article class="diag-kpi" data-tone="green">
            <div class="diag-kpi-icon"><i class="fa-solid fa-route"></i></div>
            <div class="diag-kpi-copy"><span class="diag-kpi-label">Route Baseline Coverage</span><div class="diag-kpi-value">{{ number_format($baselineCoverage, 0) }}%</div><small>Trip records with enough route history for baseline comparison.</small></div>
        </article>
        <article class="diag-kpi" data-tone="orange">
            <div class="diag-kpi-icon"><i class="fa-solid fa-gas-pump"></i></div>
            <div class="diag-kpi-copy"><span class="diag-kpi-label">Fuel Review Units</span><div class="diag-kpi-value">{{ number_format($fuelReviewCount) }}</div><small>Buses with fleet-relative efficiency or idling review signals.</small></div>
        </article>
        <article class="diag-kpi" data-tone="red">
            <div class="diag-kpi-icon"><i class="fa-solid fa-box-open"></i></div>
            <div class="diag-kpi-copy"><span class="diag-kpi-label">Stock Attention</span><div class="diag-kpi-value">{{ number_format($stockAttentionCount) }}</div><small>Inventory items at low-stock or out-of-stock thresholds.</small></div>
        </article>
    </div>

    <div class="diag-scope-strip" aria-label="Diagnostic evidence scope">
        <div class="diag-scope-title"><i class="fa-solid fa-filter-circle-dollar"></i><div><strong>Evidence Scope</strong><span>Current filters are applied through Period and Bus above.</span></div></div>
        <div class="diag-scope-chip"><span>Domains</span><strong>All 4</strong></div>
        <div class="diag-scope-chip"><span>Signal basis</span><strong>Recorded evidence</strong></div>
        <div class="diag-scope-chip"><span>Severity</span><strong>Low → High</strong></div>
        <div class="diag-scope-chip"><span>Causality</span><strong>Not asserted</strong></div>
        <div class="diag-scope-chip"><span>Period</span><strong>{{ ucwords(str_replace('-', ' ', $period)) }}</strong></div>
    </div>

    <div class="diag-domain-grid diag-domain-grid-nexora">
        <article class="diag-card diag-primary-causes">
            <div class="diag-card-head"><div><h3>Primary Contributing Factors</h3><p>Largest current evidence groups ranked by observed records.</p></div></div>
            <div class="diag-list diag-ranked-list">
                @foreach($rankedRows as $index => $row)
                    <div class="diag-list-row">
                        <span class="diag-list-rank">{{ $index + 1 }}</span>
                        <div><strong>{{ $row->signal }}</strong><small>{{ $row->domain }} · {{ $row->metric }}</small></div>
                        <div class="diag-factor-tail"><span class="diag-list-value">{{ number_format($row->observed) }}</span><span class="diag-badge {{ strtolower($row->level) }}">{{ $row->level }}</span></div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="diag-card diag-impact-card">
            <div class="diag-card-head"><div><h3>Impact by Domain and Metric</h3><p>Supported concentration of recorded signals. “—” means no supported relationship is asserted.</p></div></div>
            <div class="diag-matrix-wrap">
                <table class="diag-matrix diag-matrix-wide">
                    <thead><tr><th>Domain</th><th>Delay / Movement</th><th>Idle</th><th>Fuel Efficiency</th><th>Maintenance</th><th>Stock Risk</th><th>Overall</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>Fleet & Trip</td>
                            <td class="{{ ($fleetDiag->delay_count ?? 0) > 0 ? 'high' : (($fleetDiag->slow_movement_count ?? 0) > 0 ? 'medium' : 'low') }}">{{ ($fleetDiag->delay_count ?? 0) > 0 ? 'High' : (($fleetDiag->slow_movement_count ?? 0) > 0 ? 'Medium' : 'Low') }}</td>
                            <td class="{{ ($fleetDiag->high_idle_count ?? 0) > 0 ? 'medium' : 'low' }}">{{ ($fleetDiag->high_idle_count ?? 0) > 0 ? 'Medium' : 'Low' }}</td>
                            <td>—</td><td>—</td><td>—</td>
                            <td class="{{ ($fleetDiag->review_count ?? 0) > 0 ? 'high' : 'low' }}">{{ number_format((int) ($fleetDiag->review_count ?? 0)) }}</td>
                        </tr>
                        <tr>
                            <td>Fuel</td><td>—</td>
                            <td class="{{ $fuelDiag->high_idling_units->isNotEmpty() ? 'high' : 'low' }}">{{ $fuelDiag->high_idling_units->isNotEmpty() ? 'High' : 'Low' }}</td>
                            <td class="{{ $fuelDiag->review_units->isNotEmpty() ? 'high' : 'low' }}">{{ $fuelDiag->review_units->isNotEmpty() ? 'High' : 'Low' }}</td>
                            <td>—</td><td>—</td>
                            <td class="{{ $fuelDiag->review_units->isNotEmpty() ? 'medium' : 'low' }}">{{ number_format($fuelDiag->review_units->count()) }}</td>
                        </tr>
                        <tr>
                            <td>Bus Health</td><td>—</td><td>—</td><td>—</td>
                            <td class="{{ $healthDiag->overdue_orders->isNotEmpty() ? 'high' : ($healthDiag->attention_buses->isNotEmpty() ? 'medium' : 'low') }}">{{ $healthDiag->overdue_orders->isNotEmpty() ? 'High' : ($healthDiag->attention_buses->isNotEmpty() ? 'Medium' : 'Low') }}</td>
                            <td>—</td>
                            <td class="{{ $healthDiag->attention_buses->isNotEmpty() ? 'medium' : 'low' }}">{{ number_format($healthDiag->attention_buses->count()) }}</td>
                        </tr>
                        <tr>
                            <td>Inventory</td><td>—</td><td>—</td><td>—</td><td>—</td>
                            <td class="{{ $inventoryDiag->critical > 0 ? 'high' : ($inventoryDiag->low > 0 ? 'medium' : 'low') }}">{{ $inventoryDiag->critical > 0 ? 'High' : ($inventoryDiag->low > 0 ? 'Medium' : 'Low') }}</td>
                            <td class="{{ $inventoryDiag->attention_rows->isNotEmpty() ? 'medium' : 'low' }}">{{ number_format($inventoryDiag->attention_rows->count()) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="diag-impact-legend"><span>Impact scale</span><i class="low"></i><small>Low</small><i class="medium"></i><small>Medium</small><i class="high"></i><small>High</small></div>
        </article>

        <article class="diag-card diag-investigation-signals">
            <div class="diag-card-head"><div><h3>Signals Requiring Investigation</h3><p>Current domain-level evidence that deserves review.</p></div></div>
            <div class="diag-list">
                @foreach($domainRows as $index => $row)
                    <div class="diag-list-row diag-signal-row">
                        <span class="diag-list-rank"><i class="fa-solid {{ $index === 0 ? 'fa-route' : ($index === 1 ? 'fa-gas-pump' : ($index === 2 ? 'fa-wrench' : 'fa-box')) }}"></i></span>
                        <div><strong>{{ $row->signal }}</strong><small>{{ $row->domain }} · {{ $row->detail }}</small></div>
                        <div class="diag-signal-tail"><b>{{ number_format($row->observed) }}</b><span class="diag-badge {{ strtolower($row->level) }}">{{ $row->level }}</span></div>
                    </div>
                @endforeach
            </div>
        </article>
    </div>

    <article class="diag-card diag-cross-table">
        <div class="diag-card-head"><div><h3>Cross-Domain Diagnostic Breakdown</h3><p>Observed evidence by domain. Counts and labels are derived from current recorded data, not synthetic confidence scores.</p></div></div>
        <div class="diag-table-wrap">
            <table class="diag-table diag-table-detailed">
                <thead><tr><th>Domain</th><th>Observed Contributor</th><th>Related Metric</th><th>Observed</th><th>Supporting Evidence</th><th>Level</th><th>Status</th><th>Investigation Focus</th></tr></thead>
                <tbody>
                    @foreach($domainRows as $row)
                        <tr>
                            <td><strong>{{ $row->domain }}</strong></td>
                            <td>{{ $row->signal }}</td>
                            <td>{{ $row->metric }}</td>
                            <td><strong>{{ number_format($row->observed) }}</strong></td>
                            <td>{{ $row->detail }}</td>
                            <td><span class="diag-badge {{ strtolower($row->level) }}">{{ $row->level }}</span></td>
                            <td><span class="diag-status {{ strtolower($row->status) }}"><i></i>{{ $row->status }}</span></td>
                            <td>{{ $row->focus }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>

    <div class="diag-grid-3-equal diag-all-bottom">
        <article class="diag-card diag-insight diag-insight-expanded">
            <div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div>
            <div><h3>Key Diagnostic Insight</h3><p>@if($all->signals > 0)The largest current evidence group is <strong>{{ $topDomain?->domain }}</strong> with {{ number_format($topDomain?->observed ?? 0) }} records requiring review. Its concentration is useful for investigation prioritization, but it does not by itself establish causality beyond the recorded evidence.@else No current diagnostic signal is present in the selected records. @endif</p><div class="diag-insight-stat"><span>Highest observed domain</span><strong>{{ $topDomain?->domain ?? 'None' }}</strong><b>{{ number_format($topDomain?->observed ?? 0) }} signals</b></div></div>
        </article>

        <article class="diag-card diag-priority-card">
            <div class="diag-card-head"><div><h3>Investigation Priorities</h3><p>Evidence-first review order for the selected period.</p></div></div>
            <ol class="diag-priority-list diag-priority-numbered">
                <li><span>1</span><p>Review <strong>{{ $topDomain?->domain ?? 'the highest-count domain' }}</strong> and its supporting records first.</p></li>
                <li><span>2</span><p>Compare high-idle and delayed trip records where both signals occur.</p></li>
                <li><span>3</span><p>Review fuel units below the selected fleet efficiency baseline and their idling context.</p></li>
                <li><span>4</span><p>Verify non-active buses, open maintenance work, and stockout exposure.</p></li>
            </ol>
        </article>

        <article class="diag-card diag-coverage-card">
            <div class="diag-card-head"><div><h3>Diagnostic Data Coverage</h3><p>Recorded sources currently represented in this cross-domain analysis.</p></div><span class="diag-badge info">4 sources</span></div>
            <div class="diag-coverage-summary"><div class="diag-coverage-ring" style="--coverage: {{ min(100, max(0, $baselineCoverage)) }}"><span>{{ number_format($baselineCoverage, 0) }}%</span></div><div><strong>Route baseline coverage</strong><p>Fleet & Trip is the only domain currently using a formal historical baseline calculation.</p></div></div>
            <div class="diag-coverage-list">
                @foreach($sourceCoverage as $source)
                    <div><span class="diag-list-rank"><i class="fa-solid {{ $source['icon'] }}"></i></span><p><strong>{{ $source['label'] }}</strong><small>{{ number_format($source['value']) }} records/units · {{ $source['detail'] }}</small></p><span class="diag-badge info">Included</span></div>
                @endforeach
            </div>
        </article>
    </div>
</section>
