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

    $severityBreakdown = collect([
        (object) [
            'domain' => 'Inventory',
            'icon' => 'fa-boxes-stacked',
            'high' => (int) $inventoryDiag->critical,
            'high_label' => 'Critical / Out',
            'moderate' => (int) $inventoryDiag->low,
            'moderate_label' => 'Low Stock',
            'total' => (int) $inventoryDiag->attention_rows->count(),
        ],
        (object) [
            'domain' => 'Bus Health',
            'icon' => 'fa-screwdriver-wrench',
            'high' => (int) $healthDiag->overdue_orders->count(),
            'high_label' => 'Overdue Orders',
            'moderate' => max(0, (int) $healthDiag->attention_buses->count() - (int) $healthDiag->overdue_orders->count()),
            'moderate_label' => 'Active Orders',
            'total' => (int) $healthDiag->attention_buses->count(),
        ],
        (object) [
            'domain' => 'Fuel',
            'icon' => 'fa-gas-pump',
            'high' => (int) $fuelDiag->high_idling_units->count(),
            'high_label' => 'High Idle Units',
            'moderate' => max(0, (int) $fuelDiag->review_units->count() - (int) $fuelDiag->high_idling_units->count()),
            'moderate_label' => 'Sub-Baseline',
            'total' => (int) $fuelDiag->review_units->count(),
        ],
        (object) [
            'domain' => 'Fleet & Trip',
            'icon' => 'fa-route',
            'high' => (int) ($fleetDiag->delay_count ?? 0),
            'high_label' => 'Delayed Trips',
            'moderate' => (int) ($fleetDiag->high_idle_count ?? 0) + (int) ($fleetDiag->slow_movement_count ?? 0),
            'moderate_label' => 'Idle / Slow',
            'total' => (int) ($fleetDiag->review_count ?? 0),
        ],
    ]);
    $totalHighImpact = (int) $all->high_impact;
    $totalModerate = max(0, (int) $all->signals - $totalHighImpact);
    $maxDomainTotal = max(1, (int) $severityBreakdown->max('total'));
@endphp

<section class="diag-stack diag-all-stack">
    <section class="analytics-kpi-strip analytics-kpi-strip-six" aria-label="Diagnostic analytics summary">
        <x-analytics.kpi
            label="Diagnostic Signals"
            :value="number_format($all->signals)"
            description="Recorded signals across all four operational domains."
            icon="fa-wave-square"
            icon-variant="blue"
        />

        <x-analytics.kpi
            label="High-Impact Signals"
            :value="number_format($all->high_impact)"
            description="Delayed trips, high-idling units, overdue work, and stockouts."
            icon="fa-triangle-exclamation"
            icon-variant="red"
        />

        <x-analytics.kpi
            label="Areas With Issues"
            :value="$all->areas_with_issues . ' / 4'"
            description="Operational domains with at least one current investigation signal."
            icon="fa-layer-group"
            icon-variant="purple"
        />

        <x-analytics.kpi
            label="Route Baseline Coverage"
            :value="number_format($baselineCoverage, 0) . '%'"
            description="Trip records with enough route history for baseline comparison."
            icon="fa-route"
            icon-variant="green"
        />

        <x-analytics.kpi
            label="Fuel Review Units"
            :value="number_format($fuelReviewCount)"
            description="Buses with fleet-relative efficiency or idling review signals."
            icon="fa-gas-pump"
            icon-variant="yellow"
        />

        <x-analytics.kpi
            label="Stock Attention"
            :value="number_format($stockAttentionCount)"
            description="Inventory items at low-stock or out-of-stock thresholds."
            icon="fa-box-open"
            icon-variant="red"
        />
    </section>

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
            <x-analytics.card-header class="diag-card-head" title="Primary Contributing Factors" description="Largest current evidence groups ranked by observed records." />
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

        <article class="diag-card diag-severity-card">
            <x-analytics.card-header
                class="diag-card-head"
                title="Signal Severity & Friction Split"
                description="Distribution of critical high-impact friction versus moderate monitoring signals across domains."
            />

            <div class="diag-severity-summary">
                <div class="diag-sev-stat high">
                    <span class="diag-sev-stat-badge"><i class="fa-solid fa-triangle-exclamation"></i> High Impact</span>
                    <strong>{{ number_format($totalHighImpact) }}</strong>
                    <small>Critical operational friction</small>
                </div>
                <div class="diag-sev-stat moderate">
                    <span class="diag-sev-stat-badge"><i class="fa-solid fa-circle-exclamation"></i> Moderate / Watch</span>
                    <strong>{{ number_format($totalModerate) }}</strong>
                    <small>Baseline deviations under observation</small>
                </div>
            </div>

            <div class="diag-severity-bars">
                @foreach($severityBreakdown as $item)
                    @php
                        $barPct = $maxDomainTotal > 0 ? round(($item->total / $maxDomainTotal) * 100, 1) : 0;
                        $highPctOfTotal = $item->total > 0 ? round(($item->high / $item->total) * 100, 1) : 0;
                        $moderatePctOfTotal = $item->total > 0 ? max(0, 100 - $highPctOfTotal) : 0;
                    @endphp
                    <div class="diag-sev-row">
                        <div class="diag-sev-row-head">
                            <div class="diag-sev-domain">
                                <i class="fa-solid {{ $item->icon }}"></i>
                                <span>{{ $item->domain }}</span>
                            </div>
                            <div class="diag-sev-counts">
                                @if($item->high > 0)
                                    <span class="sev-tag high">{{ $item->high }} {{ $item->high_label }}</span>
                                @endif
                                @if($item->moderate > 0)
                                    <span class="sev-tag moderate">{{ $item->moderate }} {{ $item->moderate_label }}</span>
                                @endif
                                @if($item->total === 0)
                                    <span class="sev-tag clean"><i class="fa-solid fa-check"></i> Healthy</span>
                                @endif
                                <strong class="sev-total">({{ number_format($item->total) }})</strong>
                            </div>
                        </div>
                        <div class="diag-sev-track-wrap">
                            <div class="diag-sev-track" style="width: {{ max(10, $barPct) }}%;">
                                @if($item->high > 0)
                                    <div class="diag-sev-segment high" style="width: {{ $highPctOfTotal }}%;" title="{{ $item->high }} {{ $item->high_label }}"></div>
                                @endif
                                @if($item->moderate > 0)
                                    <div class="diag-sev-segment moderate" style="width: {{ $moderatePctOfTotal }}%;" title="{{ $item->moderate }} {{ $item->moderate_label }}"></div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="diag-sev-legend">
                <span class="legend-item"><i class="dot high"></i> High Impact (Immediate Action)</span>
                <span class="legend-item"><i class="dot moderate"></i> Moderate (Monitoring / Watch)</span>
            </div>
        </article>

        <article class="diag-card diag-investigation-signals">
            <x-analytics.card-header class="diag-card-head" title="Signals Requiring Investigation" description="Current domain-level evidence that deserves review." />
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
        <x-analytics.card-header class="diag-card-head" title="Cross-Domain Diagnostic Breakdown" description="Observed evidence by domain. Counts and labels are derived from current recorded data, not synthetic confidence scores." />
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
            <x-analytics.card-header class="diag-card-head" title="Investigation Priorities" description="Evidence-first review order for the selected period." />
            <ol class="diag-priority-list diag-priority-numbered">
                <li><span>1</span><p>Review <strong>{{ $topDomain?->domain ?? 'the highest-count domain' }}</strong> and its supporting records first.</p></li>
                <li><span>2</span><p>Compare high-idle and delayed trip records where both signals occur.</p></li>
                <li><span>3</span><p>Review fuel units below the selected fleet efficiency baseline and their idling context.</p></li>
                <li><span>4</span><p>Verify non-active buses, open maintenance work, and stockout exposure.</p></li>
            </ol>
        </article>

        <article class="diag-card diag-coverage-card">
            <x-analytics.card-header class="diag-card-head" title="Diagnostic Data Coverage" description="Recorded sources currently represented in this cross-domain analysis." badge="4 sources" />
            <div class="diag-coverage-summary"><div class="diag-coverage-ring" style="--coverage: {{ min(100, max(0, $baselineCoverage)) }}"><span>{{ number_format($baselineCoverage, 0) }}%</span></div><div><strong>Route baseline coverage</strong><p>Fleet & Trip is the only domain currently using a formal historical baseline calculation.</p></div></div>
            <div class="diag-coverage-list">
                @foreach($sourceCoverage as $source)
                    <div><span class="diag-list-rank"><i class="fa-solid {{ $source['icon'] }}"></i></span><p><strong>{{ $source['label'] }}</strong><small>{{ number_format($source['value']) }} records/units · {{ $source['detail'] }}</small></p><span class="diag-badge info">Included</span></div>
                @endforeach
            </div>
        </article>
    </div>
</section>
